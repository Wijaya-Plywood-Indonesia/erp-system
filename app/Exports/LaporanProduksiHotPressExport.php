<?php

namespace App\Exports;

use App\Models\ProduksiHp;
use App\Models\BahanPenolongHp;
use App\Models\BahanPenolongProduksi;
use App\Models\HargaPegawai;
use App\Models\TriplekHasilHp;
use App\Models\PlatformHasilHp;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanProduksiHotPressExport implements WithMultipleSheets
{
    protected $tanggal;
    protected $data;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiHotPressRekapSheet($this->tanggal),
            new LaporanProduksiHotPressDetailSheet($this->data),
        ];
    }
}

class LaporanProduksiHotPressDetailSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = collect($data);
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->data as $item) {
            $rows->push([
                'Mesin' => strtoupper($item['machine']),
                'Tanggal' => $item['tanggal'],
            ]);

            $rows->push(['HASIL PRODUKSI']);
            $rows->push(['No Palet', 'P', 'L', 'T', 'Banyak (Isi)', 'Nama Barang', 'Kubikasi', 'Tipe']);
            
            foreach ($item['hasil'] as $hasil) {
                $rows->push([
                    $hasil['no_palet'],
                    $hasil['p'],
                    $hasil['l'],
                    $hasil['t'],
                    $hasil['isi'],
                    $hasil['nama_barang'],
                    $hasil['kubikasi'],
                    $hasil['tipe'],
                ]);
            }

            $rows->push(['BAHAN PENOLONG / BIAYA']);
            $rows->push(['Kategori', 'Nama Bahan', 'Jumlah', 'Harga', 'Total']);
            foreach ($item['material_usage'] as $bp) {
                $rows->push([
                    $bp['kategori'],
                    $bp['nama'],
                    $bp['banyak'],
                    $bp['harga'],
                    $bp['total']
                ]);
            }

            $rows->push(['INFORMASI BIAYA LAIN']);
            $rows->push(['Pekerja', $item['total_pekerja'] . ' orang', 'Rate: ' . $item['harga_pekerja']]);
            $rows->push(['Penyusutan', 'Rp ' . number_format($item['penyusutan'])]);
            $rows->push(['Bulanan', 'Rp ' . number_format($item['bulanan'])]);

            $rows->push([]); // Space
            $rows->push([]);
        }
        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string { return 'Detail Produksi'; }
    public function styles(Worksheet $sheet) { return []; }
}

class LaporanProduksiHotPressRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $tanggalObj = Carbon::parse($this->tanggal);
        $tglStr = $tanggalObj->format('d F Y');

        $produksis = ProduksiHp::with([
            'bahanPenolongHp',
            'triplekHasilHp.ukuran',
            'triplekHasilHp.jenisKayu',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.mesin',
            'platformHasilHp.ukuran',
            'platformHasilHp.jenisKayu',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.mesin',
            'detailPegawaiHp'
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $bahanPenolongList = BahanPenolongProduksi::where('kategori_produksi', 'hot_press')->get();
        $hargaPegawai = HargaPegawai::first()->harga ?? 115000;

        $rows = collect();

        // We'll iterate through each machine's production for the day
        // Group results by machine
        $groupedByMachine = $produksis->groupBy(function($p) {
            return 'HOTPRESS ' . strtoupper($p->shift) . ' BESAR';
        });

        $finalRows = [];
        $no = 1;

        foreach ($groupedByMachine as $machineName => $records) {
            $materialUsage = [];
            // Group materials by category
            $dempulNames = ['Kalsium', 'Semen putih', 'Tepung', 'Lem PVAC', 'lem Dempul', 'Semen'];
            
            foreach ($bahanPenolongList as $bp) {
                $sum = 0;
                foreach ($records as $prod) {
                    $sum += $prod->bahanPenolongHp->where('nama_bahan', $bp->nama_bahan_penolong)->sum('jumlah');
                }
                
                $category = 'Bahan';
                foreach($dempulNames as $dn) {
                    if (stripos($bp->nama_bahan_penolong, $dn) !== false) {
                        $category = 'Bahan Dempul';
                        break;
                    }
                }

                $materialUsage[] = [
                    'mesin' => $machineName,
                    'tgl' => $tglStr,
                    'kategori' => $category,
                    'bahan' => $bp->nama_bahan_penolong,
                    'banyak' => $sum,
                    'harga' => $bp->harga ?: 0,
                    'total' => $sum * ($bp->harga ?: 0),
                ];
            }

            // Other Costs
            $totalPekerja = 0;
            foreach ($records as $prod) {
                $totalPekerja += $prod->detailPegawaiHp->count();
            }
            
            $otherCosts = [
                ['nama' => 'Penyusutan', 'banyak' => 3, 'harga' => 635000, 'total' => 3 * 635000],
                ['nama' => 'Bulanan', 'banyak' => 1, 'harga' => 220000, 'total' => 220000],
                ['nama' => 'Pekerja', 'banyak' => $totalPekerja, 'harga' => $hargaPegawai, 'total' => $totalPekerja * $hargaPegawai],
            ];

            foreach ($otherCosts as $oc) {
                $materialUsage[] = [
                    'mesin' => $machineName,
                    'tgl' => $tglStr,
                    'kategori' => 'Biaya Lain Lain',
                    'bahan' => $oc['nama'],
                    'banyak' => $oc['banyak'],
                    'harga' => $oc['harga'],
                    'total' => $oc['total'],
                ];
            }

            // Production Output
            $outputItems = [];
            foreach ($records as $prod) {
                foreach ($prod->triplekHasilHp as $item) {
                    $u = $item->barangSetengahJadi->ukuran ?? null;
                    $p = $u->panjang ?? 0;
                    $l = $u->lebar ?? 0;
                    $t = $u->tebal ?? 0;
                    $banyak = $item->isi;
                    $kubikasi = ($p * $l * $t * $banyak) / 1000000000; // Formula adjustment for 0,0388 style

                    $outputItems[] = [
                        'no' => $no++,
                        'mesin' => strtoupper($prod->shift) == 'PAGI' ? 'HOTPRESS PAGI' : 'HOTPRESS 2', // example mapping
                        'tgl' => $tglStr,
                        'p' => $p,
                        'l' => $l,
                        't' => $t,
                        'banyak' => $banyak,
                        'jenis_kayu' => $item->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? '-',
                        'kwalitas' => $item->barangSetengahJadi->grade->nama_grade ?? '-',
                        'kubikasi' => round($kubikasi, 4),
                    ];
                }
                foreach ($prod->platformHasilHp as $item) {
                    $u = $item->barangSetengahJadi->ukuran ?? null;
                    $p = $u->panjang ?? 0;
                    $l = $u->lebar ?? 0;
                    $t = $u->tebal ?? 0;
                    $banyak = $item->isi;
                    $kubikasi = ($p * $l * $t * $banyak) / 1000000000;

                    $outputItems[] = [
                        'no' => $no++,
                        'mesin' => strtoupper($prod->shift) == 'PAGI' ? 'HOTPRESS PAGI' : 'HOTPRESS 2',
                        'tgl' => $tglStr,
                        'p' => $p,
                        'l' => $l,
                        't' => $t,
                        'banyak' => $banyak,
                        'jenis_kayu' => $item->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? '-',
                        'kwalitas' => $item->barangSetengahJadi->grade->nama_grade ?? '-',
                        'kubikasi' => round($kubikasi, 4),
                    ];
                }
            }

            // Combine into final rows
            $max = max(count($materialUsage), count($outputItems));
            for ($i = 0; $i < $max; $i++) {
                $row = [];
                // Left
                if ($i < count($materialUsage)) {
                    $m = $materialUsage[$i];
                    $row['m_mesin'] = $m['mesin'];
                    $row['m_tgl'] = $m['tgl'];
                    $row['m_kat'] = $m['kategori'];
                    $row['m_bahan'] = $m['bahan'];
                    $row['m_banyak'] = $m['banyak'];
                    $row['m_harga'] = $m['harga'];
                    $row['m_total'] = $m['total'];
                } else {
                    $row['m_mesin'] = $row['m_tgl'] = $row['m_kat'] = $row['m_bahan'] = $row['m_banyak'] = $row['m_harga'] = $row['m_total'] = '';
                }

                $row['spacer'] = '';

                // Right
                if ($i < count($outputItems)) {
                    $o = $outputItems[$i];
                    $row['o_no'] = $o['no'];
                    $row['o_mesin'] = $o['mesin'];
                    $row['o_tgl'] = $o['tgl'];
                    $row['o_p'] = $o['p'];
                    $row['o_l'] = $o['l'];
                    $row['o_t'] = $o['t'];
                    $row['o_banyak'] = $o['banyak'];
                    $row['o_jenis_kayu'] = $o['jenis_kayu'];
                    $row['o_kwalitas'] = $o['kwalitas'];
                    $row['o_kubikasi'] = $o['kubikasi'];
                } else {
                    $row['o_no'] = $row['o_mesin'] = $row['o_tgl'] = $row['o_p'] = $row['o_l'] = $row['o_t'] = $row['o_banyak'] = $row['o_jenis_kayu'] = $row['o_kwalitas'] = $row['o_kubikasi'] = '';
                }
                $rows->push($row);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Mesin', 'Tgl', 'Kategori Bahan', 'BAHAN', 'BANYAK', 'HARGA', 'TOTAL', 
            '', 
            'NO', 'Mesin', 'TGL', 'P', 'L', 'T', 'BANYAK', 'Jenis Kayu', 'Kwalitas', 'Kubikasi'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        $sheet->getStyle('A1:R1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:G" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("I1:R" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Light Yellow for Total column
                $sheet->getStyle("G2:G" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2CC');
                
                // Light Blue for Right side dimensions
                $sheet->getStyle("L2:O" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DDEBF7');

                // Number formatting
                $sheet->getStyle('F2:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('R2:R' . $lastRow)->getNumberFormat()->setFormatCode('0.0000');

                // Widths
                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(5); // Spacer
                $sheet->getColumnDimension('I')->setWidth(5);
                $sheet->getColumnDimension('J')->setWidth(15);
                $sheet->getColumnDimension('K')->setWidth(15);
                $sheet->getColumnDimension('L')->setWidth(8);
                $sheet->getColumnDimension('M')->setWidth(8);
                $sheet->getColumnDimension('N')->setWidth(8);
                $sheet->getColumnDimension('O')->setWidth(10);
                $sheet->getColumnDimension('P')->setWidth(15);
                $sheet->getColumnDimension('Q')->setWidth(15);
                $sheet->getColumnDimension('R')->setWidth(12);
            },
        ];
    }

    public function title(): string { return 'Rekap Hot Press'; }
}
