<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class LaporanJurnalKayuMasukSheet2 extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithCustomValueBinder
{
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
    protected array $jurnalTables;

    public function __construct(array $jurnalTables)
    {
        $this->jurnalTables = $jurnalTables;
    }

    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $jenis = strtolower(trim($jenisKayuNama));
        $panjang = (int) $panjang;

        $isWHN = false;
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                $isWHN = true;
            }
        }

        $isLunak = (str_contains($jenis, 'sengon') || str_contains($jenis, 'lunak'));
        $isMeranti = str_contains($jenis, 'meranti');
        $isRijek = str_contains($jenis, 'rijek');
        $isLogCore = str_contains($jenis, 'log core') || str_contains($jenis, 'core');
        $isBaloan = str_contains($jenis, 'baloan') || str_contains($jenis, 'balo,an');

        if ($isWHN) {
            if ($isMeranti) {
                return ['no_akun' => '1413.09', 'nama_akun' => 'Kayu Meranti WHN'];
            }
            if ($isRijek) {
                return ['no_akun' => '1413.10', 'nama_akun' => 'Kayu Rijek WHN'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1414.00', 'nama_akun' => 'log core 130 WHN'];
                }
                return ['no_akun' => '1413.13', 'nama_akun' => 'log core 260 WHN'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.07', 'nama_akun' => 'Kayu Lunak 130 WHN'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1413.11', 'nama_akun' => 'kayu Lunak 230 WHN'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1413.12', 'nama_akun' => 'kayu Lunak 100 WHN'];
                }
                return ['no_akun' => '1413.01', 'nama_akun' => 'kayu Lunak 260 WHN'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1413.08', 'nama_akun' => 'Kayu Keras 130 WHN'];
            }
            return ['no_akun' => '1413.06', 'nama_akun' => 'Kayu Keras 260 WHN'];
        } else {
            // WJY
            if ($isMeranti) {
                return ['no_akun' => '1411.05', 'nama_akun' => 'Kayu Meranti WJY'];
            }
            if ($isRijek) {
                return ['no_akun' => '1411.06', 'nama_akun' => 'Kayu Rijek WJY'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.04', 'nama_akun' => 'log core 130 WJY'];
                }
                return ['no_akun' => '1413.03', 'nama_akun' => 'log core 260 WJY'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1411.03', 'nama_akun' => 'Kayu Lunak 130 WJY'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1411.07', 'nama_akun' => 'kayu Lunak 230 WJY'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1411.08', 'nama_akun' => 'kayu Lunak 100 WJY'];
                }
                return ['no_akun' => '1411.01', 'nama_akun' => 'kayu Lunak 260 WJY'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1411.04', 'nama_akun' => 'Kayu Keras 130 WJY'];
            }
            return ['no_akun' => '1411.02', 'nama_akun' => 'Kayu Keras 260 WJY'];
        }
    }

    public function collection()
    {
        $flatRows = [];
        $currentRow = 1;

        foreach ($this->jurnalTables as $table) {
            $noJurnal = "MASUK/" . \Carbon\Carbon::parse($table['tgl_kayu_masuk'])->format('Ymd') . "/" . $table['no_nota'];
            $tglVal = \Carbon\Carbon::parse($table['tgl_kayu_masuk'])->format('d/m/Y');

            // Table Header Block
            $flatRows[] = ['No. Jurnal: ' . $noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
            
            // Column Headers
            $flatRows[] = [
                'Nama Akun',
                'tgl',
                'jur',
                'No Akun',
                'No',
                'mm',
                'Nama Suplier',
                'Lahan',
                'm',
                'hit kbk',
                'Banyak',
                'M3',
                'Harga',
                'Total'
            ];
            $currentRow++;

            // 1. Add Debit entries from groups
            foreach ($table['groups'] as $group) {
                $jenisKayuNama = $group['jenis'] ?? '';
                $panjang = $group['panjang'] ?? 130;
                $kodeLahan = $group['kode_lahan'] ?? '';

                // Graceful fallback parsing in case of old serialized Livewire component state
                if (empty($jenisKayuNama) && !empty($group['header'])) {
                    $header = $group['header'];
                    
                    if (preg_match('/(\d+)\s*cm/', $header, $matches)) {
                        $panjang = (int) $matches[1];
                    }
                    
                    if (preg_match('/cm\s+(.+?)\s+\(/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    } elseif (preg_match('/cm\s+(.+)$/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    }
                }

                if (empty($kodeLahan) && !empty($group['header'])) {
                    $tokens = preg_split('/\s+/', trim($group['header']));
                    if (!empty($tokens)) {
                        $kodeLahan = $tokens[0];
                    }
                }

                $acc = $this->getAccountDetails($jenisKayuNama, $panjang);

                $hargaVal = $group['total_kubikasi'] > 0 ? (float)($group['total_harga'] / $group['total_kubikasi']) : 0.0;
                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

                $flatRows[] = [
                    $acc['nama_akun'],
                    $tglVal,
                    '',
                    $acc['no_akun'],
                    $table['seri'],
                    '',
                    $table['nama_supplier'],
                    $kodeLahan,
                    'd',
                    'm',
                    $group['total_batang'],
                    $group['total_kubikasi'],
                    $hargaVal,
                    $totalVal
                ];
                $currentRow++;
            }

            // 2. Add Credit Row 1: hutang ongkos turun kayu
            $totalValRow1 = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                'hutang ongkos turun kayu',
                $tglVal,
                '',
                '2400.01',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                '',
                '',
                $table['selisih'],
                $totalValRow1
            ];
            $currentRow++;

            // 3. Add Credit Row 2: pendapatan
            $flatRows[] = [
                'pendapatan',
                $tglVal,
                '',
                '4000.00',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                '',
                '',
                '',
                ''
            ];
            $currentRow++;

            // 4. Add Credit Row 3: Kas Mut
            $totalValKasMut = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                'Kas Mut',
                $tglVal,
                '',
                '1111.00',
                $table['seri'],
                '',
                $table['nama_supplier'],
                '',
                'k',
                '',
                $table['totalBatang'],
                $table['totalKubikasi'],
                $table['hargaFinal'],
                $totalValKasMut
            ];
            $currentRow++;

            // Spacer Rows between multiple tables
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
        }

        return collect($flatRows);
    }

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            if (str_starts_with((string)$cellValue, 'No. Jurnal:')) {
                // Style the Title block of each table
                $sheet->mergeCells("A{$row}:N{$row}");
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['argb' => 'FF1D2939']
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFD2E4F0']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif ($cellValue === 'Nama Akun') {
                // Style Table Column Headers
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E8EB']
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            } elseif (!empty($cellValue)) {
                // Style data rows
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);

                // Alignments
                $sheet->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$row}:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Formats
                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("M{$row}:N{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // Widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10); // mm
        $sheet->getColumnDimension('G')->setWidth(25); // Nama Suplier
        $sheet->getColumnDimension('H')->setWidth(10); // Lahan
        $sheet->getColumnDimension('I')->setWidth(10); // m
        $sheet->getColumnDimension('J')->setWidth(12); // hit kbk
        $sheet->getColumnDimension('K')->setWidth(12); // Banyak
        $sheet->getColumnDimension('L')->setWidth(15); // M3
        $sheet->getColumnDimension('M')->setWidth(18); // Harga
        $sheet->getColumnDimension('N')->setWidth(18); // Total

        return [];
    }
}
