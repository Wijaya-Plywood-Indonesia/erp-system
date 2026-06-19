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

class LaporanSandingExport implements WithMultipleSheets
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
            new LaporanSandingPotonganGajiSheet($this->tanggal),
            new LaporanSandingProduksiSheet($this->data),
        ];
    }
}

class LaporanSandingPotonganGajiSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
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
        $produksiList = \App\Models\ProduksiSanding::with([
            'pegawaiSandings.pegawai',
            'hasilSandings.barangSetengahJadi.ukuran',
            'hasilSandings.barangSetengahJadi.jenisBarang',
            'hasilSandings.barangSetengahJadi.grade.kategoriBarang',
            'mesin',
            'kendalaSandings'
        ])
            ->whereDate('tanggal', $this->tanggal)
            ->get();

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        $tanggalFormatted = Carbon::parse($this->tanggal)->format('d/m/Y');

        foreach ($produksiList as $prod) {
            $mesinNama = $prod->mesin->nama_mesin ?? 'SANDING';
            $pekerja = $prod->pegawaiSandings ?? [];
            $N = count($pekerja);

            // Calculate actual production
            $totalActual = 0;
            if ($prod->hasilSandings) {
                foreach ($prod->hasilSandings as $hasil) {
                    $totalActual += $hasil->kuantitas ?? 0;
                }
            }

            // Determine dominant item in Sanding
            $isSengon = true;
            $maxQty = -1;
            if ($prod->hasilSandings) {
                foreach ($prod->hasilSandings as $hasil) {
                    $qty = $hasil->kuantitas ?? 0;
                    if ($qty > $maxQty) {
                        $maxQty = $qty;
                        $b = $hasil->barangSetengahJadi;
                        if ($b) {
                            $isSengon = ($b->jenisBarang && stripos($b->jenisBarang->nama_jenis_barang, 'sengon') !== false);
                        }
                    }
                }
            }

            if (!$isSengon) {
                $target = 450;
            } else {
                $target = 250;
                if ($prod->id_mesin == 24 || ($prod->mesin && stripos($prod->mesin->nama_mesin, 'besar') !== false)) {
                    $target = 800;
                }
            }

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

            $jamKerja = 10;
            $targetPerJam = $target / $jamKerja;
            $selisih = $totalActual - $target;

            // HITUNG KENDALA DOWNTIME DARI MODEL BARU (kendalaSandings)
            $totalDowntimeMenit = 0;
            $daftarKendala = [];

            if (!empty($prod->kendalaSandings) && $prod->kendalaSandings->count() > 0) {
                foreach ($prod->kendalaSandings as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int)$knd->durasi_menit;
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        $timeStr = ($mulai && $selesai) ? ': ' . $mulai->format('H:i') . '-' . $selesai->format('H:i') : '';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')';

                        $daftarKendala[] = [
                            'text' => $formattedText,
                        ];
                        $totalDowntimeMenit += $durasiMenit;
                    } else {
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: ' . $mulai->format('H:i') . ' - Pending)' : ' (Pending)';
                        $formattedText = ($knd->kendala ?? 'Tidak disebutkan') . $timeStr;

                        $daftarKendala[] = [
                            'text' => $formattedText,
                        ];
                    }
                }
            } else {
                // Fallback ke kolom kendala lama di tabel produksi_sandings jika ada
                if (!empty($prod->kendala) && $prod->kendala !== '-') {
                    $daftarKendala[] = [
                        'text' => $prod->kendala,
                    ];
                }
            }

            $allRows[] = ['MESIN: ' . strtoupper($mesinNama)];
            $allRows[] = ['TANGGAL: ' . $tanggalFormatted];
            $allRows[] = array_fill(0, 11, '');

            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala'];

            $workerStartRow = count($allRows) + 1;
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            // Merge kolom F s/d J secara statis jika N > 1
            if ($N > 1) {
                $this->mergeRanges[] = "F{$workerStartRow}:F{$workerEndRow}";
                $this->mergeRanges[] = "G{$workerStartRow}:G{$workerEndRow}";
                $this->mergeRanges[] = "H{$workerStartRow}:H{$workerEndRow}";
                $this->mergeRanges[] = "I{$workerStartRow}:I{$workerEndRow}";
                $this->mergeRanges[] = "J{$workerStartRow}:J{$workerEndRow}";
            }

            // Pre-calculate nilai sel untuk kolom Kendala (K) secara dinamis
            $kendalaCellValues = array_fill(0, $N, '');

            if ($N > 0) {
                if (count($daftarKendala) === 0) {
                    $kendalaCellValues[0] = 'Tidak ada kendala';
                    if ($N > 1) {
                        $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
                    }
                } else {
                    $M = count($daftarKendala);
                    
                    if ($N < $M) {
                        // Jika jumlah pekerja lebih sedikit dari kendala, gabungkan semua kendala dengan newline
                        $text = implode("\n", array_column($daftarKendala, 'text'));
                        $kendalaCellValues[0] = $text;
                        if ($N > 1) {
                            $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
                        }
                    } else {
                        // Jika pekerja cukup, bagi rata secara chunk
                        $chunkSize = (int) ceil($N / $M);

                        for ($i = 0; $i < $M; $i++) {
                            $startIdx = $i * $chunkSize;
                            $endIdx = min(($i + 1) * $chunkSize - 1, $N - 1);

                            if ($startIdx < $N) {
                                $kendalaCellValues[$startIdx] = $daftarKendala[$i]['text'] ?? '';
                                $chunkStartRow = $workerStartRow + $startIdx;
                                $chunkEndRow = $workerStartRow + $endIdx;

                                if ($chunkStartRow < $chunkEndRow) {
                                    $this->mergeRanges[] = "K{$chunkStartRow}:K{$chunkEndRow}";
                                }
                            }
                        }
                    }
                }
            }

            foreach ($pekerja as $idx => $ps) {
                $jamMasuk = $ps->masuk ? Carbon::parse($ps->masuk)->format('H:i') : '-';
                $jamPulang = $ps->pulang ? Carbon::parse($ps->pulang)->format('H:i') : '-';

                $ketParts = [];
                if ($jamMasuk !== '-') {
                    $ketParts[] = "Masuk: " . $jamMasuk . ($jamPulang !== '-' ? " - " . $jamPulang : "");
                }
                if (!empty($ps->ijin) && $ps->ijin !== '-') {
                    $ketParts[] = "Ijin: " . $ps->ijin;
                }
                if (!empty($ps->ket) && $ps->ket !== '-') {
                    $ketParts[] = $ps->ket;
                }
                $ketString = !empty($ketParts) ? implode(" | ", $ketParts) : '-';

                $allRows[] = [
                    $ps->pegawai->kode_pegawai ?? '-',
                    $ps->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    $potonganPerOrang > 0 ? (int) $potonganPerOrang : 0,
                    $ketString,
                    '',
                    $idx === 0 ? (int) $target : '',
                    $idx === 0 ? (int) $jamKerja : '',
                    $idx === 0 ? round((float) $targetPerJam, 2) : '',
                    $idx === 0 ? (int) $totalActual : '',
                    $idx === 0 ? (int) $selisih : '',
                    $kendalaCellValues[$idx]
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
                $totalDowntimeMenit > 0 ? $totalDowntimeMenit . ' menit' : ''
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

class LaporanSandingProduksiSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
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
                $row['d_mesin'] = $d['mesin'];
                $row['d_p'] = $d['p'];
                $row['d_l'] = $d['l'];
                $row['d_t'] = $d['t'];
                $row['d_jenis'] = $d['jenis'];
                $row['d_banyak'] = $d['banyak'];
                $row['d_m3'] = ''; 
            } else {
                $row['d_tgl'] = $row['d_mesin'] = $row['d_p'] = $row['d_l'] = $row['d_t'] = $row['d_jenis'] = $row['d_banyak'] = $row['d_m3'] = '';
            }

            $row['spacer'] = ''; 

            // Right side (Summary)
            if ($i < count($summaryProduksi)) {
                $s = $summaryProduksi[$i];
                $row['s_tgl'] = $s['tanggal'];
                $row['s_mesin'] = $s['mesin'];
                $row['s_jml_pkj'] = $s['jml_pkj'];
                $row['s_hasil_kubikasi'] = ''; 
                $row['s_harga'] = ''; 
                $row['s_ongkos_m3'] = ''; 
                $row['s_ongkos_lbr'] = ''; 
            } else {
                $row['s_tgl'] = $row['s_mesin'] = $row['s_jml_pkj'] = $row['s_hasil_kubikasi'] = $row['s_harga'] = $row['s_ongkos_m3'] = $row['s_ongkos_lbr'] = '';
            }

            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Mesin', 'p', 'l', 't', 'jenis', 'banyak', 'm3',
            '', 
            'tanggal', 'Mesin', 'Jumlah Pekerja', 'Hasil Kubikasi', 'Harga', 'Ongkos(m3)', 'Ongkos(lbr)'
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

                $sheet->getStyle("A1:H" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("J1:P" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("I1:I" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');
                
                $sheet->getColumnDimension('A')->setWidth(15);
                $sheet->getColumnDimension('B')->setWidth(20);
                $sheet->getColumnDimension('C')->setWidth(8);
                $sheet->getColumnDimension('D')->setWidth(8);
                $sheet->getColumnDimension('E')->setWidth(8);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(10);
                $sheet->getColumnDimension('I')->setWidth(3); 
                $sheet->getColumnDimension('J')->setWidth(15);
                $sheet->getColumnDimension('K')->setWidth(20);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(15);
                $sheet->getColumnDimension('N')->setWidth(15);
                $sheet->getColumnDimension('O')->setWidth(18);
                $sheet->getColumnDimension('P')->setWidth(18);
            },
        ];
    }
}
