<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
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

class LaporanPilihPlywoodExport implements WithMultipleSheets
{
    protected $data;
    protected $tanggal;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanPilihPlywoodPotonganGajiSheet($this->tanggal),
            new LaporanPilihPlywoodProduksiSheet($this->data),
        ];
    }
}

class LaporanPilihPlywoodPotonganGajiSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected $tanggal;
    protected $mergeRanges = [];
    protected $tableRanges = [];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $produksiList = \App\Models\ProduksiPilihPlywood::with([
            'pegawaiPilihPlywood.pegawai',
            'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
            'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
            'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang'
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        $tanggalFormatted = Carbon::parse($this->tanggal)->format('d/m/Y');

        foreach ($produksiList as $prod) {
            $pekerja = $prod->pegawaiPilihPlywood ?? [];
            $N = count($pekerja);

            // Calculate actual production
            $totalActual = 0;
            if ($prod->hasilPilihPlywood) {
                foreach ($prod->hasilPilihPlywood as $hasil) {
                    $totalActual += $hasil->jumlah_bagus ?? 0;
                }
            }

            // Determine dominant item target
            $target = 450;
            $maxQty = -1;
            if ($prod->hasilPilihPlywood) {
                foreach ($prod->hasilPilihPlywood as $hasil) {
                    $qty = $hasil->jumlah_bagus ?? 0;
                    if ($qty > $maxQty) {
                        $maxQty = $qty;
                        
                        $b = $hasil->barangSetengahJadiHp;
                        if ($b) {
                            $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);
                            
                            if ($isSengon) {
                                $kategoriId = $b->grade?->id_kategori_barang ?? 0;
                                $kategoriNama = $b->grade?->kategoriBarang?->nama_kategori ?? '';
                                
                                $isNonSanding = ($kategoriId == 2 || stripos($kategoriNama, 'mentah') !== false || stripos($kategoriNama, 'non') !== false);
                                $isSanding = ($kategoriId == 1 || (stripos($kategoriNama, 'plywood') !== false && stripos($kategoriNama, 'mentah') === false));
                                
                                if ($isNonSanding) {
                                    $target = 2200; // Sengon Non Sanding
                                } elseif ($isSanding) {
                                    $target = 1950; // Sengon Sanding
                                } else {
                                    $target = 450; // Selain 2 itu
                                }
                            } else {
                                $target = 450; // Selain 2 itu
                            }
                        }
                    }
                }
            }

            $potonganPerOrang = 0;
            if ($N > 0) {
                $groupTarget = ($N / 2.0) * $target;
                $deficit = $groupTarget - $totalActual;
                if ($deficit > 0) {
                    $potonganRaw = ($deficit * 115000) / ($groupTarget * $N);
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            $jamKerja = 10;
            $groupTarget = ($N / 2.0) * $target;
            $targetPerJam = $groupTarget / $jamKerja;
            $selisih = $totalActual - $groupTarget;
            $kendala = $prod->kendala ?? '-';

            $allRows[] = ['DEPARTEMEN: PILIH PLYWOOD'];
            $allRows[] = ['TANGGAL: ' . $tanggalFormatted];
            $allRows[] = array_fill(0, 11, '');

            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala'];

            $workerStartRow = count($allRows) + 1;
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            if ($N > 1) {
                $this->mergeRanges[] = "F{$workerStartRow}:F{$workerEndRow}";
                $this->mergeRanges[] = "G{$workerStartRow}:G{$workerEndRow}";
                $this->mergeRanges[] = "H{$workerStartRow}:H{$workerEndRow}";
                $this->mergeRanges[] = "I{$workerStartRow}:I{$workerEndRow}";
                $this->mergeRanges[] = "J{$workerStartRow}:J{$workerEndRow}";
                $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
            }

            foreach ($pekerja as $idx => $pp) {
                $jamMasuk = $pp->masuk ? Carbon::parse($pp->masuk)->format('H:i') : '-';
                $jamPulang = $pp->pulang ? Carbon::parse($pp->pulang)->format('H:i') : '-';

                $ketParts = [];
                if ($jamMasuk !== '-') {
                    $ketParts[] = "Masuk: " . $jamMasuk . ($jamPulang !== '-' ? " - " . $jamPulang : "");
                }
                if (!empty($pp->ijin) && $pp->ijin !== '-') {
                    $ketParts[] = "Ijin: " . $pp->ijin;
                }
                if (!empty($pp->ket) && $pp->ket !== '-') {
                    $ketParts[] = $pp->ket;
                }
                $ketString = !empty($ketParts) ? implode(" | ", $ketParts) : '-';

                $allRows[] = [
                    $pp->pegawai->kode_pegawai ?? '-',
                    $pp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    $potonganPerOrang > 0 ? (int) $potonganPerOrang : 0,
                    $ketString,
                    '',
                    $idx === 0 ? (int) $groupTarget : '',
                    $idx === 0 ? (int) $jamKerja : '',
                    $idx === 0 ? round((float) $targetPerJam, 2) : '',
                    $idx === 0 ? (int) $totalActual : '',
                    $idx === 0 ? (int) $selisih : '',
                    $idx === 0 ? $kendala : ''
                ];
            }

            // Total Row
            $allRows[] = [
                'TOTAL',
                $N . ' pekerja',
                $N > 0 ? "=SUM(C{$workerStartRow}:C{$workerEndRow})" : 0,
                '',
                '',
                $N > 0 ? "=SUM(F{$workerStartRow}:F{$workerEndRow})" : 0,
                (int) $jamKerja,
                $N > 0 ? "=SUM(H{$workerStartRow}:H{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(I{$workerStartRow}:I{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(J{$workerStartRow}:J{$workerEndRow})" : 0,
                ''
            ];

            $allRows[] = array_fill(0, 11, '');
            $allRows[] = array_fill(0, 11, '');

            $this->tableRanges[] = [
                'header' => $headerRow,
                'start'  => $workerStartRow,
                'end'    => $workerEndRow,
                'total'  => $totalRow
            ];
        }

        return collect($allRows);
    }

    public function headings(): array { return []; }

    public function title(): string
    {
        return 'Potongan Gaji';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set explicit column widths
                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(5);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);
                $sheet->getColumnDimension('J')->setWidth(12);
                $sheet->getColumnDimension('K')->setWidth(45);

                // Merge cells dynamically
                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                // Apply styles, borders, alignments and colors for each table
                foreach ($this->tableRanges as $range) {
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    // 1. Grid borders for the entire table (A{header} to K{total})
                    $sheet->getStyle("A{$headerRow}:K{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ]
                        ]
                    ]);

                    // 2. Header row style
                    $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0']
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ]
                    ]);

                    // 3. Total row style
                    $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9']
                        ]
                    ]);

                    // 4. Alignments for worker data cells (A{start} to K{end})
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        
                        $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("I{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("K{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        
                        // Number formats
                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Enable wrap text and top vertical alignment for Kendala (K)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("K1:K{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }
}

class LaporanPilihPlywoodProduksiSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
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

    public function title(): string
    {
        return 'Laporan Produksi';
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
