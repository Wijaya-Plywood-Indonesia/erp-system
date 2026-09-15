<?php

namespace App\Exports\Sheets;

use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\ReferensiHargaProduksi;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
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
// FILE INI TIDAK MENGUBAH JurnalSheet (di LaporanRepairExport.php)
// SAMA SEKALI. Ini COPY dengan:
// - Nama class: JurnalRepairSheetV2
// - Judul sheet baru: 'jurnal repair v2'
// - Akun Hutang Gaji: 2231.00 -> 2195.1
// - Akun hpp/selisih: 6111.00 "hpp triplek" -> 5069.2 "Selisih harga patok produksi"
// - Akun veneer (jadi/kering, face-back/core, ppc) di-alias dinamis ke
//   COA baru lewat aliasAkunBaru(), berdasarkan nama_sub_anak_akun dari DB
//   (bukan hardcode statis tanpa hubungan ke data referensi).
// - Akun bahan penolong TIDAK diubah (tetap ambil langsung dari DB),
//   karena tidak ada pola jadi/kering/basah/core/face-back untuk itu.
// - Kolom "No Akun" (D) TIDAK diformat numerik. Kalau diformat numerik
//   (mis. '0.00'), Excel akan memaksa semua kode akun tampil 2 digit di
//   belakang titik dan pakai koma sebagai pemisah desimal (locale ID),
//   sehingga "1402.5" jadi "1402,50". Kolom D sekarang diformat TEKS,
//   dan nilainya dipaksa jadi string eksplisit lewat registerEvents()
//   supaya titik tetap titik dan jumlah digit di belakang titik apa
//   adanya (1 digit / 2 digit, tidak dipaksa seragam).
//
// Cara pakai: daftarkan class ini di sheets() milik LaporanRepairExport
// TANPA menghapus sheet lama, misalnya:
//
//     public function sheets(): array
//     {
//         return [
//             new LaporanRepairDetailSheet($this->detailData, $this->tanggal),
//             new LaporanRepairSummarySheet($rawCollection),
//             new JurnalSheet($rawCollection),          // lama, tidak diubah
//             new JurnalRepairSheetV2($rawCollection),  // baru
//         ];
//     }
// ============================================================
class JurnalRepairSheetV2 implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected $rawCollection) {}

    private array $kayuCache = [];

    private array $kategoriCache = [];

    private array $bahanRefCache = [];

    public function title(): string
    {
        return 'jurnal repair v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 8,
            'F' => 8,
            'G' => 15,
            'H' => 45,
            'I' => 8,
            'J' => 8,
            'K' => 14,
            'L' => 16,
            'M' => 16,
            'N' => 22,
        ];
    }

    public function columnFormats(): array
    {
        return [
            // 'D' sengaja TIDAK diberi format numerik di sini — diatur
            // sebagai TEKS di styles() supaya kode akun (mis. "1402.5")
            // tidak dipaksa 2 digit desimal / diubah titik jadi koma.
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D4F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Kolom D (No Akun) diformat sebagai TEKS, bukan numerik.
            $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($row = 2; $row <= $lastRow; $row++) {
                $namaAkunVal = $sheet->getCell("A{$row}")->getValue();
                if ($namaAkunVal !== '' && $namaAkunVal !== null) {
                    $sheet->getCell("N{$row}")->setValue(
                        "=IF(J{$row}=\"m\",M{$row}*L{$row},IF(J{$row}=\"b\",M{$row}*K{$row},M{$row}))"
                    );
                }
            }
        }
    }

    /**
     * Paksa kolom "No Akun" (D) disimpan sebagai STRING eksplisit di level
     * cell, bukan cuma diformat tampilan sebagai teks. Ini perlu karena
     * PhpSpreadsheet otomatis mendeteksi nilai seperti "1402.5" sebagai
     * angka (float) saat FromArray menulisnya. Kalau dibiarkan jadi angka,
     * Excel akan menampilkannya memakai koma sebagai pemisah desimal pada
     * locale Indonesia (mis. "1402,50"), walaupun format cell-nya "Text".
     * Dengan setCellValueExplicit(..., TYPE_STRING), nilainya benar-benar
     * jadi teks "1402.5" apa adanya, titik tidak pernah berubah jadi koma,
     * dan jumlah digit di belakang titik tidak dipaksa seragam (1 digit
     * atau 2 digit sama-sama tampil apa adanya).
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

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function buildKeterangan(
        float $panjang,
        float $lebar,
        float $tebal,
        string $jenis,
        string $statusKw,
        string $kwRaw = '',
        string $prefix = ''
    ): string {
        $fmt = function (float $val): string {
            if ($val == (int) $val) {
                return (string) (int) $val;
            }

            return str_replace('.', ',', rtrim(number_format($val, 4, '.', ''), '0'));
        };

        $p = $fmt($panjang);
        $l = $fmt($lebar);
        $t = $fmt($tebal);
        $jns = ucfirst(strtolower($this->normalizeJenis($jenis)));
        $kw = $kwRaw !== '' ? " KW{$kwRaw}" : '';
        $af = $statusKw === 'af' ? ' AF' : '';
        $pfx = $prefix !== '' ? "{$prefix} " : '';

        return "{$pfx}{$p}x{$l}x{$t} {$jns}{$kw}{$af}";
    }

    private function getIdKayuByNama(string $jenis): ?int
    {
        $jns = $this->normalizeJenis($jenis) === 'sengon' ? 'Sengon' : 'Meranti';
        $key = strtolower($jns);
        if (! array_key_exists($key, $this->kayuCache)) {
            $kayu = JenisKayu::where('nama_kayu', $jns)->first();
            $this->kayuCache[$key] = $kayu?->id;
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

    private function fetchReferensiVeneer(string $jenis, float $tebal, bool $isAf, string $status): ?ReferensiHargaProduksi
    {
        $idJenisKayu = $this->getIdKayuByNama($jenis);

        if ($isAf) {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer afalan');
            $kw = null;
        } elseif ($status === 'jadi') {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer jadi');
            $kw = 1;
        } else {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer kering');
            $kw = 3;
        }

        if (! $idJenisKayu || ! $idKategoriBarang) {
            return null;
        }

        return ReferensiHargaProduksi::findReferensi(
            idJenisKayu: $idJenisKayu,
            idKategoriBarang: $idKategoriBarang,
            kw: $kw,
            tebal: $tebal,
        );
    }

    /**
     * Sama seperti extractAkunVeneer di JurnalSheet lama, tapi nomor & nama
     * akun hasilnya di-alias ke COA baru lewat aliasAkunBaru() — harga
     * tetap dari DB (findReferensi), tidak diubah.
     */
    private function extractAkunVeneer(?ReferensiHargaProduksi $ref): array
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
        $namaAkunLama = trim($sub->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noAkunLama = trim($sub->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';

        [$namaAkunBaru, $noAkunBaru] = $this->aliasAkunBaru($namaAkunLama);

        return [$namaAkunBaru, $noAkunBaru, (float) $ref->harga];
    }

    /**
     * Alias nama & nomor akun lama (dari sub_anak_akun) ke COA baru.
     * Identik dengan aliasAkunBaru() di JurnalSheetV2 (dryer export),
     * supaya kode akun veneer konsisten di semua sheet jurnal v2.
     *
     * Nomor akun boleh 1 digit (mis. 1402.5) ATAU 2 digit (mis. 1402.16)
     * di belakang titik — tidak dipaksa seragam, karena kolom D
     * disimpan sebagai TEKS (lihat styles() & registerEvents()).
     *
     * Kalau nama akun lama tidak mengandung pola yang dikenali,
     * kembalikan UNKNOWN apa adanya (tidak dipaksa nebak).
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
            if ($isAf) {
                return ['Persediaan Veneer Kering PPC', '1402.17'];
            }

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

    private function fetchReferensiBahan(string $namaBahan): ?ReferensiHargaProduksi
    {
        $key = strtolower(trim($namaBahan));
        if (array_key_exists($key, $this->bahanRefCache)) {
            return $this->bahanRefCache[$key];
        }

        $idKategoriBarang = $this->getIdKategoriBarang('barang');
        if (! $idKategoriBarang) {
            return $this->bahanRefCache[$key] = null;
        }

        $ref = ReferensiHargaProduksi::with('subAnakAkun')
            ->where('id_kategori_barang', $idKategoriBarang)
            ->where(function ($q) use ($key) {
                $q->whereRaw('LOWER(nama) LIKE ?', ["%{$key}%"])
                    ->orWhereHas('subAnakAkun', function ($q2) use ($key) {
                        $q2->whereRaw('LOWER(nama_sub_anak_akun) LIKE ?', ["%{$key}%"]);
                    });
            })
            ->first();

        return $this->bahanRefCache[$key] = $ref;
    }

    /**
     * Akun bahan penolong TIDAK di-alias — dipakai apa adanya dari DB,
     * karena kategori ini (lem, hardner, dll: 1402.9–1402.15) tidak punya
     * pola jadi/kering/basah/core/face-back untuk dipetakan.
     */
    private function extractAkunBahan(?ReferensiHargaProduksi $ref): array
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
        $nama = trim($sub->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noAkun = trim($sub->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';

        return [$nama, $noAkun, (float) $ref->harga];
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,
            (string) $tgl,
            '',
            (string) $noAkun,
            '',
            '',
            'tembel',
            $keterangan,
            strtolower($map),
            ($hitKbk !== '' && $hitKbk !== null) ? strtolower($hitKbk) : '',
            ($banyak === '' || $banyak === null) ? '' : (float) $banyak,
            ($m3 === '' || $m3 === null) ? '' : (float) $m3,
            ($harga === '' || $harga === null) ? '' : (float) $harga,
            '',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit = 0.0;
            $totalKredit = 0.0;
            $jurnalBlockDebit = [];
            $jurnalBlockKredit = [];

            // ============================================================
            // STEP A: Kumpulkan semua data HASIL per group
            // ============================================================
            $hasilPerGroup = [];

            $groupedHasil = collect($produksi->detailHasilRepairs)->groupBy(function ($hasil) {
                if ($hasil->modalRepair && $hasil->modalRepair->ukuran && $hasil->modalRepair->jenisKayu) {
                    $modal = $hasil->modalRepair;
                    $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                    $statusKw = strtolower((string) ($modal->kw ?? $hasil->kw));
                    $isAf = str_contains($statusKw, 'af') ? 'af' : 'reguler';
                    $tebal = (float) $modal->ukuran->tebal;
                    $panjang = (float) $modal->ukuran->panjang;
                    $lebar = (float) $modal->ukuran->lebar;
                    $kwRaw = (string) ((int) filter_var($statusKw, FILTER_SANITIZE_NUMBER_INT));

                    return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
                }

                if (! $hasil->ukuran) {
                    return 'invalid_data';
                }

                $jenisModel = $hasil->jenisKayu;
                $jnsNorm = $this->normalizeJenis($jenisModel?->nama_kayu ?? 'meranti');
                $statusKw = strtolower((string) $hasil->kw);
                $isAf = str_contains($statusKw, 'af') ? 'af' : 'reguler';
                $tebal = (float) $hasil->ukuran->tebal;
                $panjang = (float) $hasil->ukuran->panjang;
                $lebar = (float) $hasil->ukuran->lebar;
                $kwRaw = (string) ((int) filter_var($statusKw, FILTER_SANITIZE_NUMBER_INT));

                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });

            foreach ($groupedHasil as $key => $items) {
                if ($key === 'invalid_data') {
                    continue;
                }

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar = (float) $lebar;
                $tebal = (float) $tebal;
                $isAf = ($statusKw === 'af');

                $totalBanyak = $items->sum('jumlah');
                $totalM3 = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                $hasilPerGroup[$key] = [
                    'jnsNorm' => $jnsNorm,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'statusKw' => $statusKw,
                    'kwRaw' => $kwRaw,
                    'isAf' => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3' => $totalM3,
                ];
            }

            // ============================================================
            // STEP B: Kumpulkan semua data MODAL per group
            // ============================================================
            $modalPerGroup = [];

            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (! $modal->ukuran || ! $modal->jenisKayu) {
                    return 'invalid_data';
                }

                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $statusKw = strtolower((string) $modal->kw);
                $isAf = str_contains($statusKw, 'af') ? 'af' : 'reguler';
                $tebal = (float) $modal->ukuran->tebal;
                $panjang = (float) $modal->ukuran->panjang;
                $lebar = (float) $modal->ukuran->lebar;
                $kwRaw = (string) ((int) filter_var($statusKw, FILTER_SANITIZE_NUMBER_INT));

                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });

            foreach ($groupedModal as $key => $items) {
                if ($key === 'invalid_data') {
                    continue;
                }

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar = (float) $lebar;
                $tebal = (float) $tebal;
                $isAf = ($statusKw === 'af');

                $totalBanyak = $items->sum('jumlah');
                $totalM3 = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                $modalPerGroup[$key] = [
                    'jnsNorm' => $jnsNorm,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'statusKw' => $statusKw,
                    'kwRaw' => $kwRaw,
                    'isAf' => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3' => $totalM3,
                ];
            }

            // ============================================================
            // STEP C: Hitung Selisih Jurnal (Balance / Kehilangan / Kelebihan)
            // ============================================================
            $allKeys = array_unique(array_merge(
                array_keys($hasilPerGroup),
                array_keys($modalPerGroup)
            ));

            foreach ($allKeys as $key) {
                $hasil = $hasilPerGroup[$key] ?? null;
                $modal = $modalPerGroup[$key] ?? null;

                $meta = $hasil ?? $modal;
                $jnsNorm = $meta['jnsNorm'];
                $panjang = $meta['panjang'];
                $lebar = $meta['lebar'];
                $tebal = $meta['tebal'];
                $statusKw = $meta['statusKw'];
                $kwRaw = $meta['kwRaw'];
                $isAf = $meta['isAf'];

                $hasilM3 = $hasil['totalM3'] ?? 0.0;
                $hasilBanyak = $hasil['totalBanyak'] ?? 0;
                $modalM3 = $modal['totalM3'] ?? 0.0;
                $modalBanyak = $modal['totalBanyak'] ?? 0;

                $refJadi = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'jadi');
                $refKering = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'kering');

                [$namaAkunJadi,   $noAkunJadi,   $hargaJadi] = $this->extractAkunVeneer($refJadi);
                [$namaAkunKering, $noAkunKering, $hargaKering] = $this->extractAkunVeneer($refKering);

                $keteranganNormal = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $statusKw, $kwRaw);
                $keteranganJadi = $keteranganNormal.(! $refJadi ? ' [UNKNOWN]' : '');
                $keteranganKering = $keteranganNormal.(! $refKering ? ' [UNKNOWN]' : '');

                $diffBanyak = $modalBanyak - $hasilBanyak;

                if ($diffBanyak > 0) {
                    // KONDISI KEHILANGAN
                    $kehilanganBanyak = $diffBanyak;
                    $modalSebanding = $hasilBanyak;

                    $m3Hasil = ($panjang * $lebar * $tebal * $hasilBanyak) / 10000000;
                    $m3Modal = ($panjang * $lebar * $tebal * $modalSebanding) / 10000000;
                    $m3Kehilangan = ($panjang * $lebar * $tebal * $kehilanganBanyak) / 10000000;

                    if ($hasilBanyak > 0) {
                        $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilBanyak, $m3Hasil, $hargaJadi, 'm');
                        $totalDebit += ($m3Hasil * $hargaJadi);
                    }

                    if ($modalSebanding > 0) {
                        $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalSebanding, $m3Modal, $hargaKering, 'm');
                        $totalKredit += ($m3Modal * $hargaKering);
                    }

                    $keteranganKehilangan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $statusKw, $kwRaw, 'Kehilangan').(! $refKering ? ' [UNKNOWN]' : '');
                    $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKehilangan, 'k', $kehilanganBanyak, $m3Kehilangan, $hargaKering, 'm');
                    $totalKredit += ($m3Kehilangan * $hargaKering);
                } elseif ($diffBanyak < 0) {
                    // KONDISI KELEBIHAN
                    $kelebihanBanyak = abs($diffBanyak);
                    $hasilSebanding = $modalBanyak;

                    $m3HasilUtama = ($panjang * $lebar * $tebal * $hasilSebanding) / 10000000;
                    $m3Kelebihan = ($panjang * $lebar * $tebal * $kelebihanBanyak) / 10000000;
                    $m3ModalUtama = ($panjang * $lebar * $tebal * $modalBanyak) / 10000000;

                    $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilSebanding, $m3HasilUtama, $hargaJadi, 'm');
                    $totalDebit += ($m3HasilUtama * $hargaJadi);

                    $keteranganKelebihan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $statusKw, $kwRaw, 'Kelebihan').(! $refJadi ? ' [UNKNOWN]' : '');
                    $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganKelebihan, 'd', $kelebihanBanyak, $m3Kelebihan, $hargaJadi, 'm');
                    $totalDebit += ($m3Kelebihan * $hargaJadi);

                    $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalBanyak, $m3ModalUtama, $hargaKering, 'm');
                    $totalKredit += ($m3ModalUtama * $hargaKering);
                } else {
                    // KONDISI NORMAL / BALANCE
                    $m3Hasil = ($panjang * $lebar * $tebal * $hasilBanyak) / 10000000;
                    $m3Modal = ($panjang * $lebar * $tebal * $modalBanyak) / 10000000;

                    $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilBanyak, $m3Hasil, $hargaJadi, 'm');
                    $totalDebit += ($m3Hasil * $hargaJadi);

                    $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalBanyak, $m3Modal, $hargaKering, 'm');
                    $totalKredit += ($m3Modal * $hargaKering);
                }
            }

            // ============================================================
            // STEP 2: UKURAN MANUAL
            // ============================================================
            $hasilManual = collect($produksi->detailHasilRepairs)->filter(function ($h) {
                return ! $h->modalRepair || ! $h->modalRepair->ukuran || ! $h->modalRepair->jenisKayu;
            });

            foreach ($hasilManual as $hasil) {
                if (! $hasil->ukuran) {
                    continue;
                }

                $jenisModel = $hasil->jenisKayu;
                $jnsNorm = $this->normalizeJenis($jenisModel?->nama_kayu ?? 'meranti');
                $statusKw = strtolower((string) $hasil->kw);
                $isAf = str_contains($statusKw, 'af');
                $tebal = (float) $hasil->ukuran->tebal;
                $panjang = (float) $hasil->ukuran->panjang;
                $lebar = (float) $hasil->ukuran->lebar;
                $kwRaw = (string) ((int) filter_var($statusKw, FILTER_SANITIZE_NUMBER_INT));

                $banyak = (int) $hasil->jumlah;
                if ($banyak <= 0) {
                    continue;
                }

                $m3 = ($panjang * $lebar * $tebal * $banyak) / 10000000;

                $refJadi = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'jadi');
                [$namaAkun, $noAkun, $harga] = $this->extractAkunVeneer($refJadi);

                $keterangan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $statusKw, $kwRaw).(! $refJadi ? ' [UNKNOWN]' : '');

                $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $banyak, $m3, $harga, 'm');
                $totalDebit += ($m3 * $harga);
            }

            // ============================================================
            // STEP 3: KREDIT BAHAN PENOLONG (akun TIDAK di-alias)
            // ============================================================
            if (! empty($produksi->bahanPenolongRepair)) {
                foreach ($produksi->bahanPenolongRepair as $bahan) {
                    $jumlah = (float) ($bahan->jumlah ?? 0);
                    if ($jumlah <= 0) {
                        continue;
                    }

                    $namaBahanRaw = $bahan->bahanPenolong->nama_bahan_penolong ?? 'Bahan';
                    $refBahan = $this->fetchReferensiBahan($namaBahanRaw);
                    [$namaAkun, $noAkun, $harga] = $this->extractAkunBahan($refBahan);

                    $keteranganBahan = ! $refBahan ? "{$namaBahanRaw} [UNKNOWN]" : '';
                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keteranganBahan, 'k', $jumlah, '', $harga, 'b');
                    $totalKredit += ($jumlah * $harga);
                }
            }

            // ============================================================
            // STEP 4: Gaji Pegawai — 2231.00 -> 2195.1
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2195.1', '', 'k', $jmlPekerja, '', 150000, 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // ============================================================
            // STEP 5: Selisih harga patok produksi (Penyeimbang)
            // 6111.00 "hpp triplek" -> 5069.2 "Selisih harga patok produksi"
            // ============================================================
            $hppRow = [];
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                if ($selisih > 0) {
                    $hppRow[] = $this->makeRow('Selisih harga patok produksi', $tglFormat, '5069.2', '', 'k', '', '', abs($selisih), '');
                } else {
                    $hppRow[] = $this->makeRow('Selisih harga patok produksi', $tglFormat, '5069.2', '', 'd', '', '', abs($selisih), '');
                }
            }

            $rows = array_merge($rows, $jurnalBlockDebit, $jurnalBlockKredit, $hppRow);
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
