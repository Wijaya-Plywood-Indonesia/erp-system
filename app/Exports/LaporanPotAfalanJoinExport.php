<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================================================
//  MAIN EXPORT — membungkus 2 sheet
// ============================================================
class LaporanPotAfalanJoinExport implements WithMultipleSheets
{
    protected array $laporan;

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotAfalanSheetPekerja($this->laporan),
            new LaporanPotAfalanSheetHasil($this->laporan),
        ];
    }
}

// ============================================================
//  SHEET 1 — "Data Pekerja" (tidak berubah)
// ============================================================
class LaporanPotAfalanSheetPekerja implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected array $laporan;

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    public function collection(): Collection
    {
        $rows = collect();

        $grouped = collect($this->laporan)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);

        foreach ($grouped as $items) {
            $first     = $items->first();
            $nomorMeja = $first['nomor_meja'] ?? '-';
            $ukuran    = $first['ukuran']     ?? '-';
            $jenisKayu = $first['jenis_kayu'] ?? '-';
            $kw        = $first['kw']         ?? '-';
            $tanggal   = $first['tanggal']    ?? '-';
            $target    = (int) ($first['target']  ?? 0);
            $hasil     = (int) ($first['hasil']   ?? 0);
            $selisih   = (int) ($first['selisih'] ?? 0);
            $pekerja   = $first['pekerja']    ?? [];

            $rows->push(['MEJA / AREA POTONG AFALAN', $nomorMeja]);
            $rows->push(['UKURAN',            $ukuran]);
            $rows->push(['JENIS KAYU/BARANG', $jenisKayu]);
            $rows->push(['GRADE / KW',        $kw]);
            $rows->push(['TANGGAL PRODUKSI',  $tanggal]);
            $rows->push([]);

            $rows->push([
                'ID PEGAWAI', 'Nama Lengkap', 'Jam Masuk', 'Jam Pulang',
                'Ijin', 'Potongan Target', 'Keterangan',
                '', 'Target Harian', 'Hasil Produksi', 'Selisih',
            ]);

            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);
                $rows->push([
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $p['jam_masuk']  ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin']       ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    $target,
                    $hasil,
                    $selisih >= 0 ? '+' . $selisih : $selisih,
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL', count($pekerja) . ' Orang', '', '', '',
                $totalPotongan > 0 ? $totalPotongan : '-',
                '', '', $target, $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string   { return 'Data Pekerja'; }
}

// ============================================================
//  SHEET 2 — "Hasil Pot Af"
//
//  Kolom: Tanggal | p | l | t | jenis | kw1 | kw2 | kw3 | kw4 | af | byk | TTL PKJ
//
//  Aturan kolom "jenis":
//    - kw angka (1/2/3/4) → singkatan jenis kayu (huruf pertama, misal Sengon → 's')
//    - kw 'Af' / 'af'     → 'af' + singkatan jenis kayu (misal 'afs' untuk Sengon)
//
//  Aturan kolom kw:
//    - kw angka → masuk ke kw1/kw2/kw3/kw4 sesuai nilainya
//    - kw 'Af'  → masuk ke kolom 'af'
//
//  Semua baris masuk ke SATU tabel per tanggal (bukan dipisah per meja)
// ============================================================
class LaporanPotAfalanSheetHasil implements FromArray, WithTitle, WithStyles
{
    protected array $laporan;
    protected array $styleMap    = [];
    protected array $mergeRanges = [];

    // Kolom: A=Tanggal B=p C=l D=t E=jenis F=kw1 G=kw2 H=kw3 I=kw4 J=af K=byk L=TTL PKJ
    const LAST_COL = 'L';
    const LAST_COL_IDX = 12; // 12 kolom

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    /**
     * Normalisasi kw:
     *   '1','2','3','4','kw1',... → 'kw1'/'kw2'/'kw3'/'kw4'
     *   'af','Af','AF','kw af',... → 'af'
     */
    private function normalizeKw(mixed $kw): string
    {
        if ($kw === null || $kw === '') return 'kw1';

        $str = strtolower(trim((string) $kw));

        // Cek apakah af
        if (str_contains($str, 'af')) return 'af';

        // Cek angka
        if (is_numeric($kw)) {
            $n = (int) $kw;
            return in_array($n, [1, 2, 3, 4]) ? "kw{$n}" : 'kw1';
        }

        if (preg_match('/(\d)/', $str, $m)) {
            $n = (int) $m[1];
            return in_array($n, [1, 2, 3, 4]) ? "kw{$n}" : 'kw1';
        }

        return 'kw1';
    }

    /**
     * Singkatan jenis kayu: ambil huruf pertama lowercase.
     * Misal: "Sengon" → "s", "Meranti" → "m"
     */
    private function singkatanJenis(string $jenisKayu): string
    {
        $clean = trim($jenisKayu);
        return $clean !== '' ? strtolower($clean[0]) : '-';
    }

    /**
     * Kolom "jenis" di Excel:
     *   - kw angka → singkatan jenis (misal 's')
     *   - kw af    → 'af' + singkatan jenis (misal 'afs')
     */
    private function labelJenis(string $kwNormalized, string $jenisKayu): string
    {
        $singkat = $this->singkatanJenis($jenisKayu);
        if ($kwNormalized === 'af') {
            return 'af' . $singkat; // misal 'afs'
        }
        return $singkat; // misal 's'
    }

    /**
     * Parse string ukuran "244.0mm x 122.0mm x 0.5mm" → p, l, t
     */
    private function parseUkuran(string $ukuran): array
    {
        $clean = preg_replace('/mm/i', '', $ukuran);
        $parts = preg_split('/\s*x\s*/i', trim($clean));

        $toNum = fn($v) => is_numeric(trim($v)) ? $v + 0 : trim($v);

        return [
            'p' => isset($parts[0]) ? $toNum($parts[0]) : '-',
            'l' => isset($parts[1]) ? $toNum($parts[1]) : '-',
            't' => isset($parts[2]) ? $toNum($parts[2]) : '-',
        ];
    }

    public function array(): array
    {
        $rows     = [];
        $rowIndex = 1;

        if (empty($this->laporan)) {
            $rows[] = ['Tidak ada data untuk tanggal ini.'];
            return $rows;
        }

        // Ambil tanggal & total pekerja unik dari seluruh laporan
        $tanggal      = $this->laporan[0]['tanggal']  ?? '-';
        $uniquePekerja = [];
        foreach ($this->laporan as $item) {
            foreach ($item['pekerja'] ?? [] as $p) {
                $uniquePekerja[$p['id'] ?? $p['nama']] = true;
            }
        }
        $totalPekerja = count($uniquePekerja);

        // ── JUDUL SEKSI (satu kali untuk semua baris) ────────────
        $rows[] = ['POTONG AFALAN JOIN', '', '', '', '', '', '', '', '', '', '', ''];
        $this->styleMap[$rowIndex] = 'section_title';
        $rowIndex++;

        // ── HEADER KOLOM ─────────────────────────────────────────
        // A       B  C  D  E      F    G    H    I    J   K    L
        // Tanggal p  l  t  jenis  kw1  kw2  kw3  kw4  af  byk  TTL PKJ
        $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'af', 'byk', 'TTL PKJ'];
        $this->styleMap[$rowIndex] = 'col_header';
        $rowIndex++;

        // ── DATA ROWS ────────────────────────────────────────────
        // Satu baris per item laporan (sudah terpisah per ukuran+kw dari transformer)
        $dataStartRow = $rowIndex;
        $totalItems   = count($this->laporan);

        foreach ($this->laporan as $i => $item) {
            $ukuranStr = $item['ukuran']     ?? '-';
            $jenisKayu = $item['jenis_kayu'] ?? '-';
            $kwRaw     = $item['kw']         ?? '1';
            $hasil     = $item['hasil']      ?? 0;

            $dim       = $this->parseUkuran($ukuranStr);
            $kwKey     = $this->normalizeKw($kwRaw);
            $labelJenis = $this->labelJenis($kwKey, $jenisKayu);

            $rows[] = [
                $i === 0 ? $tanggal      : '',          // A: Tanggal (hanya baris pertama)
                $dim['p'],                              // B: p
                $dim['l'],                              // C: l
                $dim['t'],                              // D: t
                $labelJenis,                            // E: jenis (s / afs / dll)
                $kwKey === 'kw1' ? ($hasil ?: '') : '', // F: kw1
                $kwKey === 'kw2' ? ($hasil ?: '') : '', // G: kw2
                $kwKey === 'kw3' ? ($hasil ?: '') : '', // H: kw3
                $kwKey === 'kw4' ? ($hasil ?: '') : '', // I: kw4
                $kwKey === 'af'  ? ($hasil ?: '') : '', // J: af
                $hasil ?: '',                           // K: byk (total baris ini)
                $i === 0 ? $totalPekerja : '',          // L: TTL PKJ (hanya baris pertama)
            ];
            $this->styleMap[$rowIndex] = 'data';
            $rowIndex++;
        }

        $dataEndRow = $rowIndex - 1;

        // Merge kolom Tanggal (A) dan TTL PKJ (L) jika lebih dari 1 baris
        if ($totalItems > 1) {
            $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
            $this->mergeRanges[] = "L{$dataStartRow}:L{$dataEndRow}";
        }

        return $rows;
    }

    public function title(): string { return 'Hasil Pot Af'; }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F497D';
        $blueLight = '2E75B6';
        $lastCol   = self::LAST_COL; // 'L'

        // ── MERGE CELL ────────────────────────────────────────────
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── STYLE PER BARIS ───────────────────────────────────────
        foreach ($this->styleMap as $rowNum => $type) {
            switch ($type) {

                case 'section_title':
                    $sheet->mergeCells("A{$rowNum}:{$lastCol}{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'size'  => 12,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueDark],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(22);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueLight],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'FFFFFF'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(18);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'BFBFBF'],
                            ],
                        ],
                    ]);
                    $sheet->getStyle("A{$rowNum}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    break;
            }
        }

        // ── LEBAR KOLOM ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(14); // Tanggal
        $sheet->getColumnDimension('B')->setWidth(8);  // p
        $sheet->getColumnDimension('C')->setWidth(8);  // l
        $sheet->getColumnDimension('D')->setWidth(8);  // t
        $sheet->getColumnDimension('E')->setWidth(8);  // jenis
        $sheet->getColumnDimension('F')->setWidth(8);  // kw1
        $sheet->getColumnDimension('G')->setWidth(8);  // kw2
        $sheet->getColumnDimension('H')->setWidth(8);  // kw3
        $sheet->getColumnDimension('I')->setWidth(8);  // kw4
        $sheet->getColumnDimension('J')->setWidth(8);  // af
        $sheet->getColumnDimension('K')->setWidth(8);  // byk
        $sheet->getColumnDimension('L')->setWidth(10); // TTL PKJ

        return [];
    }
}