<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanJurnalKayuMasukSheet1 implements FromCollection, WithTitle, WithStyles
{
    protected array $jurnalTables;

    public function __construct(array $jurnalTables)
    {
        $this->jurnalTables = $jurnalTables;
    }

    public function collection()
    {
        $flatRows = [];

        foreach ($this->jurnalTables as $table) {
            // Meta Row 1
            $flatRows[] = [
                'No. Nota: ' . $table['no_nota'],
                '',
                'Seri: ' . $table['seri'],
                '',
                'Tanggal: ' . $table['tgl_kayu_masuk']
            ];

            // Meta Row 2
            $flatRows[] = [
                'Supplier: ' . $table['nama_supplier'],
                '',
                'Nopol: ' . $table['nopol_kendaraan'],
                '',
                'Legal/Via: ' . $table['dokumen_legal']
            ];

            $flatRows[] = ['', '', '', '', '']; // Spacer row

            // Loop through wood groups
            foreach ($table['groups'] as $group) {
                // Group Header (e.g. "D 260 cm Sengon (B)")
                $flatRows[] = [$group['header'], '', '', '', ''];

                // Group Column Subheaders
                $flatRows[] = [
                    'Rentang D (cm)',
                    'Btg',
                    'm³',
                    'Harga',
                    'Poin'
                ];

                // Group Rows
                foreach ($group['rows'] as $row) {
                    $flatRows[] = [
                        $row['rentang'],
                        $row['batang'],
                        $row['kubikasi'],
                        $row['harga_satuan'],
                        $row['total_harga']
                    ];
                }

                // Group Total
                $flatRows[] = [
                    'Total',
                    $group['total_batang'],
                    $group['total_kubikasi'],
                    '',
                    $group['total_harga']
                ];

                $flatRows[] = ['', '', '', '', '']; // Spacer row after group
            }

            // Summary Block
            $flatRows[] = [
                'Total Kubikasi',
                $table['totalKubikasi'],
                '',
                'Grand Total',
                $table['grandTotal']
            ];

            $flatRows[] = [
                'Total Batang',
                $table['totalBatang'],
                '',
                'Selisih',
                $table['selisih']
            ];

            $flatRows[] = [
                'Total Akhir',
                '',
                '',
                '',
                $table['hargaFinal']
            ];

            $flatRows[] = [
                'Penanggung Jawab: ' . $table['penanggung_jawab'],
                '',
                'Grader: ' . $table['penerima'],
                '',
                'via: ' . $table['dokumen_legal']
            ];

            $flatRows[] = [
                'Dicetak pada: ' . now()->format('d-m-Y H:i'),
                '',
                '',
                '',
                ''
            ];

            // Spacer Rows between multiple notas
            $flatRows[] = ['', '', '', '', ''];
            $flatRows[] = ['', '', '', '', ''];
        }

        return collect($flatRows);
    }

    public function title(): string
    {
        return 'Rekap Nota Kayu';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            if (str_starts_with((string)$cellValue, 'No. Nota:')) {
                // Style the Title block of each nota
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->mergeCells("C{$row}:D{$row}");
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['argb' => 'FFFFFFFF']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF27272A'] // Zinc 800
                    ],
                ]);
            } elseif (str_starts_with((string)$cellValue, 'Supplier:')) {
                // Style the metadata row
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->mergeCells("C{$row}:D{$row}");
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 10,
                        'color' => ['argb' => 'FFE5E5E5']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF27272A'] // Zinc 800
                    ],
                ]);
            } elseif (str_ends_with((string)$cellValue, ')') && (str_contains((string)$cellValue, 'cm') || str_contains((string)$cellValue, 'Sengon') || str_contains((string)$cellValue, 'Kayu'))) {
                // This is a group header row, e.g. "D 260 cm Sengon (B)"
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E8EB']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif ($cellValue === 'Rentang D (cm)') {
                // Table Column Headers
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 9
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF1F1F1']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif ($cellValue === 'Total' && $sheet->getCell("B{$row}")->getValue() !== '') {
                // Group Total row
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 9
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
            } elseif ($cellValue === 'Total Kubikasi') {
                // Summary block row 1
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
            } elseif ($cellValue === 'Total Batang') {
                // Summary block row 2
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
            } elseif ($cellValue === 'Total Akhir') {
                // Final Netto Total row
                $sheet->mergeCells("A{$row}:D{$row}");
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['argb' => 'FF1D2939']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFD2E4F0']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]
                    ]
                ]);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
            } elseif (str_starts_with((string)$cellValue, 'Penanggung:')) {
                // Signature block
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->mergeCells("C{$row}:D{$row}");
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'font' => [
                        'size' => 9,
                        'italic' => true
                    ]
                ]);
            } elseif (!empty($cellValue) && !str_starts_with((string)$cellValue, 'Dicetak pada:')) {
                // Standard data rows (diameter rentang details)
                $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Number formats
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(25);

        return [];
    }
}
