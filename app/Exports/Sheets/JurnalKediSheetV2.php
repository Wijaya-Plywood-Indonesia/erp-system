<?php

namespace App\Exports\Sheets;

use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\ReferensiHargaProduksi;
use App\Services\CoaAliasService;
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
// FILE INI TIDAK MENGUBAH Sheets\JurnalKediSheet.php SAMA SEKALI.
// Ini COPY dengan:
// - Nama class baru: JurnalKediSheetV2
// - Judul sheet baru: 'jurnal produksi v2'
// - Akun Hutang Gaji: 2231.00 -> 2195.1
// - Akun hpp/selisih: 6111.00 -> 5069.2 "Selisih harga patok produksi"
// - Akun veneer (jadi/kering/basah, face-back/core, PPC) di-ALIAS ke
//   COA baru lewat CoaAliasService::aliasAkunBaru() berdasarkan nama akun
//   lama dari DB (sub_anak_akun), sama persis polanya dengan
//   LaporanProduksiHotPressJurnalSheetV2 & JurnalRepairSheetV2.
// - Kolom "No Akun" dipaksa jadi string literal (AfterSheet event) supaya
//   Excel tidak mengubah titik desimal jadi koma sesuai locale, dan
//   tidak memaksa jumlah digit di belakang titik jadi seragam.
//
// NOTE REFACTOR: Logic ALIAS AKUN COA BARU (aliasAkunBaru, extractAkun ->
// jadi extractAkunVeneer, getAkunHpp, getAkunGaji) sudah DIPINDAH ke
// App\Services\CoaAliasService supaya bisa dipakai bersama oleh
// LaporanProduksiHotPressJurnalSheetV2 (hotpress) & JurnalRepairSheetV2
// (repair). Sheet ini sekarang hanya memanggil service tsb lewat
// $this->coaAlias.
//
// CATATAN PERILAKU: di versi lama file ini, cabang "kering" pada
// aliasAkunBaru() TIDAK mengecek pola AF/PPC (beda dengan cabang "basah"
// & "jadi" yang sudah mengecek). Setelah pindah ke CoaAliasService, semua
// cabang (basah/kering/jadi) konsisten mengecek AF/PPC, jadi kombinasi
// "Veneer Kering PPC" (1402.17) sekarang juga bisa muncul di sheet ini
// kalau nama akun lama di DB mengandung pola af/afalan/ppc — sebelumnya
// itu akan salah kena cabang reguler (1402.5/1402.6).
//
// Cara pakai: daftarkan class ini di sheets() milik export terkait
// TANPA menghapus sheet lama, misalnya:
//
//     public function sheets(): array
//     {
//         return [
//             ...
//             new Sheets\JurnalKediSheet($this->dataKedi),   // lama, tidak diubah
//             new Sheets\JurnalKediSheetV2($this->dataKedi), // baru
//         ];
//     }
// ============================================================
class JurnalKediSheetV2 implements FromArray, WithColumnWidths, WithEvents, WithMapping, WithStyles, WithTitle
{
    protected array $dataKedi;

    protected int $rowIndex = 0;

    protected CoaAliasService $coaAlias;

    // Cache agar tidak query DB berulang
    private array $refCache = [];

    private array $kayuCache = [];

    private array $kategoriCache = [];

    public function __construct($dataKedi, ?CoaAliasService $coaAlias = null)
    {
        $this->dataKedi = $dataKedi;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, 'B' => 20, 'C' => 15, 'D' => 12, 'E' => 8,
            'F' => 18, 'G' => 20, 'H' => 45, 'I' => 6,  'J' => 10,
            'K' => 10, 'L' => 15, 'M' => 15, 'N' => 15, 'O' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle("A1:O{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
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
        $sheet->getStyle("O2:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
    // DATABASE REFERENCE HELPERS (identik dengan JurnalKediSheet)
    // =========================================================================

    /**
     * Ambil id jenis kayu berdasarkan nama (dengan cache).
     * Jenis selain sengon & meranti → fallback ke meranti.
     */
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

    /**
     * Ambil id kategori barang berdasarkan nama (dengan cache).
     * PENTING: kolom di tabel kategori_barang adalah `nama_kategori`, bukan `nama`.
     * Contoh: 'veneer jadi', 'veneer kering', 'veneer basah', 'veneer afalan'
     */
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

    /**
     * Ambil referensi harga dari DB menggunakan findReferensi() di model.
     *
     * Mapping kw integer yang dikirim:
     *   veneer jadi   → kw = 1  (representatif, cocok ke range kw_min=1, kw_max=2)
     *   veneer kering → kw = 3  (representatif, cocok ke range kw_min=3, kw_max=4)
     *   veneer afalan → kw = null (kw_min/kw_max null di tabel, skip filter kw)
     *   veneer basah  → kw = null (cukup filter by kategori + jenis kayu + tebal)
     *
     * Pembeda 260 f/b vs 130 core otomatis via t_min/t_max di tabel.
     *
     * Guard penting: jika id kategori barang tidak ditemukan di DB,
     * langsung return null tanpa lanjut ke findReferensi(). Tanpa guard ini,
     * findReferensi() akan SKIP filter kategori (karena null dianggap "tidak
     * difilter") dan bisa salah ambil referensi dari kategori lain yang
     * kebetulan cocok di kw/tebal.
     */
    private function fetchReferensi(
        string $jenisKayu,
        float $tebal,
        string $jenisBarang,
        ?int $kw = null
    ): ?ReferensiHargaProduksi {
        $cacheKey = strtolower("{$jenisKayu}_{$tebal}_{$jenisBarang}_{$kw}");
        if (array_key_exists($cacheKey, $this->refCache)) {
            return $this->refCache[$cacheKey];
        }

        $idJenisKayu = $this->getIdKayuByNama($jenisKayu);
        $idKategoriBarang = $this->getIdKategoriBarang($jenisBarang);

        // Guard: kategori tidak ditemukan → jangan lanjut cari, supaya tidak
        // salah ambil referensi dari kategori lain.
        if ($idKategoriBarang === null) {
            return $this->refCache[$cacheKey] = null;
        }

        $result = ReferensiHargaProduksi::findReferensi(
            idJenisKayu      : $idJenisKayu,
            idKategoriBarang : $idKategoriBarang,
            kw               : $kw,     // null → findReferensi() skip filter kw_min/kw_max
            tebal            : $tebal,  // dicocokkan ke t_min <= tebal <= t_max
        );

        return $this->refCache[$cacheKey] = $result;
    }

    // =========================================================================
    // FORMATTING HELPERS (identik dengan JurnalKediSheet)
    // =========================================================================

    private function parseDimensi(string $ukuranStr): array
    {
        $dimensi = explode('x', str_replace([' ', 'mm', 'MM'], '', strtolower($ukuranStr)));

        return [
            'p' => (float) ($dimensi[0] ?? 0),
            'l' => (float) ($dimensi[1] ?? 0),
            't' => (float) ($dimensi[2] ?? 0),
        ];
    }

    private function expandJenis(string $jenis): string
    {
        $map = [
            's' => 'sengon', 'j' => 'jabon', 'm' => 'meranti',
            'p' => 'pinus', 'k' => 'keruing', 'mh' => 'mahoni', 'wr' => 'waru',
        ];

        return $map[strtolower(trim($jenis))] ?? strtolower(trim($jenis));
    }

    private function isKwAf(mixed $kw): bool
    {
        return ! in_array((int) $kw, [1, 2, 3, 4]);
    }

    private function hitungM3(Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $dim = $this->parseDimensi($item['ukuran'] ?? '');
            $jumlah = (int) ($item['jumlah'] ?? 0);
            $total += ($dim['p'] * $dim['l'] * $dim['t'] * $jumlah) / 10_000_000;
        }

        return $total;
    }

    private function resolveIdBarang(string $jenisVeneer, string $jenisKayu, float $tebal, string $ukuran, ?int $kw = null, bool $isAf = false): ?int
    {
        $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/') . '/api/barang/resolve-veneer';
        $bagianParam = $isAf ? 'PPC' : ($tebal < 1 ? 'Face Back' : 'Core');
        
        // Extract only panjang and lebar (e.g. from "260 x 130 x 1.5" to "260x130")
        $dim = array_map('trim', explode('x', strtolower($ukuran)));
        $ukuranApi = (isset($dim[0]) ? $dim[0] : '') . 'x' . (isset($dim[1]) ? $dim[1] : '');

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)->get($urlApi, [
                'jenis_veneer' => $jenisVeneer,
                'bagian' => $bagianParam,
                'jenis_kayu' => $jenisKayu,
                'ketebalan' => $tebal,
                'ukuran' => $ukuranApi,
                'kw' => $kw,
            ]);
            return $response->json('id_barang');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatUkuran(array $dim): string
    {
        return "{$dim['p']} x {$dim['l']} x {$dim['t']}";
    }

    private function makeRow(
        string $namaAkun, string $noAkun, string $tgl, string $namaProduksi,
        string $keterangan, string $map, string $hitKbk,
        $banyak, $m3, $harga, $total, $idBarang = null
    ): array {
        return [$namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $keterangan, $map, $hitKbk, $banyak, $m3, $harga, $total, $idBarang];
    }

    // =========================================================================
    // MAIN ARRAY (identik dengan JurnalKediSheet, kecuali akun & aliasing)
    // =========================================================================

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang'];

        if (empty($this->dataKedi)) {
            return $rows;
        }

        $totalPegawai = 0;
        $allBongkars = [];
        $allMasuks = [];
        $tglProduksi = '';

        foreach ($this->dataKedi as $produksi) {
            $totalPegawai += $produksi['total_pekerja'] ?? 0;

            if (empty($tglProduksi)) {
                $rawTgl = str_replace('/', '-',
                    $produksi['tanggal_actual_bongkar']
                        ?? $produksi['tanggal_keluar']
                        ?? $produksi['tanggal_masuk']
                        ?? ''
                );
                try {
                    $tglProduksi = Carbon::createFromFormat('d/m/Y', $rawTgl)->format('d-m-Y');
                } catch (\Exception $e) {
                    $tglProduksi = $rawTgl;
                }
            }

            foreach ($produksi['detail_bongkar'] ?? [] as $db) {
                $allBongkars[] = $db;
            }
            foreach ($produksi['detail_masuk'] ?? [] as $dm) {
                $allMasuks[] = $dm;
            }
        }

        $namaProduksi = 'bongkar';

        $bongkarsReguler = array_filter($allBongkars, fn ($d) => ! $this->isKwAf($d['kw'] ?? 0));
        $bongkarsAf = array_filter($allBongkars, fn ($d) => $this->isKwAf($d['kw'] ?? 0));

        $makeKey = function ($d) {
            $dim = $this->parseDimensi($d['ukuran'] ?? '');

            return $this->expandJenis(trim($d['jenis_kayu'] ?? ''))
                .'_'.$dim['p']
                .'_'.$dim['l']
                .'_'.$dim['t'];
        };

        $groupedBongkarsReguler = collect($bongkarsReguler)->groupBy($makeKey);
        $groupedBongkarsAf = collect($bongkarsAf)->groupBy($makeKey);
        $groupedMasuks = collect($allMasuks)->groupBy($makeKey);

        $totalDebit = 0;
        $totalKredit = 0;
        $debitRows = [];
        $creditRows = [];

        $allKeys = collect(array_keys($groupedMasuks->toArray()))
            ->merge(array_keys($groupedBongkarsReguler->toArray()))
            ->merge(array_keys($groupedBongkarsAf->toArray()))
            ->unique();

        foreach ($allKeys as $key) {
            $dbsReguler = $groupedBongkarsReguler->get($key, collect());
            $dbsAf = $groupedBongkarsAf->get($key, collect());
            $dms = $groupedMasuks->get($key, collect());

            $sample = $dbsReguler->first() ?? $dbsAf->first() ?? $dms->first();
            if (! $sample) {
                continue;
            }

            $jenisAsli = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
            $dim = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal = $dim['t'];
            $ukuranLengkap = $this->formatUkuran($dim);
            $tipeLabel = ($tebal < 1) ? '260 f/b' : '130 core';

            // ── Ambil referensi dari DB ───────────────────────────────────────
            // kw integer dikirim sebagai representatif range:
            //   jadi   → kw=1 (cocok ke kw_min=1, kw_max=2 di tabel)
            //   kering → kw=3 (cocok ke kw_min=3, kw_max=4 di tabel)
            //   afalan → null (kw_min/kw_max null di tabel, skip filter)
            //   basah  → null (cukup filter kategori + jenis kayu + tebal)
            // Pembeda 260 f/b vs 130 core otomatis via t_min/t_max di tabel
            $refJadi = $this->fetchReferensi($jenisAsli, $tebal, 'veneer jadi', 1);
            $refKering = $this->fetchReferensi($jenisAsli, $tebal, 'veneer kering', 3);
            $refAf = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan');
            $refBasah = $this->fetchReferensi($jenisAsli, $tebal, 'veneer basah');
            $refBasahAf = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan');

            [$akunJadiNama,    $akunJadiNo,    $hargaJadi] = $this->coaAlias->extractAkunVeneer($refJadi);
            [$akunKeringNama,  $akunKeringNo,  $hargaKering] = $this->coaAlias->extractAkunVeneer($refKering);
            [$akunAfNama,      $akunAfNo,      $hargaAf] = $this->coaAlias->extractAkunVeneer($refAf);
            [$akunBasahNama,   $akunBasahNo,   $hargaBasah] = $this->coaAlias->extractAkunVeneer($refBasah);
            [$akunBasahAfNama, $akunBasahAfNo, $hargaBasahAf] = $this->coaAlias->extractAkunVeneer($refBasahAf);

            // Keterangan debit
            $ketJadi = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}".(! $refJadi ? ' [UNKNOWN]' : '');
            $ketKering = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}".(! $refKering ? ' [UNKNOWN]' : '');
            $ketAf = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af".(! $refAf ? ' [UNKNOWN]' : '');

            // ── Kelompokkan item per kw ───────────────────────────────────────
            $kwJadiItems = $dbsReguler->filter(fn ($d) => in_array((int) $d['kw'], [1, 2]));
            $kwKeringItems = $dbsReguler->filter(fn ($d) => in_array((int) $d['kw'], [3, 4]));
            $kwAfItems = collect($dbsAf);

            $jadiOutputIsi = $kwJadiItems->sum('jumlah');
            $keringOutputIsi = $kwKeringItems->sum('jumlah');
            $afOutputIsi = $kwAfItems->sum('jumlah');

            $totalHasilIsi = $jadiOutputIsi + $keringOutputIsi + $afOutputIsi;
            $totalMasukIsi = $dms->sum('jumlah');

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

            // ── Penanganan kelebihan output ───────────────────────────────────
            if ($hilang < 0) {
                $kelebihan = abs($hilang);

                if ($keringOutputIsi >= $jadiOutputIsi && $keringOutputIsi >= $afOutputIsi) {
                    $regKeringIsi = max(0, $keringOutputIsi - $kelebihan);
                    $m3Kering = $keringOutputIsi > 0 ? ($regKeringIsi / $keringOutputIsi) * $m3KeringTotal : 0;
                    $m3Kelebihan = $keringOutputIsi > 0 ? ($kelebihan / $keringOutputIsi) * $m3KeringTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel = round($m3KelebihanRnd * $hargaKering, 0);
                    $idBarangKel = $this->resolveIdBarang('Veneer Kering', $jenisAsli, $tebal, $ukuranLengkap, 3, false);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunKeringNama, $akunKeringNo, $tglProduksi, $namaProduksi,
                        $ketKering." (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaKering, $subtotalKel, $idBarangKel
                    );

                } elseif ($jadiOutputIsi >= $keringOutputIsi && $jadiOutputIsi >= $afOutputIsi) {
                    $regJadiIsi = max(0, $jadiOutputIsi - $kelebihan);
                    $m3Jadi = $jadiOutputIsi > 0 ? ($regJadiIsi / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $m3Kelebihan = $jadiOutputIsi > 0 ? ($kelebihan / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel = round($m3KelebihanRnd * $hargaJadi, 0);
                    $idBarangKel = $this->resolveIdBarang('Veneer Jadi', $jenisAsli, $tebal, $ukuranLengkap, 1, false);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunJadiNama, $akunJadiNo, $tglProduksi, $namaProduksi,
                        $ketJadi." (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaJadi, $subtotalKel, $idBarangKel
                    );

                } else {
                    $regAfIsi = max(0, $afOutputIsi - $kelebihan);
                    $m3Af = $afOutputIsi > 0 ? ($regAfIsi / $afOutputIsi) * $m3AfTotal : 0;
                    $m3Kelebihan = $afOutputIsi > 0 ? ($kelebihan / $afOutputIsi) * $m3AfTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel = round($m3KelebihanRnd * $hargaAf, 0);
                    $idBarangKel = $this->resolveIdBarang('Veneer Afalan', $jenisAsli, $tebal, $ukuranLengkap, null, true);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunAfNama, $akunAfNo, $tglProduksi, $namaProduksi,
                        $ketAf." (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaAf, $subtotalKel, $idBarangKel
                    );
                }
            }

            // ── DEBIT ─────────────────────────────────────────────────────────
            if ($regJadiIsi > 0) {
                $idBarang = $this->resolveIdBarang('Veneer Jadi', $jenisAsli, $tebal, $ukuranLengkap, 1, false);
                $m3JadiRnd = round($m3Jadi, 4);
                $subtotal = round($m3JadiRnd * $hargaJadi, 0);
                $debitRows[] = $this->makeRow($akunJadiNama, $akunJadiNo, $tglProduksi, $namaProduksi, $ketJadi, 'd', 'm', $regJadiIsi, $m3JadiRnd, $hargaJadi, $subtotal, $idBarang);
                $totalDebit += $subtotal;
            }

            if ($regKeringIsi > 0) {
                $idBarang = $this->resolveIdBarang('Veneer Kering', $jenisAsli, $tebal, $ukuranLengkap, 3, false);
                $m3KeringRnd = round($m3Kering, 4);
                $subtotal = round($m3KeringRnd * $hargaKering, 0);
                $debitRows[] = $this->makeRow($akunKeringNama, $akunKeringNo, $tglProduksi, $namaProduksi, $ketKering, 'd', 'm', $regKeringIsi, $m3KeringRnd, $hargaKering, $subtotal, $idBarang);
                $totalDebit += $subtotal;
            }

            if ($regAfIsi > 0) {
                $idBarang = $this->resolveIdBarang('Veneer Afalan', $jenisAsli, $tebal, $ukuranLengkap, null, true);
                $m3AfRnd = round($m3Af, 4);
                $subtotal = round($m3AfRnd * $hargaAf, 0);
                $debitRows[] = $this->makeRow($akunAfNama, $akunAfNo, $tglProduksi, $namaProduksi, $ketAf, 'd', 'm', $regAfIsi, $m3AfRnd, $hargaAf, $subtotal, $idBarang);
                $totalDebit += $subtotal;
            }

            if ($kelebihanDebitRow) {
                $debitRows[] = $kelebihanDebitRow;
                $totalDebit += $kelebihanDebitRow[13];
            }

            // ── KREDIT ────────────────────────────────────────────────────────
            if ($hilang >= 0) {
                if ($jadiOutputIsi > 0 || $keringOutputIsi > 0) {
                    $idBarang = $this->resolveIdBarang('Veneer Basah', $jenisAsli, $tebal, $ukuranLengkap, null, false);
                    $m3Reguler = round($m3JadiTotal + $m3KeringTotal, 4);
                    $subtotal = round($m3Reguler * $hargaBasah, 0);
                    $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, '', 'k', 'm', ($jadiOutputIsi + $keringOutputIsi), $m3Reguler, $hargaBasah, $subtotal, $idBarang);
                    $totalKredit += $subtotal;
                }

                if ($afOutputIsi > 0) {
                    $idBarang = $this->resolveIdBarang('Veneer Afalan', $jenisAsli, $tebal, $ukuranLengkap, null, true);
                    $m3AfRound = round($m3AfTotal, 4);
                    $subtotal = round($m3AfRound * $hargaBasahAf, 0);
                    $creditRows[] = $this->makeRow($akunBasahAfNama, $akunBasahAfNo, $tglProduksi, $namaProduksi, 'af', 'k', 'm', $afOutputIsi, $m3AfRound, $hargaBasahAf, $subtotal, $idBarang);
                    $totalKredit += $subtotal;
                }

                if ($hilang > 0) {
                    $idBarang = $this->resolveIdBarang('Veneer Basah', $jenisAsli, $tebal, $ukuranLengkap, null, false);
                    $m3Hilang = round($totalMasukM3 - ($m3JadiTotal + $m3KeringTotal + $m3AfTotal), 4);
                    if ($m3Hilang < 0) {
                        $m3Hilang = 0;
                    }
                    $subtotalHilang = round($m3Hilang * $hargaBasah, 0);
                    $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, 'kehilangan '.$hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang, $idBarang);
                    $totalKredit += $subtotalHilang;
                }
            } else {
                if ($totalMasukIsi > 0) {
                    $idBarang = $this->resolveIdBarang('Veneer Basah', $jenisAsli, $tebal, $ukuranLengkap, null, false);
                    $m3MasukRnd = round($totalMasukM3, 4);
                    $subtotal = round($m3MasukRnd * $hargaBasah, 0);
                    $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, '', 'k', 'm', $totalMasukIsi, $m3MasukRnd, $hargaBasah, $subtotal, $idBarang);
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
            $akunGaji = $this->coaAlias->getAkunGaji(false);
            $rows[] = $this->makeRow($akunGaji['hutang']['nama'], $akunGaji['hutang']['no'], $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
            $totalKredit += ($totalPegawai * 150000);
        }

        // --- hpp/selisih: 6111.00 -> 5069.2 "Selisih harga patok produksi" ---
        $nilaiHpp = $totalKredit - $totalDebit;
        if (round(abs($nilaiHpp), 0) != 0) {
            $hpp = $this->coaAlias->getAkunHpp();
            $mapHpp = $nilaiHpp > 0 ? 'd' : 'k';
            $nominalHpp = round(abs($nilaiHpp), 0);
            $rows[] = $this->makeRow($hpp['nama'], $hpp['no'], $tglProduksi, $namaProduksi, '', $mapHpp, '', '', '', $nominalHpp, $nominalHpp);
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

