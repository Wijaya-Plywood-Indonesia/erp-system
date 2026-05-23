<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPilihPlywoodExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $data;
    protected $tanggal;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $rows = collect();
        $detailProduksi = $this->data['detail'] ?? [];
        $summaryProduksi = $this->data['summary'] ?? [];

        $max = max(count($detailProduksi), count($summaryProduksi));

        for ($i = 0; $i < $max; $i++) {
            $row = [];
            
            // Left side (Detail)
            if ($i < count($detailProduksi)) {
                $d = $detailProduksi[$i];
                $row['d_tgl'] = $d['tanggal'];
                $row['d_p'] = $d['p'];
                $row['d_l'] = $d['l'];
                $row['d_t'] = $d['t'];
                $row['d_jenis'] = $d['jenis'];
                $row['d_bagus'] = $d['bagus'];
                $row['d_cacat'] = $d['cacat'];
                $row['d_total'] = $d['total'];
                $row['d_m3'] = ''; 
            } else {
                $row['d_tgl'] = $row['d_p'] = $row['d_l'] = $row['d_t'] = $row['d_jenis'] = $row['d_bagus'] = $row['d_cacat'] = $row['d_total'] = $row['d_m3'] = '';
            }

            $row['spacer'] = ''; 

            // Right side (Summary)
            if ($i < count($summaryProduksi)) {
                $s = $summaryProduksi[$i];
                $row['s_tgl'] = $s['tanggal'];
                $row['s_ttl_pkj'] = $s['ttl_pkj'];
                $row['s_harga'] = ''; 
                $row['s_total_m3'] = ''; 
                $row['s_ongkos_m3'] = ''; 
                $row['s_ongkos_lb'] = ''; 
            } else {
                $row['s_tgl'] = $row['s_ttl_pkj'] = $row['s_harga'] = $row['s_total_m3'] = $row['s_ongkos_m3'] = $row['s_ongkos_lb'] = '';
            }

            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'p', 'l', 't', 'jenis', 'Bagus', 'Cacat', 'Total', 'm3',
            '', 
            'Tanggal', 'TTL PKJ', 'HARGA', 'Total m3', 'ONGKOS PER M3', 'ONGKOS PER LB'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:I" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("K1:P" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("J1:J" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');
                $sheet->getStyle('O1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(8);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->getColumnDimension('F')->setWidth(10); // Bagus
                $sheet->getColumnDimension('G')->setWidth(10); // Cacat
                $sheet->getColumnDimension('H')->setWidth(10); // Total
                $sheet->getColumnDimension('I')->setWidth(10); // m3
                $sheet->getColumnDimension('J')->setWidth(3);  // Spacer
                $sheet->getColumnDimension('K')->setWidth(15); // Tanggal
                $sheet->getColumnDimension('L')->setWidth(10); // TTL PKJ
                $sheet->getColumnDimension('M')->setWidth(15); // HARGA
                $sheet->getColumnDimension('N')->setWidth(15); // Total m3
                $sheet->getColumnDimension('O')->setWidth(18); // ONGKOS PER M3
                $sheet->getColumnDimension('P')->setWidth(18); // ONGKOS PER LB
            },
        ];
    }
}
