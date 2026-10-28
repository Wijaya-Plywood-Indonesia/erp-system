<?php

namespace App\Exports\Sheets;

use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use App\Models\ReferensiHargaProduksi;
use App\Services\BkbmCoaService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapNotaSheetV2 extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private const FMT_TANGGAL = 'dd\-mm\-yyyy';
    private const FMT_ANGKA = '_(* #,##0_);_(* \(#,##0\);_(* "-"??_);_(@_)';
    private const FMT_M3 = '_(* #,##0.0000_);_(* \(#,##0.0000\);_(* "-"??_);_(@_)';

    private const TEKS_CEK_HARGA = 'cek referensi harga produksi';
    private const TEKS_CEK_AKUN = 'cek akun jurnal';

    public int $nomorBerikutnya;
    private array $cacheHarga = [];
    protected BkbmCoaService $coaService;

    public function __construct(
        protected string $jenis,
        protected string $tanggal,
        protected ?int $nomorAwal = null,
        ?BkbmCoaService $coaService = null
    ) {
        $this->nomorBerikutnya = $nomorAwal ?? 1;
        $this->coaService = $coaService ?? new BkbmCoaService();
    }

    public function bindValue(Cell $cell, $value)
    {
        // Kolom D (No Akun) HARUS selalu jadi teks supaya titik desimal tidak berubah jadi koma
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'Jurnal V2';
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
            'A' => 36.4, 'B' => 10.2, 'C' => 6.5, 'D' => 12.0, 'E' => 13.2,
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
            'font' => ['name' => 'Calibri', 'size' => 11, 'bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE2EFDA'], // Hijau soft pembeda sheet V2
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
        // Menyertakan BK, BKL (Barang Keluar Lainnya), dan SJ / DY (Surat Jalan Penjualan)
        $prefixes = $isBk ? ['BK', 'BKL', 'SJ', 'DY'] : ['BM', 'BML'];

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
            $prefix = strtoupper($this->prefix($nota));
            $items = $this->items($nota);
            if ($items === []) {
                continue;
            }

            // Jika transaksi Penjualan (SJ / DY):
            // Pasangan 1 (Beban Pokok & Pengurangan Stok):
            //   Debit:  5069.1 (Harga Pokok Penjualan)
            //   Kredit: 1404.2 (Persediaan Triplek Siap Jual)
            // Pasangan 2 (Piutang & Pendapatan Penjualan):
            //   Debit:  1122.1 (Piutang Usaha - Pihak Ketiga)
            //   Kredit: 4002.1 (Penjualan Domestik)
            if (in_array($prefix, ['SJ', 'DY'], true)) {
                // Pasangan 1: HPP (D) vs Persediaan Triplek (K)
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, BkbmCoaService::AKUN_HPP, 'd', $item);
                }
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, BkbmCoaService::AKUN_TRIPLEK_SIAP_JUAL, 'k', $item);
                }

                // Pasangan 2: Piutang Usaha (D) vs Penjualan Domestik (K)
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, BkbmCoaService::AKUN_PIUTANG_USAHA, 'd', $item);
                }
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, BkbmCoaService::AKUN_PENJUALAN_DOMESTIK, 'k', $item);
                }
            } else {
                // Transaksi BK & BKL biasa: Baris Debit dulu, lalu baris Kredit
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, $this->akunV2($item, 'd', $isBk, $prefix), 'd', $item);
                }
                foreach ($items as $item) {
                    $rows[] = $this->row($nota, $noJurnal, $this->akunV2($item, 'k', $isBk, $prefix), 'k', $item);
                }
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

                $hargaKosong = false;
                if ($harga <= 0) {
                    $harga = $this->hargaReferensi($d, $mutasi);
                    if ($harga <= 0) {
                        $hargaKosong = true;
                    }
                }

                $tebal = (float) (preg_split('/x/i', $ukuran)[2] ?? 0);

                return [
                    'tipe' => 'veneer',
                    'tipe_veneer' => $d->tipe_veneer ?? 'jadi',
                    'kw' => $d->kw ?? null,
                    'tebal' => $tebal,
                    'mm' => $ukuran !== '' ? $ukuran : null,
                    'nama' => trim(($d->tipe_veneer ?? '').' '.data_get($d, 'jenisKayu.nama_kayu', '').' KW '.($d->kw ?? '')),
                    'keterangan' => $this->keteranganVeneer($d, $ukuran, $kayu, $mutasi),
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
            $namaBarang = (string) $d->nama_barang;
            $satuan = strtolower(trim((string) $d->satuan));

            // Deteksi apakah barang manual ini sebenarnya adalah veneer (misal: "130×65×3,7(af(s) vener jadi" atau "70×55×0,5 af(s) kering")
            $isVeneer = str_contains(strtolower($namaBarang), 'vener') || str_contains(strtolower($namaBarang), 'veneer') || (str_contains(strtolower($namaBarang), 'af') && str_contains(strtolower($namaBarang), 'kering'));
            $mm = null;
            $m3 = null;
            $hit = 'b';

            if ($isVeneer) {
                // Ekstrak dimensi panjang × lebar × tebal
                if (preg_match('/([0-9]+)\s*(?:x|×)\s*([0-9]+)\s*(?:x|×)\s*([0-9]+(?:[\.,][0-9]+)?)/iu', $namaBarang, $m)) {
                    $p = (float) $m[1];
                    $l = (float) $m[2];
                    $t = (float) str_replace(',', '.', $m[3]);
                    $mm = "{$p}x{$l}x{$t}";
                    // Hitung m3 jika satuan lembar: (p * l * t * qty) / 10.000.000
                    $m3 = round(($p * $l * $t * $qty) / 10000000, 4);
                    $hit = 'm';
                }
            }

            return [
                'tipe' => $isVeneer ? 'veneer' : 'barang',
                'tipe_veneer' => null,
                'kw' => null,
                'tebal' => 0,
                'mm' => $mm,
                'nama' => $d->nama_barang,
                'keterangan' => $this->keteranganBarang($d),
                'hit' => $hit,
                'qty' => $qty,
                'm3' => $m3,
                'harga' => $harga,
                'total' => $hit === 'm' && $m3 !== null && $harga > 0 ? round($m3 * $harga, 2) : round($qty * $harga),
            ];
        })->all();
    }

    /**
     * Resolusi akun COA baru (V2) tanpa akun .0
     */
    protected function akunV2(array $item, string $sisi, bool $isBk, string $prefix = 'BK'): array
    {
        // 1. Transaksi Penjualan: Surat Jalan (SJ) & DY
        if (in_array($prefix, ['SJ', 'DY'], true)) {
            if ($sisi === 'd') {
                return [
                    'nama' => BkbmCoaService::AKUN_PIUTANG_USAHA['nama'],
                    'kode' => BkbmCoaService::AKUN_PIUTANG_USAHA['no'],
                ];
            } else {
                return [
                    'nama' => BkbmCoaService::AKUN_PENJUALAN_DOMESTIK['nama'],
                    'kode' => BkbmCoaService::AKUN_PENJUALAN_DOMESTIK['no'],
                ];
            }
        }

        // 2. Transaksi Barang Keluar Lainnya (BKL)
        if ($prefix === 'BKL') {
            $akunBarang = $this->coaService->getAkunBarang(
                $item['nama'] ?? '',
                $item['keterangan'] ?? ''
            );

            if ($sisi === 'd') {
                // Sisi Debit BKL: Jika sebetan/limbah -> Beban Operasional Pabrik, jika bukan -> Piutang Lainnya
                if (str_contains(strtolower($item['nama'] ?? ''), 'sebetan') || str_contains(strtolower($item['keterangan'] ?? ''), 'sebetan')) {
                    return [
                        'nama' => BkbmCoaService::AKUN_BIAYA_OPERASIONAL_PABRIK['nama'],
                        'kode' => BkbmCoaService::AKUN_BIAYA_OPERASIONAL_PABRIK['no'],
                    ];
                }
                return [
                    'nama' => BkbmCoaService::AKUN_PIUTANG_LAINNYA['nama'],
                    'kode' => BkbmCoaService::AKUN_PIUTANG_LAINNYA['no'],
                ];
            } else {
                // Sisi Kredit BKL: Akun persediaan barang asal
                if ($akunBarang) {
                    return [
                        'nama' => $akunBarang['nama'],
                        'kode' => $akunBarang['no'],
                    ];
                }
                return [
                    'nama' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['nama'],
                    'kode' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['no'],
                ];
            }
        }

        // 3. Jika item Veneer dari mutasi palet (BK Transfer)
        if (($item['tipe'] ?? '') === 'veneer' && !empty($item['tipe_veneer'])) {
            $akunPersediaan = $this->coaService->getAkunVeneer(
                $item['tipe_veneer'] ?? 'jadi',
                $item['kw'] ?? null,
                (float) ($item['tebal'] ?? 0)
            );

            return [
                'nama' => $akunPersediaan['nama'],
                'kode' => $akunPersediaan['no'],
            ];
        }

        // 4. Jika item Barang Umum / Non-Veneer / Veneer Manual
        $akunBarang = $this->coaService->getAkunBarang(
            $item['nama'] ?? '',
            $item['keterangan'] ?? ''
        );

        if ($isBk) {
            // Pengeluaran Barang (BK Transfer)
            if ($akunBarang) {
                return [
                    'nama' => $akunBarang['nama'],
                    'kode' => $akunBarang['no'],
                ];
            }
            // Fallback BK: Perlengkapan Pabrik
            return [
                'nama' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['nama'],
                'kode' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['no'],
            ];
        } else {
            // Penerimaan Barang (BM)
            if ($sisi === 'd') {
                if ($akunBarang) {
                    return [
                        'nama' => $akunBarang['nama'],
                        'kode' => $akunBarang['no'],
                    ];
                }
                return [
                    'nama' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['nama'],
                    'kode' => BkbmCoaService::AKUN_PERLENGKAPAN_PABRIK['no'],
                ];
            } else {
                return [
                    'nama' => BkbmCoaService::AKUN_KAS_UTAMA['nama'],
                    'kode' => BkbmCoaService::AKUN_KAS_UTAMA['no'],
                ];
            }
        }
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

    protected function row($nota, int $noJurnal, array $akun, string $map, array $item): array
    {
        $kode = $akun['no'] ?? ($akun['kode'] ?? null);

        // Jika transaksi Penjualan (SJ/DY), kolom "Nama" mencatat Nama Customer/Tujuan Nota
        $prefix = strtoupper($this->prefix($nota));
        $namaCol = in_array($prefix, ['SJ', 'DY'], true)
            ? $nota->tujuan_nota
            : (($this->jenis === 'bk' ? config('akun_jurnal.bk.nama') : null) ?: $nota->tujuan_nota);

        return [
            $akun['nama'] ?? null,
            $nota->tanggal ? ExcelDate::dateTimeToExcel($nota->tanggal) : null,
            $noJurnal,
            (string) $kode,
            $nota->no_nota,
            $item['mm'],
            $namaCol,
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
