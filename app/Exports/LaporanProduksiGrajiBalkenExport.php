<?php

namespace App\Exports;

use App\Models\HasilGrajiBalken;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LaporanProduksiGrajiBalkenExport implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $data = HasilGrajiBalken::with(['produksiGrajiBalken', 'ukuran', 'jenisKayu'])
            ->whereHas('produksiGrajiBalken', function($q) {
                $q->whereDate('tanggal_produksi', $this->tanggal);
            })
            ->get();

        $rows = collect();
        foreach ($data as $item) {
            $u = $item->ukuran;
            $p = $u->panjang ?? 0;
            $l = $u->lebar ?? 0;
            $t = $u->tebal ?? 0;
            $banyak = $item->jumlah;
            $m3 = ($p * $l * $t * $banyak) / 10000000;

            $rows->push([
                'tanggal' => Carbon::parse($item->produksiGrajiBalken->tanggal_produksi)->format('d-M-Y'),
                'p' => $p,
                'l' => $l,
                't' => $t,
                'jenis' => $item->jenisKayu->nama_kayu ?? '',
                'banyak' => $banyak,
                'm3' => round($m3, 2),
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tanggal', 'p', 'l', 't', 'jenis', 'banyak', 'm3'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:G" . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Grey background for 'jenis' and 'banyak' as in the user's image
                $sheet->getStyle("E1:F" . $lastRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('E0E0E0');

                $sheet->getStyle('G2:G' . $lastRow)->getNumberFormat()->setFormatCode('0.00');
            },
        ];
    }

    public function title(): string
    {
        return 'Produksi Graji Balken';
    }
}
