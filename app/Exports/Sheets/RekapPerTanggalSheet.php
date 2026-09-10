<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class RekapPerTanggalSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function title(): string
    {
        return 'Rekap Per Tanggal';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['REKAP PER TANGGAL'];
        $rows[] = [];
        $rows[] = ['Tanggal', 'Pagi', 'Malam', 'Total'];

        $totalPagi = 0;
        $totalMalam = 0;
        $totalAll = 0;

        foreach ($this->data as $r) {
            $rows[] = [
                Carbon::parse($r['tanggal'])->format('d-m-Y'),
                $r['pagi'],
                $r['malam'],
                $r['total'],
            ];
            $totalPagi += $r['pagi'];
            $totalMalam += $r['malam'];
            $totalAll += $r['total'];
        }

        $rows[] = ['TOTAL', $totalPagi, $totalMalam, $totalAll];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4; // judul + blank + header + rows + total

        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle('A3:D3')->getFont()->setBold(true)->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE)
        );
        $sheet->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle('A3:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A{$lastRow}:D{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$lastRow}:D{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

        $sheet->getStyle("B4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A4:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $lastRow = count($this->data) + 4;
                $event->sheet->getDelegate()
                    ->getStyle("A3:D{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            },
        ];
    }
}