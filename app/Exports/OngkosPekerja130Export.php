<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class OngkosPekerja130Export implements FromCollection, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $laporanOngkos;

    public function __construct(array $laporanOngkos)
    {
        $this->laporanOngkos = $laporanOngkos;
    }

    public function collection()
    {
        $rows = collect();
        $groupedLaporan = collect($this->laporanOngkos)->groupBy('kategori_mesin');

        // Header sesuai template gambar tanpa solasi
        $standardHeadings = [
            'TANGGAL',
            'P',
            'L',
            'T',
            'JENIS',
            'KW1',
            'KW2',
            'KW3',
            'KW4',
            'BANYAK',
            'M3',
            'TOTAL PEKERJA',
            'HARGA',
            'ONGKOS PER M3',
            'ONGKOS MESIN',
            'TOTAL PER M3+MESIN',
            'ONGKOS PER LBR',
            'KETERANGAN'
        ];

        foreach ($groupedLaporan as $kategori => $items) {
            $rows->push(['KATEGORI: ' . strtoupper($kategori)]);
            $rows->push($standardHeadings);

            foreach ($items as $row) {
                $rows->push($row);
            }

            // Grand Total
            $rows->push([
                'tanggal' => 'GRAND TOTAL',
                'p' => null,
                'l' => null,
                't' => null,
                'jenis' => null,
                'kw1' => null,
                'kw2' => null,
                'kw3' => null,
                'kw4' => null,
                'byk' => (int)$items->sum('byk'),
                'm3' => (float)round($items->sum('m3'), 4),
                'ttl_pkj' => (int)$items->unique('tanggal')->sum('ttl_pkj'),
                'harga' => null,
                'ongkos_per_m3' => (float)round($items->sum('ongkos_per_m3')),
                'ongkos_mesin' => null,
                'ongkos_m3_mesin' => (float)round($items->sum('ongkos_m3_mesin')),
                'ongkos_per_lb' => (float)round($items->sum('ongkos_per_lb')),
                'ket' => null
            ]);
            $rows->push([]);
        }

        return $rows;
    }

    public function map($row): array
    {
        if (!isset($row['tanggal']) && !isset($row['ongkos_per_m3'])) {
            return $row;
        }

        return [
            $row['tanggal'],
            $row['p'] ?? 0,
            $row['l'] ?? 0,
            $row['t'] ?? 0,
            strtoupper($row['jenis'] ?? ''),
            (int)($row['kw1'] ?? 0),
            (int)($row['kw2'] ?? 0),
            (int)($row['kw3'] ?? 0),
            (int)($row['kw4'] ?? 0),
            (int)($row['byk'] ?? 0),
            (float)round(($row['m3'] ?? 0), 4),
            (int)($row['ttl_pkj'] ?? 0),
            (float)round($row['harga'] ?? 0),
            (float)round($row['ongkos_per_m3'] ?? 0),
            (float)round($row['ongkos_mesin'] ?? 0),
            (float)round($row['ongkos_m3_mesin'] ?? 0),
            (float)round($row['ongkos_per_lb'] ?? 0),
            $row['ket'] ?? '-'
        ];
    }

    /**
     * PERBAIKAN: Nama method harus 'columnFormats'
     */
    public function columnFormats(): array
    {
        return [
            'K' => '0.0000', // Kolom M3
            'M' => '#,##0',  // Kolom Harga
            'N' => '#,##0',  // Ongkos M3
            'P' => '#,##0',  // M3 + Mesin
            'Q' => '#,##0',  // Ongkos per Lbr
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $currentDate = null;
        $startRow = 0;

        // Kolom yang di-merge: Tanggal(A), Pekerja(L), Harga(M), OngkosM3(N), Mesin(O), M3+Mesin(P), Lbr(Q), Ket(R)
        $mergeCols = ['A', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'];

        for ($i = 1; $i <= $lastRow; $i++) {
            $cellValue = (string)$sheet->getCell('A' . $i)->getValue();

            if (!empty($cellValue) && !in_array($cellValue, ['TANGGAL', 'GRAND TOTAL']) && !str_contains($cellValue, 'KATEGORI:')) {
                if ($cellValue !== $currentDate) {
                    if ($startRow !== 0 && ($i - 1) > $startRow) {
                        foreach ($mergeCols as $col) {
                            $sheet->mergeCells("{$col}{$startRow}:{$col}" . ($i - 1));
                        }
                    }
                    $currentDate = $cellValue;
                    $startRow = $i;
                }
            } else {
                if ($startRow !== 0 && ($i - 1) > $startRow) {
                    foreach ($mergeCols as $col) {
                        $sheet->mergeCells("{$col}{$startRow}:{$col}" . ($i - 1));
                    }
                }
                $startRow = 0;
                $currentDate = null;
            }

            // Styling Header & Grand Total (Sama dengan 260)
            if (str_contains($cellValue, 'KATEGORI:')) {
                $sheet->mergeCells("A{$i}:R{$i}");
                $sheet->getStyle("A{$i}")->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]]);
            }
            if ($cellValue === 'TANGGAL') {
                $sheet->getStyle("A{$i}:R{$i}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1A1A']]]);
            }
            if ($cellValue === 'GRAND TOTAL') {
                $sheet->getStyle("A{$i}:R{$i}")->getFont()->setBold(true);
                $sheet->getStyle("N{$i}:Q{$i}")->getFont()->getColor()->setRGB('EA580C');
            }
        }

        $sheet->getStyle("A1:R{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:R{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }
}
