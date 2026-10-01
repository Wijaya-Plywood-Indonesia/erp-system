<?php

namespace App\Exports;

use App\Models\ProduksiSanding;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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
            new LaporanSandingPotonganGajiSheet($this->data, $this->tanggal),
            new LaporanSandingProduksiSheet($this->data),
            new LaporanSandingTargetSheet($this->data),
        ];
    }
}

class LaporanSandingPotonganGajiSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected $data;
    protected $tanggal;
    protected $mergeRanges = [];
    protected $tableRanges = [];

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    /**
     * Target "setara": total hasil dibagi capaian global, sehingga
     * hasil / target selalu sama dengan persen capaian di laporan.
     */
    private function targetSetara(array $prod): float
    {
        $cap = (float) ($prod['capaian_global'] ?? 0);
        $hasil = (float) ($prod['hasil_total'] ?? 0);

        if ($cap > 0 && $hasil > 0) {
            return $hasil / ($cap / 100);
        }

        $list = collect($prod['per_ukuran'] ?? [])->where('has_target', true);

        return $list->isNotEmpty() ? (float) $list->avg('target') : 0.0;
    }

    public function collection()
    {
        $blok = $this->data['produksi'] ?? [];

        $ids = collect($blok)->pluck('id_produksi')->filter()->all();
        $modelProduksi = ProduksiSanding::with('kendalaSandings')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        $tanggalFormatted = Carbon::parse($this->tanggal)->format('d/m/Y');

        foreach ($blok as $prod) {
            $mesinNama = $prod['mesin'] ?? 'SANDING';
            $shift = $prod['shift'] ?? '';
            $pekerja = $prod['pekerja'] ?? [];
            $N = count($pekerja);

            $punyaTarget = (bool) ($prod['punya_target'] ?? false);
            $target = $this->targetSetara($prod);
            $hasil = (float) ($prod['hasil_total'] ?? 0);
            $jamKerja = (float) ($prod['jam_aktual_rata'] ?? 0);
            $targetPerJam = $jamKerja > 0 ? $target / $jamKerja : 0;
            $selisih = $hasil - $target;
            $capaianCell = $punyaTarget
                ? ((float) ($prod['capaian_global'] ?? 0)) / 100
                : 'Target belum ada';

            // Kendala dari model kendalaSandings
            $totalDowntimeMenit = 0;
            $daftarKendala = [];
            $model = $modelProduksi->get($prod['id_produksi'] ?? 0);

            if ($model && $model->kendalaSandings && $model->kendalaSandings->count() > 0) {
                foreach ($model->kendalaSandings as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $durasiMenit = (int) $knd->durasi_menit;
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                        $timeStr = ($mulai && $selesai) ? ': ' . $mulai->format('H:i') . '-' . $selesai->format('H:i') : '';
                        $daftarKendala[] = [
                            'text' => ($knd->kendala ?? 'Tidak disebutkan') . ' (' . $durasiMenit . ' menit' . $timeStr . ')',
                        ];
                        $totalDowntimeMenit += $durasiMenit;
                    } else {
                        $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                        $timeStr = $mulai ? ' (Mulai: ' . $mulai->format('H:i') . ' - Pending)' : ' (Pending)';
                        $daftarKendala[] = [
                            'text' => ($knd->kendala ?? 'Tidak disebutkan') . $timeStr,
                        ];
                    }
                }
            } elseif ($model && !empty($model->kendala) && $model->kendala !== '-') {
                $daftarKendala[] = ['text' => $model->kendala];
            }

            $allRows[] = ['MESIN: ' . strtoupper($mesinNama) . ($shift !== '' ? ' - SHIFT ' . strtoupper($shift) : '')];
            $allRows[] = ['TANGGAL: ' . $tanggalFormatted];
            $allRows[] = array_fill(0, 12, '');

            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Capaian', 'Kendala'];

            $workerStartRow = count($allRows) + 1;
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            // Merge kolom F s/d K (ringkasan produksi) jika N > 1
            if ($N > 1) {
                foreach (['F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                    $this->mergeRanges[] = "{$col}{$workerStartRow}:{$col}{$workerEndRow}";
                }
            }

            // Nilai sel kolom Kendala (L)
            $kendalaCellValues = array_fill(0, max($N, 1), '');

            if ($N > 0) {
                if (count($daftarKendala) === 0) {
                    $kendalaCellValues[0] = 'Tidak ada kendala';
                    if ($N > 1) {
                        $this->mergeRanges[] = "L{$workerStartRow}:L{$workerEndRow}";
                    }
                } else {
                    $M = count($daftarKendala);

                    if ($N < $M) {
                        $kendalaCellValues[0] = implode("\n", array_column($daftarKendala, 'text'));
                        if ($N > 1) {
                            $this->mergeRanges[] = "L{$workerStartRow}:L{$workerEndRow}";
                        }
                    } else {
                        $chunkSize = (int) ceil($N / $M);

                        for ($i = 0; $i < $M; $i++) {
                            $startIdx = $i * $chunkSize;
                            $endIdx = min(($i + 1) * $chunkSize - 1, $N - 1);

                            if ($startIdx < $N) {
                                $kendalaCellValues[$startIdx] = $daftarKendala[$i]['text'] ?? '';
                                $chunkStartRow = $workerStartRow + $startIdx;
                                $chunkEndRow = $workerStartRow + $endIdx;

                                if ($chunkStartRow < $chunkEndRow) {
                                    $this->mergeRanges[] = "L{$chunkStartRow}:L{$chunkEndRow}";
                                }
                            }
                        }
                    }
                }
            }

            foreach ($pekerja as $idx => $p) {
                $jamMasuk = $p['jam_masuk'] ?? '-';
                $jamPulang = $p['jam_pulang'] ?? '-';

                $ketParts = [];
                if ($jamMasuk !== '-') {
                    $ketParts[] = 'Masuk: ' . $jamMasuk . ($jamPulang !== '-' ? ' - ' . $jamPulang : '');
                }
                if (!empty($p['ijin']) && $p['ijin'] !== '-') {
                    $ketParts[] = 'Ijin: ' . $p['ijin'];
                }
                if (!empty($p['keterangan']) && $p['keterangan'] !== '-') {
                    $ketParts[] = $p['keterangan'];
                }
                $ketString = !empty($ketParts) ? implode(' | ', $ketParts) : '-';

                $allRows[] = [
                    $p['id'] ?? '-',
                    $p['nama'] ?? 'TANPA NAMA',
                    (int) ($p['pot_target'] ?? 0),
                    $ketString,
                    '',
                    $idx === 0 ? (int) round($target) : '',
                    $idx === 0 ? round($jamKerja, 2) : '',
                    $idx === 0 ? round($targetPerJam, 2) : '',
                    $idx === 0 ? (int) round($hasil) : '',
                    $idx === 0 ? (int) round($selisih) : '',
                    $idx === 0 ? $capaianCell : '',
                    $kendalaCellValues[$idx] ?? '',
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
                round($jamKerja, 2),
                $N > 0 ? "=SUM(H{$workerStartRow}:H{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(I{$workerStartRow}:I{$workerEndRow})" : 0,
                $N > 0 ? "=SUM(J{$workerStartRow}:J{$workerEndRow})" : 0,
                '',
                $totalDowntimeMenit > 0 ? $totalDowntimeMenit . ' menit' : '',
            ];

            $allRows[] = array_fill(0, 12, '');
            $allRows[] = array_fill(0, 12, '');

            $this->tableRanges[] = [
                'header' => $headerRow,
                'start' => $workerStartRow,
                'end' => $workerEndRow,
                'total' => $totalRow,
            ];
        }

        return collect($allRows);
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Potongan Gaji';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $widths = ['A' => 10, 'B' => 25, 'C' => 15, 'D' => 32, 'E' => 5, 'F' => 14, 'G' => 11, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 16, 'L' => 45];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                foreach ($this->tableRanges as $range) {
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    $sheet->getStyle("A{$headerRow}:L{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9'],
                        ],
                    ]);

                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                        foreach (['F', 'H', 'I', 'J', 'K'] as $col) {
                            $sheet->getStyle("{$col}{$startRow}:{$col}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                        $sheet->getStyle("G{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("L{$startRow}:L{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("K{$startRow}:K{$endRow}")->getNumberFormat()->setFormatCode('0.0%');
                    }
                }

                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("L1:L{$highestRow}")
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
            'tanggal', 'Mesin', 'Jumlah Pekerja', 'Hasil Kubikasi', 'Harga', 'Ongkos(m3)', 'Ongkos(lbr)',
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

                $sheet->getStyle('A1:H' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('J1:P' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('I1:I' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');

                $widths = ['A' => 15, 'B' => 20, 'C' => 8, 'D' => 8, 'E' => 8, 'F' => 15, 'G' => 10, 'H' => 10, 'I' => 3, 'J' => 15, 'K' => 20, 'L' => 15, 'M' => 15, 'N' => 15, 'O' => 18, 'P' => 18];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}

class LaporanSandingTargetSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $rows = collect();

        foreach (($this->data['produksi'] ?? []) as $prod) {
            foreach (($prod['per_ukuran'] ?? []) as $u) {
                $adaTarget = (bool) ($u['has_target'] ?? false);

                $rows->push([
                    $prod['tanggal'] ?? '',
                    $prod['mesin'] ?? '',
                    $prod['shift'] ?? '',
                    $u['ukuran'] ?? '-',
                    $u['jenis_kayu'] ?? '-',
                    $u['kategori'] ?? '-',
                    $u['grade'] ?? '-',
                    (int) round($u['hasil'] ?? 0),
                    $adaTarget ? round((float) $u['target'], 1) : '-',
                    $adaTarget ? round((float) ($u['target_normal'] ?? 0), 1) : '-',
                    $adaTarget ? round((float) $u['selisih'], 1) : '-',
                    $adaTarget ? ((float) ($u['capaian_persen'] ?? 0)) / 100 : 'Target ?',
                ]);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Mesin', 'Shift', 'Ukuran', 'Jenis Kayu', 'Kategori', 'Grade',
            'Hasil', 'Target (Adjusted)', 'Target Normal', 'Selisih', 'Capaian',
        ];
    }

    public function title(): string
    {
        return 'Target per Barang';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->getStyle('A1:L' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
                $sheet->getStyle('L2:L' . $lastRow)->getNumberFormat()->setFormatCode('0.0%');
                $sheet->getStyle('H2:K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $widths = ['A' => 12, 'B' => 18, 'C' => 9, 'D' => 20, 'E' => 14, 'F' => 14, 'G' => 20, 'H' => 10, 'I' => 17, 'J' => 14, 'K' => 12, 'L' => 12];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}