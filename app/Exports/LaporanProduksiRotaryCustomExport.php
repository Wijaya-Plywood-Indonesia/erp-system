<?php

namespace App\Exports;

use App\Models\ProduksiRotary;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanProduksiRotaryCustomExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $tanggal;
    protected $mergeData = [];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $produksis = ProduksiRotary::with([
            'mesin',
            'detailPaletRotary.ukuran',
            'detailPaletRotary.penggunaanLahan.jenisKayu',
            'detailPegawaiRotary'
        ])
            ->whereDate('tgl_produksi', $this->tanggal)
            ->get();

        $rows = collect();
        $currentRow = 2; // Data starts at row 2
        $this->mergeData = [];

        foreach ($produksis as $index => $produksi) {
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '-');
            $tgl = Carbon::parse($produksi->tgl_produksi)->format('d/m/Y');
            $totalPekerja = $produksi->detailPegawaiRotary->count();
            
            // Group detail palet by size and wood type
            $details = $produksi->detailPaletRotary->groupBy(function($item) {
                $u = $item->ukuran;
                $j = $item->penggunaanLahan->jenisKayu->kode_kayu ?? '-';
                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $j;
            });

            $groupSize = $details->count();
            if ($groupSize > 0) {
                $this->mergeData[] = [
                    'start' => $currentRow,
                    'end' => $currentRow + $groupSize - 1,
                ];

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
                        'mesin' => $namaMesin,
                        'tanggal' => $tgl,
                        'p' => $p,
                        'l' => $l,
                        't' => $t,
                        'jenis' => $jenis,
                        'kw1' => $kw1 ?: '',
                        'kw2' => $kw2 ?: '',
                        'kw3' => $kw3 ?: '',
                        'kw4' => $kw4 ?: '',
                        'ttl_pkj' => $totalPekerja,
                    ]);
                    $currentRow++;
                }

                // Add empty row after each machine group (except the last one)
                if ($index < $produksis->count() - 1) {
                    $rows->push(['', '', '', '', '', '', '', '', '', '', '']);
                    $currentRow++;
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Mesin',
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
                $peachColor = 'F9CB9C';

                // Headers styling
                $sheet->getStyle('A1:K1')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'font' => ['bold' => true],
                ]);

                // Apply styling for each machine block
                foreach ($this->mergeData as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    $blockRange = "A{$start}:K{$end}";

                    // Merge cells
                    if ($start < $end) {
                        $sheet->mergeCells("A{$start}:A{$end}");
                        $sheet->mergeCells("K{$start}:K{$end}");
                    }

                    // Block borders and alignment
                    $sheet->getStyle($blockRange)->applyFromArray([
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

                    // Peach background
                    $sheet->getStyle("A{$start}:B{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("F{$start}:K{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    
                    // Bold font for certain columns
                    $sheet->getStyle("A{$start}:B{$end}")->getFont()->setBold(true);
                    $sheet->getStyle("K{$start}:K{$end}")->getFont()->setBold(true);
                }

                $sheet->getRowDimension(1)->setRowHeight(30);

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(8);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(10);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(10);
                $sheet->getColumnDimension('K')->setWidth(12);
            },
        ];
    }
}
