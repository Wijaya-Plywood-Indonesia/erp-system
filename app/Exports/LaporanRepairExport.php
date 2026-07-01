<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanRepairExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // Array hasil RepairDataMap (untuk Sheet 1)
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query langsung ke DB, tidak lewat transformer!
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (UPDATE: TAMBAH KOLOM KETERANGAN)
// ============================================================
class LaporanRepairDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $detailData)
    {
        $this->data = collect($detailData)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->data as $groupKey => $items) {
            $first        = $items->first();
            $targetPerJam = $first['jam_kerja'] > 0
                ? round($first['target'] / $first['jam_kerja'], 2)
                : 0;
            $pekerja      = $first['pekerja'] ?? [];

            $rows->push(['MEJA',        $first['nomor_meja']]);
            $rows->push(['UKURAN',      $first['ukuran']]);
            $rows->push(['JENIS KAYU',  $first['jenis_kayu']]);
            $rows->push(['KW',          $first['kw']]);
            $rows->push(['TANGGAL',     $first['tanggal']]);
            $rows->push([]);

            // 🚀 UPDATE HEADER TABEL: Menambahkan Keterangan Hasil & Kerja di samping Keterangan Absen lama
            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil', // 👈 Kolom Baru
                'Keterangan Kerja', // 👈 Kolom Baru
                '',
                'Target Harian',
                'Jam Kerja',
                'Target / Jam',
                'Hasil',
                'Selisih'
            ]);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',       // Ini Keterangan Absen bawaan array Anda
                    $p['keterangan_hasil'] ?? '—', // 👈 Diambil langsung dari mapping data hasil pekerja
                    $p['keterangan_kerja'] ?? '—', // 👈 Diambil langsung dari mapping data rencana kerja pekerja
                    '',
                    $first['target'],
                    $first['jam_kerja'],
                    $targetPerJam,
                    $first['hasil'],
                    $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                '',
                '',
                '',
                '',
                $totalPotongan,
                '',
                '', // Kosongkan kolom baru untuk baris TOTAL
                '', // Kosongkan kolom baru untuk baris TOTAL
                '',
                $first['target'],
                $first['jam_kerja'],
                $targetPerJam,
                $first['hasil'],
                $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }
    public function title(): string
    {
        return 'Detail Per Meja';
    }
}

// ============================================================
// SHEET 2: SUMMARY — Bersih Seperti Semula
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    private const MASTER_KW = ['1', '2', '3', '4', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d M');

            foreach ($produksi->modalRepairs as $modal) {
                $p = (float) ($modal->ukuran->panjang ?? 0);
                $l = (float) ($modal->ukuran->lebar   ?? 0);
                $t = (float) ($modal->ukuran->tebal   ?? 0);
                $jenis = strtoupper($modal->jenisKayu->kode_kayu ?? substr($modal->jenisKayu->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim($modal->kw ?? ''));

                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$kwData}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'current_kw'  => $kwData,
                        'pekerja_ids' => [],
                    ];

                    foreach (self::MASTER_KW as $mKw) {
                        $this->summary[$key]['kw_' . $mKw] = 0;
                    }
                }

                $hasilModal = 0;
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu > 0) {
                        $hasilModal += $hasilIndividu;
                        $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                    }
                }

                if ($kwData !== '' && $hasilModal > 0) {
                    if (in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $hasilModal;
                    }
                }
            }
        }

        ksort($this->summary);
    }

    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalMasterKw = count(self::MASTER_KW);
        $lastRow = $dataStart + count($this->summary) - 1;

        // Row 2: Grand Total
        $grandRow = ['', '', '', '', ''];
        for ($i = 0; $i < $totalMasterKw; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalMasterKw);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";

        $rows->push($grandRow);

        // Row 3+: Data Rows
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            foreach (self::MASTER_KW as $mKw) {
                $val = $s['kw_' . $mKw] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_KW as $mKw) {
            $heads[] = 'KW ' . strtoupper($mKw);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Header & Grand Total
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => $color]],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }
}

// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}
 
    // Cache agar tidak query DB berulang
    private array $kayuCache       = [];
    private array $kategoriCache   = [];
    private array $bahanRefCache   = [];
 
    public function title(): string
    {
        return 'jurnal produksi';
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
            'D' => '0.00',
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
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);
 
        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
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
 
    // ================================================================
    // HELPER: Normalisasi jenis kayu → 'sengon' | 'meranti'
    // ================================================================
    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }
 
    // ================================================================
    // HELPER: Deteksi apakah request dari perusahaan WHN
    // ================================================================
    private function isWHN(): bool
    {
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                return true;
            }
        }
        return false;
    }
 
    // ================================================================
    // HELPER: Format keterangan lengkap dengan dimensi + jenis + KW
    // ================================================================
    private function buildKeterangan(
        float  $panjang,
        float  $lebar,
        float  $tebal,
        string $jenis,
        string $statusKw,
        string $kwRaw = '',
        string $prefix = ''
    ): string {
        $fmt = function (float $val): string {
            if ($val == (int) $val) {
                return (string)(int) $val;
            }
            return str_replace('.', ',', rtrim(number_format($val, 4, '.', ''), '0'));
        };
 
        $p   = $fmt($panjang);
        $l   = $fmt($lebar);
        $t   = $fmt($tebal);
        $jns = ucfirst(strtolower($this->normalizeJenis($jenis)));
        $kw  = $kwRaw !== '' ? " KW{$kwRaw}" : '';
        $af  = $statusKw === 'af' ? ' AF' : '';
        $pfx = $prefix !== '' ? "{$prefix} " : '';
 
        return "{$pfx}{$p}x{$l}x{$t} {$jns}{$kw}{$af}";
    }
 
    // ================================================================
    // DATABASE REFERENCE HELPERS
    // ================================================================
 
    private function getIdKayuByNama(string $jenis): ?int
    {
        $jns = $this->normalizeJenis($jenis) === 'sengon' ? 'Sengon' : 'Meranti';
        $key = strtolower($jns);
        if (!array_key_exists($key, $this->kayuCache)) {
            $kayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
            $this->kayuCache[$key] = $kayu?->id;
        }
        return $this->kayuCache[$key];
    }
 
    /**
     * Kolom di tabel kategori_barang adalah `nama_kategori`, bukan `nama`.
     */
    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (!array_key_exists($key, $this->kategoriCache)) {
            try {
                $kategori = \App\Models\KategoriBarang::whereRaw("LOWER(nama_kategori) LIKE ?", ["%{$key}%"])->first();
                $this->kategoriCache[$key] = $kategori?->id;
            } catch (\Throwable $e) {
                $this->kategoriCache[$key] = null;
            }
        }
        return $this->kategoriCache[$key];
    }
 
    // ================================================================
    // HELPER: Cari referensi veneer (jadi/kering/afalan), full dari DB.
    // Tidak ada fallback hardcode — jika tidak ditemukan, return null
    // supaya bisa dideteksi sebagai UNKNOWN di Excel.
    //
    // Mapping kategori:
    //   isAf = true                   → 'veneer afalan' (satu kategori untuk
    //                                    modal maupun hasil yang ber-kw "af")
    //   isAf = false, status='jadi'   → 'veneer jadi'
    //   isAf = false, status='kering' → 'veneer kering'
    //
    // kw dikirim null untuk afalan (kw_min/kw_max null di tabel),
    // kw=1 untuk jadi (representatif range 1-2), kw=3 untuk kering (range 3-4).
    // Pembeda 260 f/b vs 130 core otomatis via t_min/t_max di tabel.
    //
    // Guard: kalau id kategori atau id jenis kayu tidak ditemukan, langsung
    // return null tanpa lanjut ke findReferensi(), supaya tidak salah ambil
    // referensi dari kategori/kayu lain.
    // ================================================================
    private function fetchReferensiVeneer(string $jenis, float $tebal, bool $isAf, string $status): ?\App\Models\ReferensiHargaProduksi
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
 
        if (!$idJenisKayu || !$idKategoriBarang) {
            return null;
        }
 
        return \App\Models\ReferensiHargaProduksi::findReferensi(
            idJenisKayu      : $idJenisKayu,
            idKategoriBarang : $idKategoriBarang,
            kw               : $kw,
            tebal            : $tebal,
        );
    }
 
    /**
     * Ekstrak nama akun, no akun, harga dari hasil referensi veneer.
     * Jika ref null → 'UNKNOWN' agar mudah dicrosscheck di Excel.
     */
    private function extractAkunVeneer(?\App\Models\ReferensiHargaProduksi $ref): array
    {
        if (!$ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }
        if (!$ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }
        $sub = $ref->subAnakAkun;
        if (!$sub) {
            return ['UNKNOWN', 'UNKNOWN', (float) $ref->harga];
        }
        $nama   = trim($sub->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noAkun = trim($sub->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';
 
        return [$nama, $noAkun, (float) $ref->harga];
    }
 
    // ================================================================
    // HELPER: Cari referensi bahan penolong dari kategori 'barang'.
    // Dicocokkan by nama bahan (LIKE), karena kategori 'barang' tidak
    // pakai filter kw/tebal — tiap baris referensi spesifik per nama bahan.
    // Tidak ada fallback hardcode — jika tidak ketemu, return null.
    // ================================================================
    private function fetchReferensiBahan(string $namaBahan): ?\App\Models\ReferensiHargaProduksi
    {
        $key = strtolower(trim($namaBahan));
        if (array_key_exists($key, $this->bahanRefCache)) {
            return $this->bahanRefCache[$key];
        }
 
        $idKategoriBarang = $this->getIdKategoriBarang('barang');
        if (!$idKategoriBarang) {
            return $this->bahanRefCache[$key] = null;
        }
 
        // Cocokkan nama bahan ke kolom `nama` di referensi_harga_produksi,
        // dan juga ke nama sub anak akun sebagai fallback pencocokan.
        $ref = \App\Models\ReferensiHargaProduksi::with('subAnakAkun')
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
 
    // ================================================================
    // HELPER: Buat satu baris array untuk export
    // ================================================================
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
            ($m3     === '' || $m3     === null) ? '' : (float) $m3,
            ($harga  === '' || $harga  === null) ? '' : (float) $harga,
            '',
        ];
    }
 
    // ================================================================
    // HELPER: Inisialisasi entry $selisihPerGroup hanya sekali
    // ================================================================
    private function ensureSelisihGroup(
        array  &$selisihPerGroup,
        string $groupKey,
        string $jenis,
        float  $panjang,
        float  $lebar,
        float  $tebal,
        string $kelompok,
        bool   $isAf,
        string $kwRaw
    ): void {
        if (!isset($selisihPerGroup[$groupKey])) {
            $selisihPerGroup[$groupKey] = [
                'hasilM3'     => 0.0,
                'modalM3'     => 0.0,
                'hasilBanyak' => 0,
                'modalBanyak' => 0,
                'jenis'       => $jenis,
                'panjang'     => $panjang,
                'lebar'       => $lebar,
                'tebal'       => $tebal,
                'kelompok'    => $kelompok,
                'isAf'        => $isAf,
                'kwRaw'       => $kwRaw,
            ];
        }
    }
 
    // ================================================================
    // MAIN: Bangun seluruh array baris jurnal untuk export Excel
    // ================================================================
    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];
 
        foreach ($this->rawCollection as $produksi) {
            $tglFormat         = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit        = 0.0;
            $totalKredit       = 0.0;
            $jurnalBlockDebit  = [];
            $jurnalBlockKredit = [];
 
            // ============================================================
            // STEP A: Kumpulkan semua data hasil per group dulu
            // ============================================================
            $hasilPerGroup = [];
 
            $groupedHasil = collect($produksi->hasilRepairs)->groupBy(function ($hasil) {
                $modal = $hasil->rencanaRepair?->modalRepairs;
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';
 
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower(($hasil->rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $tebal    = (float) $modal->ukuran->tebal;
                $panjang  = (float) $modal->ukuran->panjang;
                $lebar    = (float) $modal->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));
 
                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });
 
            foreach ($groupedHasil as $key => $items) {
                if ($key === 'invalid_data') continue;
 
                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;
                $isAf    = ($statusKw === 'af');
 
                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;
 
                $hasilPerGroup[$key] = [
                    'jnsNorm'    => $jnsNorm,
                    'panjang'    => $panjang,
                    'lebar'      => $lebar,
                    'tebal'      => $tebal,
                    'statusKw'   => $statusKw,
                    'kwRaw'      => $kwRaw,
                    'isAf'       => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3'    => $totalM3,
                ];
            }
 
            // ============================================================
            // STEP B: Kumpulkan semua data modal per group
            // ============================================================
            $modalPerGroup = [];
 
            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';
 
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower($modal->kw ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $tebal    = (float) $modal->ukuran->tebal;
                $panjang  = (float) $modal->ukuran->panjang;
                $lebar    = (float) $modal->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));
 
                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });
 
            foreach ($groupedModal as $key => $items) {
                if ($key === 'invalid_data') continue;
 
                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;
                $isAf    = ($statusKw === 'af');
 
                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;
 
                $modalPerGroup[$key] = [
                    'jnsNorm'    => $jnsNorm,
                    'panjang'    => $panjang,
                    'lebar'      => $lebar,
                    'tebal'      => $tebal,
                    'statusKw'   => $statusKw,
                    'kwRaw'      => $kwRaw,
                    'isAf'       => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3'    => $totalM3,
                ];
            }
 
            // ============================================================
            // STEP C: Gabungkan semua key yang ada di hasil maupun modal
            //
            // KEHILANGAN (hasil < modal):
            //   DEBIT  hasil  → nilai asli hasil
            //   KREDIT modal  → nilai = hasil
            //   KREDIT info   → baris "Kehilangan X" dengan nilai selisih
            //
            // KELEBIHAN (hasil > modal):
            //   DEBIT  hasil  → nilai = modal
            //   DEBIT  info   → baris "Kelebihan X" dengan nilai selisih
            //   KREDIT modal  → nilai asli modal
            // ============================================================
            $allKeys = array_unique(array_merge(
                array_keys($hasilPerGroup),
                array_keys($modalPerGroup)
            ));
 
            $selisihDebitRows  = [];
            $selisihKreditRows = [];
 
            foreach ($allKeys as $key) {
                $hasil = $hasilPerGroup[$key] ?? null;
                $modal = $modalPerGroup[$key] ?? null;
 
                $meta     = $hasil ?? $modal;
                $jnsNorm  = $meta['jnsNorm'];
                $panjang  = $meta['panjang'];
                $lebar    = $meta['lebar'];
                $tebal    = $meta['tebal'];
                $statusKw = $meta['statusKw'];
                $kwRaw    = $meta['kwRaw'];
                $isAf     = $meta['isAf'];
 
                $hasilM3     = $hasil['totalM3']     ?? 0.0;
                $hasilBanyak = $hasil['totalBanyak'] ?? 0;
                $modalM3     = $modal['totalM3']     ?? 0.0;
                $modalBanyak = $modal['totalBanyak'] ?? 0;
 
                $diffM3     = round($hasilM3 - $modalM3, 4);
                $diffBanyak = $hasilBanyak - $modalBanyak;
 
                // ── Ambil referensi penuh dari DB, tidak ada fallback hardcode ──
                $refJadi   = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'jadi');
                $refKering = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'kering');
 
                [$namaAkunJadi,   $noAkunJadi,   $hargaJadi]   = $this->extractAkunVeneer($refJadi);
                [$namaAkunKering, $noAkunKering, $hargaKering] = $this->extractAkunVeneer($refKering);
 
                $keteranganNormal = $this->buildKeterangan(
                    $panjang,
                    $lebar,
                    $tebal,
                    $jnsNorm,
                    $statusKw,
                    $kwRaw
                );
                // Tandai di keterangan kalau referensi tidak ditemukan, untuk crosscheck
                $keteranganJadi   = $keteranganNormal . (!$refJadi   ? ' [UNKNOWN]' : '');
                $keteranganKering = $keteranganNormal . (!$refKering ? ' [UNKNOWN]' : '');
 
                if ($diffM3 < 0) {
                    // ── KEHILANGAN ─────────────────────────────────────────
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganJadi,
                        'd',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($hasilM3 * $hargaJadi);
 
                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganKering,
                        'k',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($hasilM3 * $hargaKering);
 
                    $keteranganKehilangan = $this->buildKeterangan(
                        $panjang,
                        $lebar,
                        $tebal,
                        $jnsNorm,
                        $statusKw,
                        $kwRaw,
                        'Kehilangan'
                    ) . (!$refKering ? ' [UNKNOWN]' : '');
                    $selisihKreditRows[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganKehilangan,
                        'k',
                        abs($diffBanyak),
                        abs($diffM3),
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += (abs($diffM3) * $hargaKering);
                } elseif ($diffM3 > 0) {
                    // ── KELEBIHAN ──────────────────────────────────────────
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganJadi,
                        'd',
                        $modalBanyak,
                        $modalM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($modalM3 * $hargaJadi);
 
                    $keteranganKelebihan = $this->buildKeterangan(
                        $panjang,
                        $lebar,
                        $tebal,
                        $jnsNorm,
                        $statusKw,
                        $kwRaw,
                        'Kelebihan'
                    ) . (!$refJadi ? ' [UNKNOWN]' : '');
                    $selisihDebitRows[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganKelebihan,
                        'd',
                        abs($diffBanyak),
                        abs($diffM3),
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += (abs($diffM3) * $hargaJadi);
 
                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganKering,
                        'k',
                        $modalBanyak,
                        $modalM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($modalM3 * $hargaKering);
                } else {
                    // ── BALANCE ────────────────────────────────────────────
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganJadi,
                        'd',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($hasilM3 * $hargaJadi);
 
                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganKering,
                        'k',
                        $modalBanyak,
                        $modalM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($modalM3 * $hargaKering);
                }
            }
 
            foreach ($selisihDebitRows  as $row) $jurnalBlockDebit[]  = $row;
            foreach ($selisihKreditRows as $row) $jurnalBlockKredit[] = $row;
 
            // ============================================================
            // 4. KREDIT: Bahan Penolong
            // Sekarang full dari tabel referensi (kategori 'barang'),
            // dicocokkan by nama. Tidak ada fallback hardcode no akun/harga —
            // jika tidak ketemu, ditandai UNKNOWN agar mudah dicrosscheck.
            // ============================================================
            if (!empty($produksi->bahanPenolongRepair)) {
                foreach ($produksi->bahanPenolongRepair as $bahan) {
                    $jumlah = (float) ($bahan->jumlah ?? 0);
                    if ($jumlah <= 0) continue;
 
                    $namaBahanRaw = $bahan->bahanPenolong->nama_bahan_penolong ?? 'Bahan';
 
                    $refBahan = $this->fetchReferensiBahan($namaBahanRaw);
                    [$namaAkun, $noAkun, $harga] = $this->extractAkunVeneer($refBahan);
 
                    $keteranganBahan = !$refBahan ? "{$namaBahanRaw} [UNKNOWN]" : '';
 
                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keteranganBahan, 'k', $jumlah, '', $harga, 'b');
                    $totalKredit += ($jumlah * $harga);
                }
            }
 
            // ============================================================
            // 5. KREDIT: Gaji Pegawai (tidak berubah — bukan dari referensi harga produksi)
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, '', 150000, 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }
 
            // ============================================================
            // 6. PENYEIMBANG: HPP Repair
            // Ditaruh di array terpisah ($hppRow), bukan digabung ke
            // $jurnalBlockDebit/$jurnalBlockKredit, supaya saat dicetak
            // baris HPP selalu berada paling bawah sendiri (setelah semua
            // baris debit dan kredit lainnya).
            // ============================================================
            $hppRow = [];
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                if ($selisih > 0) {
                    $hppRow[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'k', '', '', abs($selisih), '');
                } else {
                    $hppRow[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', '', '', abs($selisih), '');
                }
            }
 
            $rows   = array_merge($rows, $jurnalBlockDebit, $jurnalBlockKredit, $hppRow);
            $rows[] = array_fill(0, 14, '');
        }
 
        return $rows;
    }
}
