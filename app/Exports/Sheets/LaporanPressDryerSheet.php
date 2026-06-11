<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPressDryerSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected $dataProduksi;
    protected $mergeRanges = [];
    protected $tableRanges = [];

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = collect($dataProduksi)->groupBy('mesin');
    }

    public function collection()
    {
        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        foreach ($this->dataProduksi as $mesinNama => $produksiList) {
            $first = $produksiList->first();
            $pekerja = $first['pekerja'] ?? [];
            $daftarKendala = $first['daftar_kendala'] ?? [];
            $tanggal = $first['tanggal'] ?? '';
            $target = $first['target'] ?? 0;
            $jamKerja = $first['jam_kerja'] ?? 0;
            $targetPerJam = $first['target_per_jam'] ?? 0;
            $hasil = $first['hasil'] ?? 0;
            $selisih = $first['selisih'] ?? 0;
            $totalDowntimeMenit = $first['total_downtime_menit'] ?? 0;

            $allRows[] = ['MESIN: ' . strtoupper($mesinNama)];
            $allRows[] = ['TANGGAL: ' . $tanggal];
            $allRows[] = array_fill(0, 12, '');
            
            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala', 'Keterangan'];

            $workerStartRow = count($allRows) + 1;
            $N = count($pekerja);
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            // Pre-calculate cell values for Kendala column
            $kendalaCellValues = array_fill(0, $N, '');

            if ($N > 0) {
                if (count($daftarKendala) === 0) {
                    $kendalaCellValues[0] = 'Tidak ada kendala';
                    if ($N > 1) {
                        $this->mergeRanges[] = "K{$workerStartRow}:K" . ($workerStartRow + $N - 1);
                    }
                } else {
                    $M = count($daftarKendala);
                    $chunkSize = (int) ceil($N / $M);

                    for ($i = 0; $i < $M; $i++) {
                        $startIdx = $i * $chunkSize;
                        $endIdx = min(($i + 1) * $chunkSize - 1, $N - 1);

                        if ($startIdx < $N) {
                            $kendalaCellValues[$startIdx] = $daftarKendala[$i]['text'] ?? '';
                            $chunkStartRow = $workerStartRow + $startIdx;
                            $chunkEndRow = $workerStartRow + $endIdx;

                            if ($chunkStartRow < $chunkEndRow) {
                                $this->mergeRanges[] = "K{$chunkStartRow}:K{$chunkEndRow}";
                            }
                        }
                    }
                }

                // Merge Column L (Keterangan) vertically across all worker rows
                if ($N > 1) {
                    $this->mergeRanges[] = "L{$workerStartRow}:L{$workerEndRow}";
                }
            }

            foreach ($pekerja as $idx => $p) {
                $potTargetRaw = (float) str_replace('.', '', $p['pot_target'] ?? '0');
                
                // Column 12: Global Keterangan only for the first worker row
                $globalKetVal = '';
                if ($idx === 0) {
                    $globalKetVal = ($first['keterangan_global'] !== '-') ? $first['keterangan_global'] : '';
                }
                
                $allRows[] = [
                    $p['id'] ?? '-', 
                    $p['nama'] ?? '-', 
                    $potTargetRaw > 0 ? (int) $potTargetRaw : 0, 
                    $p['keterangan'] ?? '-', 
                    '', 
                    (float) $target, 
                    (int) $jamKerja, 
                    round((float) $targetPerJam, 4), 
                    (float) $hasil, 
                    (float) $selisih,
                    $kendalaCellValues[$idx],
                    $globalKetVal
                ];
            }

            $allRows[] = [
                'TOTAL', 
                $N . ' pekerja', 
                $N > 0 ? "=SUM(C{$workerStartRow}:C{$workerEndRow})" : 0, 
                '', 
                '', 
                (float) $target, 
                (int) $jamKerja, 
                round((float) $targetPerJam, 4), 
                (float) $hasil, 
                (float) $selisih, 
                $totalDowntimeMenit > 0 ? $totalDowntimeMenit . ' menit' : '',
                ''
            ];

            $allRows[] = array_fill(0, 12, '');
            $allRows[] = array_fill(0, 12, '');

            // Record the table range for borders and styling
            $this->tableRanges[] = [
                'header' => $headerRow,
                'start'  => $workerStartRow,
                'end'    => $workerEndRow,
                'total'  => $totalRow
            ];
        }
        return collect($allRows);
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Laporan Press Dryer';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set explicit column widths
                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(5);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);
                $sheet->getColumnDimension('J')->setWidth(12);
                $sheet->getColumnDimension('K')->setWidth(45);
                $sheet->getColumnDimension('L')->setWidth(45);

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

                    // 1. Grid borders for the entire table (A{header} to L{total})
                    $sheet->getStyle("A{$headerRow}:L{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ]
                        ]
                    ]);

                    // 2. Header row style
                    $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0']
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ]
                    ]);

                    // 3. Total row style
                    $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9']
                        ]
                    ]);

                    // 4. Alignments for worker data cells (A{start} to L{end})
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        
                        $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("I{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("K{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("L{$startRow}:L{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        
                        // Number formats
                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        // F, H, I, J formats
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                        $sheet->getStyle("J{$startRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    }
                }

                // Enable wrap text and top vertical alignment for Kendala (K) and Keterangan (L)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("K1:L{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            }
        ];
    }
}