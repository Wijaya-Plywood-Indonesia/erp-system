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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class RekapPerUkuranSheet implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected array $data;
    protected array $daftarUkuran;

    public function __construct(array $data, array $daftarUkuran)
    {
        $this->data = $data;
        $this->daftarUkuran = $daftarUkuran;
    }

    public function title(): string
    {
        return 'Rekap Per Ukuran';
    }

    protected function totalCols(): int
    {
        // Tanggal, Shift, ...ukuran..., Total
        return 2 + count($this->daftarUkuran) + 1;
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['REKAP PER TANGGAL, PER SHIFT, PER UKURAN (p x l x t)'];
        $rows[] = [];

        $header = ['Tanggal', 'Shift'];
        foreach ($this->daftarUkuran as $u) {
            $header[] = $u;
        }
        $header[] = 'Total';
        $rows[] = $header;

        $totalPerUkuran = array_fill_keys($this->daftarUkuran, 0);
        $grandTotal = 0;

        foreach ($this->data as $r) {
            $row = [
                Carbon::parse($r['tanggal'])->format('d-m-Y'),
                $r['shift'],
            ];
            foreach ($this->daftarUkuran as $u) {
                $qty = $r['ukuran'][$u] ?? 0;
                $row[] = $qty;
                $totalPerUkuran[$u] += $qty;
            }
            $row[] = $r['total'];
            $grandTotal += $r['total'];

            $rows[] = $row;
        }

        $totalRow = ['TOTAL', ''];
        foreach ($this->daftarUkuran as $u) {
            $totalRow[] = $totalPerUkuran[$u];
        }
        $totalRow[] = $grandTotal;
        $rows[] = $totalRow;

        return $rows;
    }

    /**
     * Hitung baris (relatif terhadap data, 0-based) tempat tiap tanggal mulai & berapa baris (rowspan).
     * Return: [ ['start' => n, 'span' => m], ... ] hanya untuk tanggal dengan span > 1.
     */
    protected function tanggalMergeRanges(): array
    {
        $ranges = [];
        $currentTanggal = null;
        $startIndex = null;
        $count = 0;

        foreach ($this->data as $i => $r) {
            if ($r['tanggal'] !== $currentTanggal) {
                if ($currentTanggal !== null && $count > 1) {
                    $ranges[] = ['start' => $startIndex, 'span' => $count];
                }
                $currentTanggal = $r['tanggal'];
                $startIndex = $i;
                $count = 1;
            } else {
                $count++;
            }
        }
        if ($currentTanggal !== null && $count > 1) {
            $ranges[] = ['start' => $startIndex, 'span' => $count];
        }

        return $ranges;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = Coordinate::stringFromColumnIndex($this->totalCols());
        $lastRow = count($this->data) + 4; // judul + blank + header + rows + total

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE)
        );
        $sheet->getStyle("A3:{$lastCol}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle("A3:{$lastCol}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

        $sheet->getStyle("C4:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A4:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        // ── Merge kolom Tanggal (kolom A) untuk tanggal dengan 2 shift atau lebih ──
        // Baris data dimulai dari row 4 (row 1=judul, 2=blank, 3=header)
        foreach ($this->tanggalMergeRanges() as $range) {
            $rowAwal = 4 + $range['start'];
            $rowAkhir = $rowAwal + $range['span'] - 1;
            $sheet->mergeCells("A{$rowAwal}:A{$rowAkhir}");
            $sheet->getStyle("A{$rowAwal}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $lastCol = Coordinate::stringFromColumnIndex($this->totalCols());
                $lastRow = count($this->data) + 4;
                $event->sheet->getDelegate()
                    ->getStyle("A3:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}