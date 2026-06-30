<?php

namespace App\Exports;

use App\Exports\Sheets\LaporanProduksiHotPressJurnalSheet;
use App\Models\ProduksiHp;
use App\Models\BahanPenolongHp;
use App\Models\BahanPenolongProduksi;
use App\Models\HargaPegawai;
use App\Models\TriplekHasilHp;
use App\Models\PlatformHasilHp;
use App\Models\Target;
use App\Models\Mesin;
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
    protected $domain; // Tambahan variabel domain

    public function __construct($data, $tanggal, $domain = '')
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
        $this->domain = $domain; // Simpan domain
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiHotPressSheetPekerja($this->tanggal),
            new LaporanProduksiHotPressRekapSheet($this->tanggal),
            new LaporanProduksiHotPressDetailSheet($this->data),
            // TAMBAHKAN SHEET JURNAL DI SINI
            new LaporanProduksiHotPressJurnalSheet($this->tanggal, $this->domain),
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
                        'kwalitas' => strtoupper('TRIPLEK ' . ($item->barangSetengahJadi->grade->nama_grade ?? '-')),
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
                        'kwalitas' => strtoupper('PLATFORM ' . ($item->barangSetengahJadi->grade->nama_grade ?? '-')),
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

class LaporanProduksiHotPressSheetPekerja implements FromCollection, WithHeadings, WithTitle, WithEvents
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
            'detailPegawaiHp.pegawaiHp',
            'kendalaHps.mesin',
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $hotpressMachineIds = Mesin::join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'HOTPRESS')
            ->pluck('mesins.id')
            ->toArray();

        if (empty($hotpressMachineIds)) {
            $hotpressMachineIds = [13, 26, 27, 28];
        }

        $targets = Target::whereIn('id_mesin', $hotpressMachineIds)->get();

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        $allRows[] = ['LAPORAN POTONGAN GAJI HOT PRESS'];
        $allRows[] = ['TANGGAL: ' . $tglStr];
        $allRows[] = array_fill(0, 11, '');

        foreach ($produksis as $produksi) {
            $produksiId = $produksi->id;
            $shift = (strtoupper($produksi->shift ?? '') === 'MALAM') ? 'MALAM' : 'PAGI';

            // 1. Calculate actual production by id_ukuran
            $platformActuals = PlatformHasilHp::where('id_produksi_hp', $produksiId)
                ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'platform_hasil_hp.id_barang_setengah_jadi')
                ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(platform_hasil_hp.isi) as total_actual')
                ->groupBy('barang_setengah_jadi_hp.id_ukuran')
                ->get();

            $triplekActuals = TriplekHasilHp::where('id_produksi_hp', $produksiId)
                ->join('barang_setengah_jadi_hp', 'barang_setengah_jadi_hp.id', '=', 'triplek_hasil_hp.id_barang_setengah_jadi')
                ->selectRaw('barang_setengah_jadi_hp.id_ukuran, SUM(triplek_hasil_hp.isi) as total_actual')
                ->groupBy('barang_setengah_jadi_hp.id_ukuran')
                ->get();

            $combinedActuals = [];
            foreach ($platformActuals as $act) {
                $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
            }
            foreach ($triplekActuals as $act) {
                if (isset($combinedActuals[$act->id_ukuran])) {
                    $combinedActuals[$act->id_ukuran] += (int) $act->total_actual;
                } else {
                    $combinedActuals[$act->id_ukuran] = (int) $act->total_actual;
                }
            }

            // 2. Calculate target achievement ratio, total target, total actual, etc.
            $totalTargetVal = 0;
            $totalActualVal = 0;
            $targetHotpress = 0;
            $gaji = 115000;
            $stdJam = 10;

            foreach ($combinedActuals as $id_ukuran => $actual) {
                $tgt = $targets->first(function ($t) use ($id_ukuran) {
                    return $t->id_ukuran == $id_ukuran;
                });

                // FALLBACK TO WILDCARD (size 33 / '0x0x0') IF NOT FOUND
                if (!$tgt) {
                    $tgt = $targets->first(function ($t) {
                        return $t->id_ukuran == 33;
                    });
                }

                if ($tgt) {
                    $targetVal = (float) $tgt->target;
                    $totalTargetVal += $targetVal;
                    $totalActualVal += $actual;

                    if ($targetVal > 0) {
                        $targetHotpress += $actual / $targetVal;
                    }

                    if (isset($tgt->gaji) && $tgt->gaji > 0) {
                        $gaji = (float) $tgt->gaji;
                    }
                    if (isset($tgt->jam) && $tgt->jam > 0) {
                        $stdJam = (int) $tgt->jam;
                    }
                }
            }

            // 3. Calculate worker salary deduction using the new formula
            $pekerjaList = $produksi->detailPegawaiHp ?? [];
            $N = count($pekerjaList);
            $potonganPerOrang = 0;

            if ($N > 0) {
                if ($targetHotpress >= 1.0) {
                    $potonganPerOrang = 0;
                } else {
                    $potonganRaw = (($gaji - ($targetHotpress * $gaji)) * (0.1 * $stdJam)) / ($N * 0.1);
                    if ($potonganRaw < 0) {
                        $potonganRaw = 0;
                    }

                    // --- RUMUS PEMBULATAN KHUSUS (0, 500, 1000) ---
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

            if ($N > 0) {
                $allRows[] = ['PRODUKSI HOT PRESS - ' . strtoupper($shift)];
                $allRows[] = array_fill(0, 11, '');

                $headerRow = count($allRows) + 1;
                $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Pencapaian', 'Kendala'];

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

                $kendalaText = 'Tidak ada kendala.';
                if ($produksi->kendalaHps && $produksi->kendalaHps->isNotEmpty()) {
                    $kendalaParts = [];
                    foreach ($produksi->kendalaHps as $k) {
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
                } else if (!empty($produksi->kendala) && $produksi->kendala !== '-') {
                    $kendalaText = $produksi->kendala;
                }
                $kendala = $kendalaText;
                $targetPerJam = $stdJam > 0 ? 1 / $stdJam : 0;
                $pencapaianVal = $totalTargetVal > 0 ? $totalActualVal / $totalTargetVal : 0;

                foreach ($pekerjaList as $idx => $dp) {
                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i') : '-';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i') : '-';

                    $ketParts = [];
                    if ($jamMasuk !== '-') {
                        $ketParts[] = "Masuk: " . $jamMasuk . ($jamPulang !== '-' ? " - " . $jamPulang : "");
                    }
                    if (!empty($dp->ijin) && $dp->ijin !== '-') {
                        $ketParts[] = "Ijin: " . $dp->ijin;
                    }
                    if (!empty($dp->ket) && $dp->ket !== '-') {
                        $ketParts[] = $dp->ket;
                    }
                    $ketString = !empty($ketParts) ? implode(" | ", $ketParts) : '-';

                    $allRows[] = [
                        $dp->pegawaiHp->kode_pegawai ?? '-',
                        $dp->pegawaiHp->nama_pegawai ?? 'TANPA NAMA',
                        (int) $potonganPerOrang,
                        $ketString,
                        '',
                        $idx === 0 ? 1 : '',
                        $idx === 0 ? (int) $stdJam : '',
                        $idx === 0 ? (float) $targetPerJam : '',
                        $idx === 0 ? (int) $totalActualVal : '',
                        $idx === 0 ? (float) $pencapaianVal : '',
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
                    1,
                    (int) $stdJam,
                    $stdJam > 0 ? 1 / $stdJam : 0,
                    $N > 0 ? "=SUM(I{$workerStartRow}:I{$workerEndRow})" : 0,
                    $N > 0 ? "=J{$workerStartRow}" : 0,
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
        }

        return collect($allRows);
    }

    public function headings(): array { return []; }
    public function title(): string   { return 'Laporan Pekerja Hot Press'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set explicit column widths
                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(25);
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
                        
                        // Vertical alignment top for merged cells
                        $sheet->getStyle("F{$startRow}:K{$endRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                        // Number formats
                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('0%');
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('0.0%');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("J{$startRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('0.00%');
                    }
                }

                // Enable wrap text and top vertical alignment for Keterangan (D) and Kendala (K)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("D1:D{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("K1:K{$highestRow}")->getAlignment()->setWrapText(true);
            }
        ];
    }
}
