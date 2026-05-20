<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanGuellotineExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    public function __construct(
        protected array  $data,
        protected string $tanggal
    ) {}

    public function collection()
    {
        $rows    = collect();
        $allRows = $this->data;

        // Grand Total — Row 2 (kuning)
        $grandByk = collect($allRows)->sum('byk');
        $grandM3  = round(collect($allRows)->sum('m3'), 3);
        $grandPkj = collect($allRows)->max('ttl_pkj'); // max karena PKJ sama per produksi

        $rows->push([
            '',         // A - Tanggal
            '',         // B - p
            '',         // C - l
            '',         // D - t
            '',         // E - jenis
            $grandByk,  // F - byk
            $grandM3,   // G - m3
            $grandPkj,  // H - TTL PKJ
        ]);

        // Data rows mulai Row 3
        foreach ($allRows as $row) {
            $rows->push([
                $row['tanggal'],  // A
                $row['p'],        // B
                $row['l'],        // C
                $row['t'],        // D
                $row['jenis'],    // E
                $row['byk'],      // F
                $row['m3'],       // G
                $row['ttl_pkj'],  // H
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'p', 'l', 't', 'jenis', 'byk', 'm3', 'TTL PKJ'];
    }

    public function title(): string
    {
        return 'Laporan Guellotine';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Row 1: Header biru
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2: Grand Total kuning
                $sheet->getStyle('A2:H2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 3+: Data
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:H{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Kolom A rata kiri
                $sheet->getStyle("A2:A{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Auto-size
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
