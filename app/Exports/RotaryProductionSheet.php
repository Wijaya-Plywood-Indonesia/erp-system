<?php

namespace App\Exports;

use App\Models\ProduksiRotary;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RotaryProductionSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;
    protected $type;
    protected $mesinId;
    protected $mesinNama;

    public function __construct($tanggal, $type, $mesinId, $mesinNama)
    {
        $this->tanggal = $tanggal;
        $this->type = $type;
        $this->mesinId = $mesinId;
        $this->mesinNama = $mesinNama;
    }

    public function title(): string
    {
        return $this->mesinNama;
    }

    public function collection()
    {
        $query = ProduksiRotary::with([
            'detailPaletRotary.ukuran',
            'detailPaletRotary.penggunaanLahan.jenisKayu',
            'detailPegawaiRotary'
        ])
        ->where('id_mesin', $this->mesinId);

        if ($this->type === 'monthly') {
            $startDate = Carbon::parse($this->tanggal)->startOfMonth();
            $endDate = Carbon::parse($this->tanggal)->endOfMonth();
            $query->whereBetween('tgl_produksi', [$startDate, $endDate]);
        } else {
            $query->whereDate('tgl_produksi', $this->tanggal);
        }

        $produksis = $query->orderBy('tgl_produksi')->get();

        $rows = collect();
        
        foreach ($produksis as $produksi) {
            $tgl = Carbon::parse($produksi->tgl_produksi)->format('j-M');
            $totalPekerja = $produksi->detailPegawaiRotary->count();
            
            $details = $produksi->detailPaletRotary->groupBy(function($item) {
                $u = $item->ukuran;
                $j = $item->penggunaanLahan->jenisKayu->kode_kayu ?? '-';
                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $j;
            });

            $firstRowInGroup = true;
            foreach ($details as $key => $items) {
                [$p, $l, $t, $jenis] = explode('|', $key);
                
                $p = str_replace('.', ',', (string)$p);
                $l = str_replace('.', ',', (string)$l);
                $t = str_replace('.', ',', (string)$t);

                $kw1 = $items->where('kw', 1)->sum('total_lembar');
                $kw2 = $items->where('kw', 2)->sum('total_lembar');
                $kw3 = $items->where('kw', 3)->sum('total_lembar');
                $kw4 = $items->where('kw', 4)->sum('total_lembar');

                $rows->push([
                    'tanggal' => $firstRowInGroup ? $tgl : '',
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => $jenis,
                    'kw1' => $kw1 ?: '',
                    'kw2' => $kw2 ?: '',
                    'kw3' => $kw3 ?: '',
                    'kw4' => $kw4 ?: '',
                    'ttl_pkj' => $firstRowInGroup ? $totalPekerja : '',
                ]);
                $firstRowInGroup = false;
            }
        }

        for ($i = 0; $i < 10; $i++) {
            $rows->push(['', '', '', '', '', '', '', '', '', '']);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'p',
            'l',
            't',
            'jenis',
            'kw1',
            'kw2',
            'kw3',
            'kw4',
            'TTL PKJ'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $peachColor = 'F9CB9C';

                $sheet->getStyle('A1:J' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(30);

                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(8);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(10);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(12);

                $sheet->getStyle('A2:A' . $highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                $sheet->getStyle('E2:E' . $highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                $sheet->getStyle('F2:I' . $highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                $sheet->getStyle('J2:J' . $highestRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                
                $sheet->getStyle('A2:A' . $highestRow)->getFont()->setBold(true);
                $sheet->getStyle('J2:J' . $highestRow)->getFont()->setBold(true);
            },
        ];
    }
}
