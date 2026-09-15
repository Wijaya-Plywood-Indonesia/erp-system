<?php

namespace App\Exports\Sheets;

use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\ReferensiHargaProduksi;
use App\Models\Ukuran;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// FILE INI TIDAK MENGUBAH Sheets\JurnalSheet.php SAMA SEKALI.
// Ini COPY dengan:
// - Nama class baru: JurnalSheetV2
// - Judul sheet baru: 'jurnal produksi v2'
// - Akun Hutang Gaji: 2231.00 -> 2195.1
// - Akun hpp/selisih: 6111.00 -> 5069.2 "Selisih harga patok produksi"
//
// CATATAN PENTING:
// Akun untuk item veneer (jadi/kering/af/basah/basah af) di file ini
// TIDAK di-hardcode di kode — diambil dinamis dari DB lewat
// ReferensiHargaProduksi->subAnakAkun (lihat extractAkun()). Supaya
// akun-akun itu ikut memakai COA baru, ubah datanya di tabel
// referensi/sub_anak_akun terkait, bukan di file ini.
//
// Cara pakai: daftarkan class ini di sheets() milik
// LaporanPressDryerExport TANPA menghapus sheet lama, misalnya:
//
//     public function sheets(): array
//     {
//         return [
//             new Sheets\LaporanPressDryerSheet($this->dataProduksi),
//             new Sheets\HasilProduksiSheet($this->dataProduksi),
//             new Sheets\JurnalSheet($this->dataProduksi),   // lama, tidak diubah
//             new Sheets\JurnalSheetV2($this->dataProduksi), // baru
//         ];
//     }
// ============================================================
class JurnalSheetV2 implements FromArray, WithColumnWidths, WithEvents, WithMapping, WithStyles, WithTitle
{
    protected array $dataProduksi;

    protected int $rowIndex = 0;

    // Cache agar tidak query DB berulang
    private array $refCache = [];

    private array $kayuCache = [];

    private array $kategoriCache = [];

    private array $ukuranCache = [];

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = $dataProduksi;
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,
            'B' => 20,
            'C' => 15,
            'D' => 12,
            'E' => 8,
            'F' => 18,
            'G' => 20,
            'H' => 45,
            'I' => 6,
            'J' => 10,
            'K' => 10,
            'L' => 15,
            'M' => 15,
            'N' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D4F']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // No Akun (kolom D) dibiarkan sebagai TEKS, bukan angka.
        // Kalau diformat numerik (mis. '0.00'), Excel akan memaksa semua
        // kode akun tampil 2 digit di belakang titik, sehingga "1402.1"
        // berubah tampilan jadi "1402.10" padahal itu kode yang berbeda.
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    /**
     * Paksa kolom "No Akun" (D) disimpan sebagai STRING eksplisit di level
     * cell, bukan cuma diformat tampilan sebagai teks. Ini perlu karena
     * PhpSpreadsheet otomatis mendeteksi nilai seperti "1402.7" sebagai
     * angka (float) saat FromArray menulisnya. Kalau dibiarkan jadi angka,
     * Excel akan menampilkannya memakai koma sebagai pemisah desimal pada
     * locale Indonesia (mis. "1402,7"), walaupun format cell-nya "Text".
     * Dengan setCellValueExplicit(..., TYPE_STRING), nilainya benar-benar
     * jadi teks "1402.7" apa adanya, titik tidak pernah berubah jadi koma.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                for ($row = 2; $row <= $lastRow; $row++) {
                    $cell = $sheet->getCell("D{$row}");
                    $value = $cell->getValue();

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $sheet->setCellValueExplicit("D{$row}", (string) $value, DataType::TYPE_STRING);
                }
            },
        ];
    }

    // =========================================================================
    // DATABASE REFERENCE HELPERS (identik dengan JurnalSheet)
    // =========================================================================

    private function getIdKayuByNama(string $jenisKayu): ?int
    {
        $jenisUntukRef = in_array(strtolower(trim($jenisKayu)), ['sengon', 'meranti'])
            ? $jenisKayu
            : 'meranti';

        $key = strtolower($jenisUntukRef);
        if (! array_key_exists($key, $this->kayuCache)) {
            try {
                $kayu = JenisKayu::whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$key}%"])->first();
                $this->kayuCache[$key] = $kayu?->id;
            } catch (\Throwable $e) {
                $this->kayuCache[$key] = null;
            }
        }

        return $this->kayuCache[$key];
    }

    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (! array_key_exists($key, $this->kategoriCache)) {
            try {
                $kategori = KategoriBarang::whereRaw('LOWER(nama_kategori) LIKE ?', ["%{$key}%"])->first();
                $this->kategoriCache[$key] = $kategori?->id;
            } catch (\Throwable $e) {
                $this->kategoriCache[$key] = null;
            }
        }

        return $this->kategoriCache[$key];
    }

    private function getIdUkuran(float $p, float $l, float $t): ?int
    {
        $key = "{$p}_{$l}_{$t}";
        if (! array_key_exists($key, $this->ukuranCache)) {
            try {
                $ukuran = Ukuran::where('panjang', $p)
                    ->where('lebar', $l)
                    ->where('tebal', $t)
                    ->first();
                $this->ukuranCache[$key] = $ukuran?->id;
            } catch (\Throwable $e) {
                $this->ukuranCache[$key] = null;
            }
        }

        return $this->ukuranCache[$key];
    }

    private function fetchReferensi(
        string $jenisKayu,
        float $tebal,
        string $jenisBarang,
        ?int $kw = null,
        ?array $ukuranDim = null
    ): ?ReferensiHargaProduksi {
        $cacheKey = strtolower("{$jenisKayu}_{$tebal}_{$jenisBarang}_{$kw}");
        if (array_key_exists($cacheKey, $this->refCache)) {
            return $this->refCache[$cacheKey];
        }

        $idJenisKayu = $this->getIdKayuByNama($jenisKayu);
        $idKategoriBarang = $this->getIdKategoriBarang($jenisBarang);

        $idUkuran = null;
        if ($ukuranDim !== null) {
            $idUkuran = $this->getIdUkuran(
                $ukuranDim['p'] ?? 0,
                $ukuranDim['l'] ?? 0,
                $ukuranDim['t'] ?? 0,
            );
        }

        $result = ReferensiHargaProduksi::findReferensi(
            idJenisKayu      : $idJenisKayu,
            idKategoriBarang : $idKategoriBarang,
            kw               : $kw,
            tebal            : $tebal,
            idUkuran         : $idUkuran,
        );

        return $this->refCache[$cacheKey] = $result;
    }

    private function extractAkun(?ReferensiHargaProduksi $ref): array
    {
        if (! $ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }
        if (! $ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }
        $sub = $ref->subAnakAkun;
        if (! $sub) {
            return ['UNKNOWN', 'UNKNOWN', (float) $ref->harga];
        }
        $namaAkunLama = trim($sub->nama_sub_anak_akun ?? '');
        $noAkunLama = trim($sub->kode_sub_anak_akun ?? '');

        if ($namaAkunLama === '') {
            $namaAkunLama = 'UNKNOWN';
        }
        if ($noAkunLama === '') {
            $noAkunLama = 'UNKNOWN';
        }

        // Harga TETAP dari DB (findReferensi), tidak diubah.
        // Nomor & nama akun di-ALIAS ke COA baru berdasarkan pola nama akun lama,
        // bukan hardcode statis tanpa hubungan ke data referensi.
        [$namaAkunBaru, $noAkunBaru] = $this->aliasAkunBaru($namaAkunLama);

        return [$namaAkunBaru, $noAkunBaru, (float) $ref->harga];
    }

    /**
     * Alias nama & nomor akun lama (dari sub_anak_akun) ke COA baru.
     * Sengon/meranti digabung -> hanya dibedakan:
     *   jadi / kering / basah / afalan (ppc)  x  face-back / core
     *
     * Nomor akun boleh 1 digit (mis. 1402.5) ATAU 2 digit (mis. 1402.16)
     * di belakang titik — tidak dipaksa seragam. Kolom "No Akun" di
     * styles() diformat sebagai TEKS supaya Excel tidak menambah/mengubah
     * jumlah digit di belakang titik.
     *
     * Kalau nama akun lama tidak mengandung pola yang dikenali,
     * kembalikan UNKNOWN apa adanya (tidak dipaksa nebak) supaya
     * gampang terlihat di sheet kalau ada kombinasi baru yang belum
     * dipetakan.
     */
    private function aliasAkunBaru(string $namaAkunLama): array
    {
        if ($namaAkunLama === 'UNKNOWN') {
            return ['UNKNOWN', 'UNKNOWN'];
        }

        $n = strtolower($namaAkunLama);
        $isCore = str_contains($n, 'core');
        $isAf = str_contains($n, 'af') || str_contains($n, 'afalan') || str_contains($n, 'ppc');

        if (str_contains($n, 'basah')) {
            if ($isAf) {
                return ['Persediaan Veneer Basah PPC', '1402.16'];
            }

            return $isCore
                ? ['Persediaan Veneer Basah Core', '1402.4']
                : ['Persediaan Veneer Basah Face Back', '1402.3'];
        }

        if (str_contains($n, 'kering')) {
            return $isCore
                ? ['Persediaan Veneer Kering Core', '1402.6']
                : ['Persediaan Veneer Kering Face Back', '1402.5'];
        }

        if (str_contains($n, 'jadi') || $isAf) {
            if ($isAf) {
                return ['Persediaan Veneer Jadi PPC', '1402.18'];
            }

            return $isCore
                ? ['Persediaan Veneer Jadi Core', '1402.8']
                : ['Persediaan Veneer Jadi Face Back', '1402.7'];
        }

        // Pola tidak dikenali -> jangan dipaksa nebak
        return ['UNKNOWN', 'UNKNOWN'];
    }

    // =========================================================================
    // FORMATTING HELPERS (identik dengan JurnalSheet)
    // =========================================================================

    private function expandJenis(string $jenis): string
    {
        $map = [
            's' => 'sengon',
            'j' => 'jabon',
            'm' => 'meranti',
            'p' => 'pinus',
            'k' => 'keruing',
            'mh' => 'mahoni',
            'wr' => 'waru',
        ];
        $jns = strtolower(trim($jenis));

        return $map[$jns] ?? $jns;
    }

    private function isKwAf(mixed $kw): bool
    {
        return ! in_array((int) $kw, [1, 2, 3, 4]);
    }

    private function hitungM3(Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $p = (float) ($item['ukuran']['p'] ?? $item['ukuran']['panjang'] ?? 0);
            $l = (float) ($item['ukuran']['l'] ?? $item['ukuran']['lebar'] ?? 0);
            $t = (float) ($item['ukuran']['t'] ?? $item['ukuran']['tebal'] ?? 0);
            $jumlah = (int) ($item['isi'] ?? 0);
            $total += ($p * $l * $t * $jumlah) / 10_000_000;
        }

        return $total;
    }

    private function formatUkuran(array $ukuran): string
    {
        $p = $ukuran['p'] ?? ($ukuran['panjang'] ?? '');
        $l = $ukuran['l'] ?? ($ukuran['lebar'] ?? '');
        $t = $ukuran['t'] ?? ($ukuran['tebal'] ?? '');

        return "{$p} x {$l} x {$t}";
    }

    private function makeRow(string $namaAkun, string $noAkun, string $tgl, string $namaProduksi, string $keterangan, string $map, string $hitKbk, $banyak, $m3, $harga, $total): array
    {
        return [$namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $keterangan, $map, $hitKbk, $banyak, $m3, $harga, $total];
    }

    // =========================================================================
    // MAIN ARRAY (identik dengan JurnalSheet, kecuali 2 akun di bawah)
    // =========================================================================

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        $groupedByShift = collect($this->dataProduksi)->groupBy(function ($item) {
            return strtoupper($item['shift'] ?? 'PAGI');
        });

        foreach (['PAGI', 'MALAM'] as $shiftName) {
            $shiftData = $groupedByShift->get($shiftName, collect());
            if ($shiftData->isEmpty()) {
                continue;
            }

            $totalPegawai = 0;
            $allHasils = [];
            $allMasuks = [];
            $tglProduksi = '';

            foreach ($shiftData as $produksi) {
                $totalPegawai += $produksi['jumlah_pekerja'] ?? 0;
                foreach ($produksi['detail_hasils'] ?? [] as $dh) {
                    $allHasils[] = $dh;
                }
                foreach ($produksi['detail_masuks'] ?? [] as $dm) {
                    $allMasuks[] = $dm;
                }

                if (empty($tglProduksi)) {
                    $rawTgl = $produksi['tanggal_produksi'] ?? $produksi['tanggal'] ?? $produksi['tgl_produksi'] ?? $produksi['date'] ?? '';
                    if (! empty($rawTgl)) {
                        $rawTgl = str_replace('/', '-', $rawTgl);
                        try {
                            $tglProduksi = Carbon::parse($rawTgl)->format('d-m-Y');
                        } catch (\Exception $e) {
                            $tglProduksi = $rawTgl;
                        }
                    }
                }
            }

            $hasilsReguler = array_filter($allHasils, fn ($d) => ! $this->isKwAf($d['kw'] ?? 0));
            $hasilsAf = array_filter($allHasils, fn ($d) => $this->isKwAf($d['kw'] ?? 0));

            $makeKey = fn ($d) => $this->expandJenis(trim($d['jenis_kayu'] ?? ''))
                .'_'.(float) ($d['ukuran']['t'] ?? $d['ukuran']['tebal'] ?? 0);

            $groupedHasilsReguler = collect($hasilsReguler)->groupBy($makeKey);
            $groupedHasilsAf = collect($hasilsAf)->groupBy($makeKey);
            $groupedMasuks = collect($allMasuks)->groupBy($makeKey);

            $totalDebit = 0;
            $totalKredit = 0;
            $debitRows = [];
            $creditRows = [];
            $namaProduksi = 'dryer '.strtolower($shiftName);

            $allKeys = collect(array_keys($groupedMasuks->toArray()))
                ->merge(array_keys($groupedHasilsReguler->toArray()))
                ->merge(array_keys($groupedHasilsAf->toArray()))
                ->unique();

            foreach ($allKeys as $key) {
                $dhsReguler = $groupedHasilsReguler->get($key, collect());
                $dhsAf = $groupedHasilsAf->get($key, collect());
                $dms = $groupedMasuks->get($key, collect());

                $sample = $dhsReguler->first() ?? $dhsAf->first() ?? $dms->first();
                if (! $sample) {
                    continue;
                }

                $jenisAsli = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $ukuranArr = $sample['ukuran'] ?? [];
                $tebal = (float) ($ukuranArr['t'] ?? 0);
                $ukuranLengkap = $this->formatUkuran($ukuranArr);
                $tipeLabel = ($tebal < 1) ? '260 f/b' : '130 core';

                $dim = [
                    'p' => (float) ($ukuranArr['p'] ?? $ukuranArr['panjang'] ?? 0),
                    'l' => (float) ($ukuranArr['l'] ?? $ukuranArr['lebar'] ?? 0),
                    't' => $tebal,
                ];

                $refJadi = $this->fetchReferensi($jenisAsli, $tebal, 'veneer jadi', 1, $dim);
                $refKering = $this->fetchReferensi($jenisAsli, $tebal, 'veneer kering', 3, $dim);
                $refAf = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', null, $dim);
                $refBasah = $this->fetchReferensi($jenisAsli, $tebal, 'veneer basah', null, $dim);
                $refBasahAf = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', null, $dim);

                [$akunJadiNama,    $akunJadiNo,    $hargaJadi] = $this->extractAkun($refJadi);
                [$akunKeringNama,  $akunKeringNo,  $hargaKering] = $this->extractAkun($refKering);
                [$akunAfNama,      $akunAfNo,      $hargaAf] = $this->extractAkun($refAf);
                [$akunBasahNama,   $akunBasahNo,   $hargaBasah] = $this->extractAkun($refBasah);
                [$akunBasahAfNama, $akunBasahAfNo, $hargaBasahAf] = $this->extractAkun($refBasahAf);

                $ketJadi = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}".(! $refJadi ? ' [UNKNOWN]' : '');
                $ketKering = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}".(! $refKering ? ' [UNKNOWN]' : '');
                $ketAf = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af".(! $refAf ? ' [UNKNOWN]' : '');

                $ketBasah = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} basah".(! $refBasah ? ' [UNKNOWN]' : '');
                $ketBasahAf = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} basah af".(! $refBasahAf ? ' [UNKNOWN]' : '');

                $kwJadiItems = $dhsReguler->filter(fn ($d) => in_array((int) $d['kw'], [1, 2]));
                $kwKeringItems = $dhsReguler->filter(fn ($d) => in_array((int) $d['kw'], [3, 4]));
                $kwAfItems = collect($dhsAf);

                $jadiOutputIsi = $kwJadiItems->sum('isi');
                $keringOutputIsi = $kwKeringItems->sum('isi');
                $afOutputIsi = $kwAfItems->sum('isi');

                $totalHasilIsi = $jadiOutputIsi + $keringOutputIsi + $afOutputIsi;
                $totalMasukIsi = $dms->sum('isi');

                $m3JadiTotal = $this->hitungM3($kwJadiItems);
                $m3KeringTotal = $this->hitungM3($kwKeringItems);
                $m3AfTotal = $this->hitungM3($kwAfItems);
                $totalMasukM3 = $this->hitungM3($dms);

                $hilang = $totalMasukIsi - $totalHasilIsi;

                $regJadiIsi = $jadiOutputIsi;
                $regKeringIsi = $keringOutputIsi;
                $regAfIsi = $afOutputIsi;

                $m3Jadi = $m3JadiTotal;
                $m3Kering = $m3KeringTotal;
                $m3Af = $m3AfTotal;

                $kelebihanDebitRow = null;

                if ($hilang < 0) {
                    $kelebihan = abs($hilang);

                    if ($keringOutputIsi >= $jadiOutputIsi && $keringOutputIsi >= $afOutputIsi) {
                        $regKeringIsi = max(0, $keringOutputIsi - $kelebihan);
                        $m3Kering = $keringOutputIsi > 0 ? ($regKeringIsi / $keringOutputIsi) * $m3KeringTotal : 0;
                        $m3Kelebihan = $keringOutputIsi > 0 ? ($kelebihan / $keringOutputIsi) * $m3KeringTotal : 0;
                        $akunKelebihanNama = $akunKeringNama;
                        $akunKelebihanNo = $akunKeringNo;
                        $hargaKelebihan = $hargaKering;
                        $ketKelebihan = $ketKering." (kelebihan {$kelebihan})";
                        $m3KelebihanRnd = round($m3Kelebihan, 4);
                        $subtotalKel = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);

                    } elseif ($jadiOutputIsi >= $keringOutputIsi && $jadiOutputIsi >= $afOutputIsi) {
                        $regJadiIsi = max(0, $jadiOutputIsi - $kelebihan);
                        $m3Jadi = $jadiOutputIsi > 0 ? ($regJadiIsi / $jadiOutputIsi) * $m3JadiTotal : 0;
                        $m3Kelebihan = $jadiOutputIsi > 0 ? ($kelebihan / $jadiOutputIsi) * $m3JadiTotal : 0;
                        $akunKelebihanNama = $akunJadiNama;
                        $akunKelebihanNo = $akunJadiNo;
                        $hargaKelebihan = $hargaJadi;
                        $ketKelebihan = $ketJadi." (kelebihan {$kelebihan})";
                        $m3KelebihanRnd = round($m3Kelebihan, 4);
                        $subtotalKel = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);

                    } else {
                        $regAfIsi = max(0, $afOutputIsi - $kelebihan);
                        $m3Af = $afOutputIsi > 0 ? ($regAfIsi / $afOutputIsi) * $m3AfTotal : 0;
                        $m3Kelebihan = $afOutputIsi > 0 ? ($kelebihan / $afOutputIsi) * $m3AfTotal : 0;
                        $akunKelebihanNama = $akunAfNama;
                        $akunKelebihanNo = $akunAfNo;
                        $hargaKelebihan = $hargaAf;
                        $ketKelebihan = $ketAf." (kelebihan {$kelebihan})";
                        $m3KelebihanRnd = round($m3Kelebihan, 4);
                        $subtotalKel = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);
                    }
                }

                if ($regJadiIsi > 0) {
                    $m3JadiRnd = round($m3Jadi, 4);
                    $subtotal = round($m3JadiRnd * $hargaJadi, 0);
                    $debitRows[] = $this->makeRow($akunJadiNama, $akunJadiNo, $tglProduksi, $namaProduksi, $ketJadi, 'd', 'm', $regJadiIsi, $m3JadiRnd, $hargaJadi, $subtotal);
                    $totalDebit += $subtotal;
                }

                if ($regKeringIsi > 0) {
                    $m3KeringRnd = round($m3Kering, 4);
                    $subtotal = round($m3KeringRnd * $hargaKering, 0);
                    $debitRows[] = $this->makeRow($akunKeringNama, $akunKeringNo, $tglProduksi, $namaProduksi, $ketKering, 'd', 'm', $regKeringIsi, $m3KeringRnd, $hargaKering, $subtotal);
                    $totalDebit += $subtotal;
                }

                if ($regAfIsi > 0) {
                    $m3AfRnd = round($m3Af, 4);
                    $subtotal = round($m3AfRnd * $hargaAf, 0);
                    $debitRows[] = $this->makeRow($akunAfNama, $akunAfNo, $tglProduksi, $namaProduksi, $ketAf, 'd', 'm', $regAfIsi, $m3AfRnd, $hargaAf, $subtotal);
                    $totalDebit += $subtotal;
                }

                if ($kelebihanDebitRow) {
                    $debitRows[] = $kelebihanDebitRow;
                    $totalDebit += $kelebihanDebitRow[13];
                }

                if ($hilang >= 0) {
                    if ($jadiOutputIsi > 0 || $keringOutputIsi > 0) {
                        $m3Reguler = round($m3JadiTotal + $m3KeringTotal, 4);
                        $subtotal = round($m3Reguler * $hargaBasah, 0);
                        $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah, 'k', 'm', ($jadiOutputIsi + $keringOutputIsi), $m3Reguler, $hargaBasah, $subtotal);
                        $totalKredit += $subtotal;
                    }

                    if ($afOutputIsi > 0) {
                        $m3AfRound = round($m3AfTotal, 4);
                        $subtotal = round($m3AfRound * $hargaBasahAf, 0);
                        $creditRows[] = $this->makeRow($akunBasahAfNama, $akunBasahAfNo, $tglProduksi, $namaProduksi, $ketBasahAf, 'k', 'm', $afOutputIsi, $m3AfRound, $hargaBasahAf, $subtotal);
                        $totalKredit += $subtotal;
                    }

                    if ($hilang > 0) {
                        $m3Hilang = round($totalMasukM3 - ($m3JadiTotal + $m3KeringTotal + $m3AfTotal), 4);
                        if ($m3Hilang < 0) {
                            $m3Hilang = 0;
                        }
                        $subtotalHilang = round($m3Hilang * $hargaBasah, 0);
                        $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah.' kehilangan '.$hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                        $totalKredit += $subtotalHilang;
                    }
                } else {
                    if ($totalMasukIsi > 0) {
                        $m3MasukRnd = round($totalMasukM3, 4);
                        $subtotal = round($m3MasukRnd * $hargaBasah, 0);
                        $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah, 'k', 'm', $totalMasukIsi, $m3MasukRnd, $hargaBasah, $subtotal);
                        $totalKredit += $subtotal;
                    }
                }
            }

            foreach ($debitRows as $r) {
                $rows[] = $r;
            }
            foreach ($creditRows as $r) {
                $rows[] = $r;
            }

            // --- Hutang Gaji: 2231.00 -> 2195.1 ---
            if ($totalPegawai > 0) {
                $rows[] = $this->makeRow('Hutang Gaji', '2195.1', $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
                $totalKredit += ($totalPegawai * 150000);
            }

            // --- hpp/selisih: 6111.00 -> 5069.2 "Selisih harga patok produksi" ---
            $nilaiHpp = $totalKredit - $totalDebit;
            if (round(abs($nilaiHpp), 0) != 0) {
                $mapHpp = $nilaiHpp > 0 ? 'd' : 'k';
                $nominalHpp = round(abs($nilaiHpp), 0);
                $rows[] = $this->makeRow('Selisih harga patok produksi', '5069.2', $tglProduksi, $namaProduksi, '', $mapHpp, '', '', '', $nominalHpp, $nominalHpp);
            }

            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }

    public function map($row): array
    {
        $this->rowIndex++;

        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r = $this->rowIndex;
        $row[13] = "=ROUND(IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r})), 0)";

        return $row;
    }
}
