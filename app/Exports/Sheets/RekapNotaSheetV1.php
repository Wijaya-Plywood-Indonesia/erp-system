<?php

namespace App\Exports\Sheets;

use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use App\Models\ReferensiHargaProduksi;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapNotaSheetV1 implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private const FMT_TANGGAL = 'dd\-mm\-yyyy';
    private const FMT_ANGKA = '_(* #,##0_);_(* \(#,##0\);_(* "-"??_);_(@_)';
    private const FMT_M3 = '_(* #,##0.0000_);_(* \(#,##0.0000\);_(* "-"??_);_(@_)';

    private const TEKS_CEK_HARGA = 'cek referensi harga produksi';
    private const TEKS_CEK_AKUN = 'cek akun jurnal';

    public int $nomorBerikutnya;
    private array $cacheHarga = [];

    public function __construct(
        protected string $jenis,
        protected string $tanggal,
        protected ?int $nomorAwal = null,
    ) {
        $this->nomorBerikutnya = $nomorAwal ?? 1;
    }

    public function title(): string
    {
        return strtoupper(
            Carbon::parse($this->tanggal)->locale('id')->translatedFormat('F')
        );
    }

    public function headings(): array
    {
        return [
            'NAMA AKUN', 'TANGGAL', 'No', 'NO.AKUN', 'NO', 'mm',
            'Nama', 'Keterangan', 'Map', 'Hit', 'Banyak', 'm3', 'Harga', 'Total',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 36.4, 'B' => 10.2, 'C' => 6.5, 'D' => 9.5, 'E' => 13.2,
            'F' => 6.5,  'G' => 19.2, 'H' => 78.9, 'I' => 4.4, 'J' => 3.5,
            'K' => 8.7,  'L' => 11.3, 'M' => 28,   'N' => 14.2,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => self::FMT_TANGGAL,
            'K' => self::FMT_ANGKA,
            'L' => self::FMT_M3,
            'M' => self::FMT_ANGKA,
            'N' => self::FMT_ANGKA,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => config('akun_jurnal.warna_header', 'FFFFC6C6')],
            ],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max($sheet->getHighestRow(), 2);

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:N'.$last);

                for ($r = 2; $r <= $last; $r++) {
                    if ($sheet->getCell("A{$r}")->getValue() === self::TEKS_CEK_AKUN) {
                        $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                            'font' => ['color' => ['argb' => 'FFC00000'], 'bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFF2CC'],
                            ],
                        ]);
                    }
                    if ($sheet->getCell("M{$r}")->getValue() === self::TEKS_CEK_HARGA) {
                        $sheet->getStyle("M{$r}")->applyFromArray([
                            'font' => ['color' => ['argb' => 'FFC00000'], 'bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFF2CC'],
                            ],
                        ]);
                    }
                }
            },
        ];
    }

    public function array(): array
    {
        $isBk = $this->jenis === 'bk';
        $prefixes = $isBk ? ['BK'] : ['BM', 'BML'];

        $query = $isBk ? NotaBarangKeluar::query() : NotaBarangMasuk::query();

        $notas = $query
            ->with(['detail', 'mutasi.details.ukuran', 'mutasi.details.jenisKayu'])
            ->whereDate('tanggal', $this->tanggal)
            ->orderBy('id')
            ->get()
            ->filter(fn ($nota) => in_array($this->prefix($nota), $prefixes, true));

        $noJurnal = $this->nomorAwal ?? 1;
        $rows = [];

        foreach ($notas as $nota) {
            $prefix = strtolower($this->prefix($nota));
            $items = $this->items($nota);

            if ($items === []) {
                continue;
            }

            foreach ($items as $item) {
                $rows[] = $this->row($nota, $noJurnal, $this->akun($prefix, $item, 'd'), 'd', $item);
            }
            foreach ($items as $item) {
                $rows[] = $this->row($nota, $noJurnal, $this->akun($prefix, $item, 'k'), 'k', $item);
            }

            $noJurnal++;
        }

        $this->nomorBerikutnya = $noJurnal;

        return $rows;
    }

    protected function prefix($nota): string
    {
        preg_match('/^[A-Za-z]+/', trim((string) $nota->no_nota), $m);
        return strtoupper($m[0] ?? '');
    }

    protected function items($nota): array
    {
        if ($nota->mutasi && $nota->mutasi->details->isNotEmpty()) {
            return $nota->mutasi->details->map(function ($d) use ($nota) {
                $qty = (float) $d->qty;
                $m3 = (float) $d->m3;
                $harga = (float) $d->harga;
                $mutasi = $nota->mutasi;
                $ukuran = $this->ukuranTeks($d);
                $kayu = $this->kayuTeks($d, $mutasi);
                $kunci = $this->kunciVeneer($d, $mutasi);

                if ($kunci === null || $ukuran === '' || $kayu === '') {
                    logger()->warning('RekapNota: ukuran/jenis kayu/akun veneer tidak terbaca', [
                        'nota' => $nota->no_nota,
                        'kunci_akun' => $kunci,
                        'detail' => $d->getAttributes(),
                        'mutasi' => $mutasi?->getAttributes(),
                        'ukuran' => data_get($d, 'ukuran')?->toArray(),
                        'jenisKayu' => data_get($d, 'jenisKayu')?->toArray(),
                    ]);
                }

                $hargaKosong = false;
                if ($harga <= 0) {
                    $harga = $this->hargaReferensi($d, $mutasi);
                    if ($harga <= 0) {
                        $hargaKosong = true;
                        logger()->warning('RekapNota: harga referensi tidak ketemu', [
                            'nota' => $nota->no_nota,
                            'detail' => $d->getAttributes(),
                        ]);
                    }
                }

                return [
                    'mm' => $ukuran !== '' ? $ukuran : null,
                    'nama' => trim(($d->tipe_veneer ?? '').' '.data_get($d, 'jenisKayu.nama_kayu', '').' KW '.($d->kw ?? '')),
                    'keterangan' => $this->keteranganVeneer($d, $ukuran, $kayu, $mutasi),
                    'veneer' => $kunci,
                    'hit' => 'm',
                    'qty' => $qty,
                    'm3' => $m3,
                    'harga' => $hargaKosong ? self::TEKS_CEK_HARGA : $harga,
                    'total' => $hargaKosong ? 0 : round($m3 * $harga, 2),
                ];
            })->all();
        }

        return $nota->detail->map(function ($d) {
            $qty = (float) $d->jumlah;
            $harga = (float) ($d->getAttributes()['harga'] ?? 0);

            return [
                'mm' => null,
                'nama' => $d->nama_barang,
                'keterangan' => $this->keteranganBarang($d),
                'veneer' => null,
                'hit' => 'b',
                'qty' => $qty,
                'm3' => null,
                'harga' => $harga,
                'total' => round($qty * $harga),
            ];
        })->all();
    }

    protected function keteranganBarang($d): string
    {
        $nama = trim((string) ($d->nama_barang ?? ''));
        $satuan = trim((string) ($d->satuan ?? ''));
        $catatan = trim((string) ($d->keterangan ?? ''));

        if ($nama !== '' && $catatan !== '' && str_starts_with(mb_strtolower($catatan), mb_strtolower($nama))) {
            $nama = '';
        }

        return trim(implode(' ', array_filter([
            $nama,
            $satuan !== '' ? '@'.$satuan : null,
            $catatan,
        ])));
    }

    protected function keteranganVeneer($d, string $ukuran, string $kayu, $mutasi): string
    {
        $kw = trim((string) data_get($d, 'kw', ''));
        $tipe = trim((string) data_get($d, 'tipe_veneer', ''));

        $teks = trim(implode(' ', array_filter([
            $ukuran,
            $kayu,
            $kw !== '' ? 'KW '.$kw : null,
            '@lembar vener'.($tipe !== '' ? ' '.$tipe : ''),
        ])));

        $catatan = trim((string) data_get($mutasi, 'keterangan', ''));
        return $catatan !== '' ? $teks.' // '.$catatan : $teks;
    }

    protected function ukuranTeks($d): string
    {
        $nama = trim((string) data_get($d, 'ukuran.nama', ''));
        if ($nama !== '') return $nama;

        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        foreach ([$d, data_get($d, 'ukuran')] as $src) {
            $p = data_get($src, 'panjang');
            $l = data_get($src, 'lebar');
            $t = data_get($src, 'tebal');
            if ($p !== null && $l !== null && $t !== null) {
                return $fmt($p).'x'.$fmt($l).'x'.$fmt($t);
            }
        }
        return '';
    }

    protected function kayuTeks($d, $mutasi = null): string
    {
        foreach ([$d, $mutasi] as $src) {
            $nama = trim((string) data_get($src, 'jenisKayu.nama_kayu', ''));
            if ($nama !== '') return $nama;
            $kode = trim((string) data_get($src, 'jenisKayu.kode_kayu', ''));
            if ($kode !== '') return $kode;
        }
        return '';
    }

    protected function kayuKunci($d, $mutasi = null): ?string
    {
        $peta = config('akun_jurnal.kayu', [
            'sengon' => ['sengon', 'sgn', 's'],
            'meranti' => ['meranti', 'waru', 'mrt', 'm'],
        ]);

        foreach ([$d, $mutasi] as $src) {
            $nama = mb_strtolower(trim((string) data_get($src, 'jenisKayu.nama_kayu', '')));
            $kode = mb_strtolower(trim((string) data_get($src, 'jenisKayu.kode_kayu', '')));

            foreach ($peta as $kunci => $daftar) {
                if ($nama !== '') {
                    foreach (array_merge([$kunci], $daftar) as $kata) {
                        if (mb_strlen($kata) >= 3 && str_contains($nama, mb_strtolower($kata))) {
                            return $kunci;
                        }
                    }
                }
                if ($kode !== '' && in_array($kode, $daftar, true)) {
                    return $kunci;
                }
            }
        }
        return null;
    }

    protected function hargaReferensi($d, $mutasi = null): float
    {
        $angka = fn ($v) => is_numeric($v) ? $v + 0 : null;
        $ambil = fn (string $k) => data_get($d, $k) ?? data_get($mutasi, $k);

        $idJenisKayu = $angka($ambil('id_jenis_kayu'));
        $idKategori = $angka($ambil('id_kategori_barang'));
        $idGrade = $angka($ambil('id_grade'));
        $kw = $angka(data_get($d, 'kw'));
        $tebal = $angka(data_get($d, 'tebal') ?? data_get($d, 'ukuran.tebal'));
        $idUkuran = $angka(data_get($d, 'id_ukuran'));

        if ($idJenisKayu === null) {
            return 0.0;
        }

        $kunciCache = json_encode([$idJenisKayu, $idKategori, $idGrade, $kw, $tebal, $idUkuran]);

        return $this->cacheHarga[$kunciCache] ??= (float) (ReferensiHargaProduksi::findReferensi(
            idJenisKayu: $idJenisKayu !== null ? (int) $idJenisKayu : null,
            idKategoriBarang: $idKategori !== null ? (int) $idKategori : null,
            idGrade: $idGrade !== null ? (int) $idGrade : null,
            kw: $kw !== null ? (int) $kw : null,
            tebal: $tebal !== null ? (float) $tebal : null,
            idUkuran: $idUkuran !== null ? (int) $idUkuran : null,
        )?->harga ?? 0);
    }

    protected function kunciVeneer($d, $mutasi = null): ?string
    {
        $tipe = mb_strtolower(trim((string) ($d->tipe_veneer ?? '')));
        $kayu = (string) $this->kayuKunci($d, $mutasi);
        $kw = mb_strtolower(trim((string) ($d->kw ?? '')));
        $ukuran = mb_strtolower($this->ukuranTeks($d));

        if ($tipe === '' || $kayu === '') {
            return null;
        }

        if (in_array($kw, ['af', 'aff'], true)) {
            $kelompok = 'ppc';
        } else {
            $tebal = (float) (preg_split('/x/i', $ukuran)[2] ?? 0);
            $kelompok = $tebal > 0 && $tebal < 1 ? '260' : '130';
        }

        return "{$tipe}|{$kelompok}|{$kayu}";
    }

    protected function akun(string $prefix, array $item, string $map): array
    {
        $sisi = $map === 'd' ? 'debit' : 'kredit';
        $adaKunci = ! empty($item['veneer']);

        if ($prefix === 'bk' && $adaKunci) {
            $v = config('akun_jurnal.veneer.'.$item['veneer']);
            if (is_array($v)) {
                return [
                    'nama' => $v['nama'].' '.($map === 'd' ? 'WHN' : 'WJY'),
                    'kode' => round((float) $v['kode'] + ($map === 'd' ? 0.01 : 0), 2),
                ];
            }
        }

        $daftar = config('akun_jurnal.'.$prefix.'.barang', []);
        $namaItem = mb_strtolower(trim((string) $item['nama']));
        $barang = $daftar[$namaItem] ?? null;

        // Coba fuzzy match jika nama barang mengandung kata kunci di daftar barang config
        if (! $barang) {
            foreach ($daftar as $k => $v) {
                if (str_contains($namaItem, $k) || str_contains($k, $namaItem)) {
                    $barang = $v;
                    break;
                }
            }
        }

        if (is_array($barang) && isset($barang[$sisi]) && ! empty($barang[$sisi]['kode'])) {
            return $barang[$sisi];
        }

        $fallback = config('akun_jurnal.'.$prefix.'.'.$sisi, []);
        if (($item['hit'] ?? '') !== 'm' && ! empty($fallback['kode'])) {
            return $fallback;
        }

        return ['nama' => self::TEKS_CEK_AKUN, 'kode' => self::TEKS_CEK_AKUN];
    }

    protected function row($nota, int $noJurnal, array $akun, string $map, array $item): array
    {
        $kode = $akun['kode'] ?? null;

        return [
            $akun['nama'] ?? null,
            $nota->tanggal ? ExcelDate::dateTimeToExcel($nota->tanggal) : null,
            $noJurnal,
            is_numeric($kode) ? (float) $kode : $kode,
            $nota->no_nota,
            $item['mm'],
            ($this->jenis === 'bk' ? config('akun_jurnal.bk.nama') : null) ?: $nota->tujuan_nota,
            $item['keterangan'] ?: $item['nama'],
            $map,
            $item['hit'],
            $item['qty'],
            $item['m3'],
            $item['harga'],
            $item['total'],
        ];
    }
}
