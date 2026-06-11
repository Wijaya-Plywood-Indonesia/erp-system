<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================================================
//  MAIN EXPORT — membungkus 2 sheet
// ============================================================
class LaporanProduksiStikExport implements WithMultipleSheets
{
    protected array $dataStik;

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiStikSheetPekerja($this->dataStik),
            new LaporanProduksiStikSheetHasil($this->dataStik),
        ];
    }
}

// ============================================================
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

// ============================================================
//  SHEET 1 — "Laporan Produksi Stik" (Rotary Style)
// ============================================================
class LaporanProduksiStikSheetPekerja implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected $dataStik;
    protected $mergeRanges = [];
    protected $tableRanges = [];

    public function __construct(array $dataStik)
    {
        $this->dataStik = collect($dataStik);
    }

    public function collection(): Collection
    {
        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        if ($this->dataStik->isEmpty()) {
            return collect($allRows);
        }

        $allRows[] = ['LAPORAN PRODUKSI STIK'];
        $allRows[] = ['TANGGAL: ' . ($this->dataStik->first()['tanggal'] ?? '')];
        $allRows[] = array_fill(0, 11, '');

        $index = 0;
        foreach ($this->dataStik as $produksi) {
            $pekerja       = $produksi['pekerja']       ?? [];
            $kendala       = $produksi['kendala']       ?? 'Tidak ada kendala.';
            $target        = $produksi['target_harian'] ?? 0;
            $jamKerja      = $produksi['jam_kerja']     ?? 0;
            $hasil         = $produksi['hasil_harian']  ?? 0;
            $selisih       = $produksi['selisih']       ?? 0;
            $totalPekerja  = count($pekerja);
            $selisihTampil = $selisih * -1;

            $targetPerJam  = $jamKerja > 0 ? $target / $jamKerja : 0;

            $allRows[] = ['PRODUKSI STIK - Entri ke-' . ($index + 1)];
            $allRows[] = array_fill(0, 11, '');

            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala'];

            $workerStartRow = count($allRows) + 1;
            $N = count($pekerja);
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            if ($N > 0) {
                // Merge summary columns
                if ($N > 1) {
                    $this->mergeRanges[] = "F{$workerStartRow}:F{$workerEndRow}";
                    $this->mergeRanges[] = "G{$workerStartRow}:G{$workerEndRow}";
                    $this->mergeRanges[] = "H{$workerStartRow}:H{$workerEndRow}";
                    $this->mergeRanges[] = "I{$workerStartRow}:I{$workerEndRow}";
                    $this->mergeRanges[] = "J{$workerStartRow}:J{$workerEndRow}";
                    $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
                }
            }

            foreach ($pekerja as $idx => $p) {
                $potTargetRaw = (int) str_replace(['.', 'Rp ', '-'], '', $p['pot_target'] ?? '0');
                
                // Combine attendance details into Keterangan column
                $ketParts = [];
                if (!empty($p['jam_masuk']) && $p['jam_masuk'] !== '-') {
                    $ketParts[] = "Masuk: " . $p['jam_masuk'] . ($p['jam_pulang'] !== '-' ? " - " . $p['jam_pulang'] : "");
                }
                if (!empty($p['ijin']) && $p['ijin'] !== '-') {
                    $ketParts[] = "Ijin: " . $p['ijin'];
                }
                if (!empty($p['keterangan']) && $p['keterangan'] !== '-') {
                    $ketParts[] = $p['keterangan'];
                }
                $ketString = !empty($ketParts) ? implode(" | ", $ketParts) : '-';

                $allRows[] = [
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $potTargetRaw > 0 ? (int) $potTargetRaw : 0,
                    $ketString,
                    '',
                    $idx === 0 ? (int) $target : '',
                    $idx === 0 ? (int) $jamKerja : '',
                    $idx === 0 ? round((float) $targetPerJam, 2) : '',
                    $idx === 0 ? (int) $hasil : '',
                    $idx === 0 ? (int) $selisihTampil : '',
                    $idx === 0 ? $kendala : ''
                ];
            }

            // Total Row
            $allRows[] = [
                'TOTAL',
                $N . ' pekerja',
                $N > 0 ? "=SUM(C{$workerStartRow}:C{$workerEndRow})" : 0,
                '',
                '',
                $N > 0 ? "=SUM(F{$workerStartRow}:F{$workerEndRow})" : 0,
                (int) $jamKerja,
                $N > 0 ? "=SUM(H{$workerStartRow}:H{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(I{$workerStartRow}:I{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(J{$workerStartRow}:J{$workerEndRow})" : 0,
                ''
            ];

            $allRows[] = array_fill(0, 11, '');
            $allRows[] = array_fill(0, 11, '');

            $this->tableRanges[] = [
                'header' => $headerRow,
                'start'  => $workerStartRow,
                'end'    => $workerEndRow,
                'total'  => $totalRow
            ];

            $index++;
        }

        return collect($allRows);
    }

    public function headings(): array { return []; }
    public function title(): string   { return 'Laporan Produksi Stik'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set explicit column widths
                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(25);
                $sheet->getColumnDimension('E')->setWidth(5);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);
                $sheet->getColumnDimension('J')->setWidth(12);
                $sheet->getColumnDimension('K')->setWidth(45);

                // Merge cells dynamically
                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                // Apply styles, borders, alignments and colors for each table
                foreach ($this->tableRanges as $range) {
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    // 1. Grid borders for the entire table (A{header} to K{total})
                    $sheet->getStyle("A{$headerRow}:K{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ]
                        ]
                    ]);

                    // 2. Header row style
                    $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0']
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ]
                    ]);

                    // 3. Total row style
                    $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9']
                        ]
                    ]);

                    // 4. Alignments for worker data cells (A{start} to K{end})
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        
                        $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("I{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("K{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        
                        // Vertical alignment top for merged cells
                        $sheet->getStyle("F{$startRow}:K{$endRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                        // Number formats
                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("J{$startRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Enable wrap text and top vertical alignment for Keterangan (D) and Kendala (K)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("D1:D{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("K1:K{$highestRow}")->getAlignment()->setWrapText(true);
            }
        ];
    }
}

// ============================================================
//  SHEET 2 — "Hasil Stik"
//  Menggunakan data 'detail_hasil' yang sudah disiapkan
//  oleh LaporanStik::loadAllData() — sudah digroup & dikw-kan
// ============================================================
class LaporanProduksiStikSheetHasil implements FromArray, WithTitle, WithStyles
{
    protected array $dataStik;
    protected array $styleMap    = [];
    protected array $mergeRanges = [];

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function array(): array
    {
        $rows     = [];
        $rowIndex = 1;

        if (empty($this->dataStik)) {
            $rows[] = ['Tidak ada data untuk tanggal ini.'];
            return $rows;
        }

        foreach ($this->dataStik as $produksi) {
            $tanggal      = $produksi['tanggal']      ?? '-';
            $pekerja      = $produksi['pekerja']       ?? [];
            // ✅ Gunakan 'detail_hasil' sesuai key dari LaporanStik::loadAllData()
            $detailHasil  = $produksi['detail_hasil']  ?? [];
            $totalPekerja = count($pekerja);

            // ── JUDUL SEKSI ──────────────────────────────────────
            $rows[] = ['PRODUKSI STIK', '', '', '', '', '', '', '', '', '', '', ''];
            $this->styleMap[$rowIndex] = 'section_title';
            $rowIndex++;

            // ── HEADER KOLOM ─────────────────────────────────────
            $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'kw af', 'byk', 'TTL PKJ'];
            $this->styleMap[$rowIndex] = 'col_header';
            $rowIndex++;

            // ── DATA ROWS ────────────────────────────────────────
            $dataStartRow = $rowIndex;

            if (empty($detailHasil)) {
                // Fallback jika belum ada input hasil
                $rows[] = [$tanggal, '-', '-', '-', '-', '', '', '', '', '', $produksi['hasil_harian'] ?? 0, $totalPekerja];
                $this->styleMap[$rowIndex] = 'data';
                $rowIndex++;
            } else {
                foreach ($detailHasil as $i => $detail) {
                    $rows[] = [
                        // Tanggal dan TTL PKJ hanya di baris pertama, sisanya kosong (akan di-merge)
                        $i === 0 ? $tanggal      : '',
                        $detail['panjang']    ?? '-',
                        $detail['lebar']      ?? '-',
                        $detail['tebal']      ?? '-',
                        $detail['jenis_kayu'] ?? '-',
                        $detail['kw1']        ?? '',
                        $detail['kw2']        ?? '',
                        $detail['kw3']        ?? '',
                        $detail['kw4']        ?? '',
                        $detail['af']         ?? '',
                        $detail['total']      ?? '',
                        $i === 0 ? $totalPekerja : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                // Merge Tanggal (A) & TTL PKJ (L) jika lebih dari 1 baris
                if (count($detailHasil) > 1) {
                    $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
                    $this->mergeRanges[] = "L{$dataStartRow}:L{$dataEndRow}";
                }
            }

            // ── BARIS KOSONG PEMISAH ─────────────────────────────
            $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
            $rowIndex++;
        }

        return $rows;
    }

    public function title(): string { return 'Hasil Stik'; }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F4E79';
        $blueLight = '2E75B6';

        // ── MERGE CELL ───────────────────────────────────────────
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
                    $sheet->mergeCells("A{$rowNum}:L{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'size'  => 14,
                            'color' => ['rgb' => 'FFFFFF'],
                            'name'  => 'Arial',
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueDark],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'indent'     => 1,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(28);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                            'size'  => 10,
                            'name'  => 'Arial',
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
                    $sheet->getRowDimension($rowNum)->setRowHeight(20);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Arial'],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'BDD7EE'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(16);
                    break;
            }
        }

        // ── LEBAR KOLOM ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(13);
        $sheet->getColumnDimension('B')->setWidth(7);
        $sheet->getColumnDimension('C')->setWidth(7);
        $sheet->getColumnDimension('D')->setWidth(7);
        $sheet->getColumnDimension('E')->setWidth(9);
        foreach (['F','G','H','I','J','K'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
        $sheet->getColumnDimension('L')->setWidth(10);

        $sheet->freezePane('A3');

        return [];
    }
}