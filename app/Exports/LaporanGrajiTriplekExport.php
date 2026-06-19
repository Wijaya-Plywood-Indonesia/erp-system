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

class LaporanGrajiTriplekExport implements WithMultipleSheets
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
            new LaporanGrajiTriplekPotonganGajiSheet($this->tanggal),
            new LaporanGrajiTriplekProduksiSheet($this->data),
        ];
    }
}

class LaporanGrajiTriplekPotonganGajiSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
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
        $produksiList = \App\Models\ProduksiGrajitriplek::with([
            'pegawaiGrajiTriplek.pegawaiGrajiTriplek',
            'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
            'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
            'hasilGrajiTriplek.barangSetengahJadiHp.grade.kategoriBarang',
            'kendalaGrajiTripleks.mesin',
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        $tanggalFormatted = Carbon::parse($this->tanggal)->format('d/m/Y');

        foreach ($produksiList as $prod) {
            $shift = strtoupper($prod->shift ?? 'PAGI');
            $mesinNama = 'GERGAJI TRIPLEK - ' . $shift;
            
            $pekerja = [];
            if ($prod->pegawaiGrajiTriplek) {
                foreach ($prod->pegawaiGrajiTriplek as $pg) {
                    if ($pg->pegawaiGrajiTriplek) {
                        $pekerja[] = $pg;
                    }
                }
            }
            $N = count($pekerja);

            // Calculate actual production
            $totalActual = 0;
            if ($prod->hasilGrajiTriplek) {
                foreach ($prod->hasilGrajiTriplek as $detail) {
                    $totalActual += $detail->isi ?? 0;
                }
            }

            // Target scales linearly with worker count: N * 750
            $target = $N * 750;

            $potonganPerOrang = 0;
            if ($N > 0) {
                $deficit = $target - $totalActual;
                if ($deficit > 0) {
                    $potonganRaw = ($deficit * 115000) / ($target * $N);
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

            $jamKerja = 10; // Jam kerja standard
            $targetPerJam = $jamKerja > 0 ? $target / $jamKerja : 0;
            $selisih = $totalActual - $target;

            // Concatenate child table constraints if available
            $kendalaText = '-';
            if ($prod->kendalaGrajiTripleks && $prod->kendalaGrajiTripleks->isNotEmpty()) {
                $kendalaParts = [];
                foreach ($prod->kendalaGrajiTripleks as $k) {
                    $mesinLabel = $k->mesin?->nama_mesin;
                    $mulai = $k->waktu_mulai ? Carbon::parse($k->waktu_mulai)->format('H:i') : '';
                    $selisihTime = $k->waktu_selesai ? Carbon::parse($k->waktu_selesai)->format('H:i') : '';
                    $durasi = $k->durasi_menit ? "{$k->durasi_menit} menit" : '';
                    
                    $timeStr = '';
                    if ($mulai || $selisihTime) {
                        $timeStr = " (" . ($durasi ? "{$durasi}: " : "") . "{$mulai}-{$selisihTime})";
                    }
                    
                    $mesinPart = $mesinLabel ? "{$mesinLabel}: " : "";
                    $kendalaParts[] = $mesinPart . $k->kendala . $timeStr;
                }
                $kendalaText = implode("\n", $kendalaParts);
            } else {
                $kendalaText = $prod->kendala ?? '-';
            }
            $kendala = $kendalaText;

            $allRows[] = ['MESIN: ' . strtoupper($mesinNama)];
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

            foreach ($pekerja as $idx => $pg) {
                $jamMasuk = $pg->masuk ? Carbon::parse($pg->masuk)->format('H:i') : '-';
                $jamPulang = $pg->pulang ? Carbon::parse($pg->pulang)->format('H:i') : '-';

                $ketParts = [];
                if ($jamMasuk !== '-') {
                    $ketParts[] = "Masuk: " . $jamMasuk . ($jamPulang !== '-' ? " - " . $jamPulang : "");
                }
                if (!empty($pg->ijin) && $pg->ijin !== '-') {
                    $ketParts[] = "Ijin: " . $pg->ijin;
                }
                if (!empty($pg->ket) && $pg->ket !== '-') {
                    $ketParts[] = $pg->ket;
                }
                $ketString = !empty($ketParts) ? implode(" | ", $ketParts) : '-';

                $allRows[] = [
                    $pg->pegawaiGrajiTriplek->kode_pegawai ?? '-',
                    $pg->pegawaiGrajiTriplek->nama_pegawai ?? 'TANPA NAMA',
                    $potonganPerOrang > 0 ? (int) $potonganPerOrang : 0,
                    $ketString,
                    '',
                    $idx === 0 ? (int) $target : '',
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

class LaporanGrajiTriplekProduksiSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
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
                $row['d_grade'] = $d['grade'];
                $row['d_byk'] = $d['byk'];
                $row['d_m3'] = ''; 
            } else {
                $row['d_tgl'] = $row['d_p'] = $row['d_l'] = $row['d_t'] = $row['d_jenis'] = $row['d_grade'] = $row['d_byk'] = $row['d_m3'] = '';
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
            'Tanggal', 'p', 'l', 't', 'jenis', 'grade', 'byk', 'm3',
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
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:H" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("J1:O" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("I1:I" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');
                $sheet->getStyle('N1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(8);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(8);
                $sheet->getColumnDimension('H')->setWidth(10);
                $sheet->getColumnDimension('I')->setWidth(3); 
                $sheet->getColumnDimension('J')->setWidth(15);
                $sheet->getColumnDimension('K')->setWidth(10);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(15);
                $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(18);
            },
        ];
    }
}
