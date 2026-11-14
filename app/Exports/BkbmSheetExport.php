<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Lembar Excel sederhana untuk menu BK/BM baru: judul kolom tebal, baris merah untuk yang perlu perhatian. */
class BkbmSheetExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    /** @param array<int, array> $rows  @param array<int, string> $headings  @param array<int, int> $barisMerah nomor urut baris data (mulai 0) */
    public function __construct(private array $rows, private array $headings, private array $barisMerah = []) {}

    public function array(): array { return $this->rows; }

    public function headings(): array { return $this->headings; }

    public function styles(Worksheet $sheet): array
    {
        $kolomAkhir = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings));
        $sheet->getStyle("A1:{$kolomAkhir}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$kolomAkhir}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->freezePane('A2');
        foreach ($this->barisMerah as $i) {
            $r = $i + 2; // baris 1 = judul kolom
            $sheet->getStyle("A{$r}:{$kolomAkhir}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FECACA');
        }
        return [];
    }
}