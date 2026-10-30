```
<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// ==========================================
// KELAS UTAMA & MASTER (MULTI-SHEET)
// ==========================================
class LaporanSandingJoinExport implements WithMultipleSheets
{
    protected array $dataProduksi;
    protected array $dataPekerja;
    protected ?string $tanggal;

    public function __construct(array $dataProduksi, $dataPekerja = [], $tanggal = null)
    {
        $this->dataProduksi = $dataProduksi;

        if (is_string($dataPekerja)) {
            $this->tanggal = $dataPekerja;
            $this->dataPekerja = [];
        } else {
            $this->dataPekerja = is_array($dataPekerja) ? $dataPekerja : [];
            $this->tanggal = is_string($tanggal) ? $tanggal : null;
        }
    }

    public function sheets(): array
    {
        return [
            // ==========================================
            // SHEET 1: LAPORAN UTAMA (PEKERJA & KARTU)
            // ==========================================
            new class($this->dataProduksi, $this->dataPekerja, $this->tanggal) implements FromCollection, WithTitle, WithEvents {
                protected array $dataProduksi;
                protected array $dataPekerja;
                protected ?string $tanggal;
                protected array $cardRanges = [];

                public function __construct(array $dataProduksi, array $dataPekerja, ?string $tanggal)
                {
                    $this->dataProduksi = $dataProduksi;
                    $this->dataPekerja = $dataPekerja;
                    $this->tanggal = $tanggal;
                }

                public function title(): string
                {
                    return 'Laporan Sanding Joint';
                }

                public function collection(): Collection
                {
                    $allRows = [];
                    $this->cardRanges = [];

                    if (empty($this->dataProduksi)) {
                        return collect([['Tidak ada data produksi sanding joint untuk tanggal ini.']]);
                    }

                    $firstItem = reset($this->dataProduksi);
                    $tanggalLaporan = $this->tanggal ?? ($firstItem['tanggal'] ?? date('d/m/Y'));
                    $capaianGlobal = $firstItem['rata2_capaian_tim'] ?? null;
                    $potonganTotalTim = (int) ($firstItem['potongan_total_tim'] ?? 0);
                    $totalGajiTim = (int) ($firstItem['total_gaji_tim'] ?? 0);
                    $potonganMelebihiGaji = $firstItem['potongan_melebihi_gaji'] ?? false;
                    $isSuccessGlobal = ($capaianGlobal !== null) ? ($capaianGlobal >= 100) : false;

                    // JUDUL LAPORAN UTAMA
                    $allRows[] = ['LAPORAN PRODUKSI SANDING JOINT'];
                    $allRows[] = ['TANGGAL: ' . $tanggalLaporan];
                    $allRows[] = array_fill(0, 7, '');

                    // BANNER CAPAIAN GLOBAL TIM
                    if ($capaianGlobal !== null) {
                        $statusText = $isSuccessGlobal ? 'TARGET TERCAPAI' : 'KURANG TARGET';
                        $globalInfo = "Capaian GLOBAL tim: " . number_format($capaianGlobal, 1, ',', '.') . "% | " .
                            "Potongan total tim: Rp " . number_format($potonganTotalTim, 0, ',', '.') . " | Status: {$statusText}";

                        if ($potonganMelebihiGaji) {
                            $globalInfo .= " (⚠ Melebihi Total Gaji Tim Rp " . number_format($totalGajiTim, 0, ',', '.') . ")";
                        }

                        $allRows[] = [$globalInfo, '', '', '', '', '', ''];
                        $allRows[] = array_fill(0, 7, '');
                    }

                    // LOOPING KARTU PER UKURAN
                    foreach ($this->dataProduksi as $data) {
                        $isUkuranUnknown = (isset($data['kode_ukuran']) && $data['kode_ukuran'] === 'SANDING-JOINT-NOT-FOUND') || !($data['has_target'] ?? true);
                        $cardTitle = $isUkuranUnknown
                            ? "SANDING JOINT (" . ($data['ukuran'] ?? '-') . ") - Ukuran tidak dikenal"
                            : strtoupper($data['kode_ukuran'] ?? '');

                        $hasil = (int) ($data['hasil'] ?? 0);
                        $target = (int) ($data['target'] ?? $data['target_adjusted'] ?? 0);
                        $selisih = (int) ($data['selisih'] ?? ($hasil - $target));
                        $jamKerja = (float) ($data['jam_standar'] ?? $data['jam_aktual'] ?? 9.0);
                        $pekerjaList = !empty($data['pekerja']) ? $data['pekerja'] : $this->dataPekerja;
                        $totalPekerja = count($pekerjaList);

                        $titleRow = count($allRows) + 1;
                        $allRows[] = ["SANDING JOINT: {$cardTitle}", '', '', '', '', '', ''];

                        $subTitleRow = count($allRows) + 1;
                        $allRows[] = ['DATA PEKERJA SANDING JOINT', '', '', '', '', '', ''];

                        $colHeaderRow = count($allRows) + 1;
                        $allRows[] = ['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan'];

                        $workerStartRow = count($allRows) + 1;
                        if (empty($pekerjaList)) {
                            $allRows[] = ['-', 'Tidak ada data pekerja untuk ukuran ini.', '-', '-', '-', 0, '-'];
                        } else {
                            foreach ($pekerjaList as $p) {
                                $potRaw = (int) str_replace(['.', 'Rp ', '-', ' '], '', $p['pot_target'] ?? '0');
                                $allRows[] = [
                                    $p['id'] ?? '-',
                                    $p['nama'] ?? '-',
                                    $p['jam_masuk'] ?? '-',
                                    $p['jam_pulang'] ?? '-',
                                    $p['ijin'] ?? '-',
                                    $potRaw > 0 ? (int) $potRaw : 0,
                                    $p['keterangan'] ?? '-',
                                ];
                            }
                        }
                        $workerEndRow = count($allRows);

                        $tanda = $selisih >= 0 ? '+' : '';
                        $summaryText = "Pekerja: {$totalPekerja} | Target: " . number_format($target, 0, ',', '.') .
                            " | Jam Produksi: " . number_format($jamKerja, 1) . " jam" .
                            " | Hasil: " . number_format($hasil, 0, ',', '.') .
                            " | Selisih: {$tanda}" . number_format($selisih, 0, ',', '.') .
                            " | Tanggal: {$tanggalLaporan}";

                        $footerRow1 = count($allRows) + 1;
                        $allRows[] = [$summaryText, '', '', '', '', '', ''];

                        $this->cardRanges[] = [
                            'title'      => $titleRow,
                            'sub_title'  => $subTitleRow,
                            'col_header' => $colHeaderRow,
                            'start_data' => $workerStartRow,
                            'end_data'   => $workerEndRow,
                            'footer'     => $footerRow1,
                        ];

                        $allRows[] = array_fill(0, 7, '');
                        $allRows[] = array_fill(0, 7, '');
                    }

                    return collect($allRows);
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();

                            $sheet->getColumnDimension('A')->setWidth(12);
                            $sheet->getColumnDimension('B')->setWidth(26);
                            $sheet->getColumnDimension('C')->setWidth(14);
                            $sheet->getColumnDimension('D')->setWidth(14);
                            $sheet->getColumnDimension('E')->setWidth(12);
                            $sheet->getColumnDimension('F')->setWidth(20);
                            $sheet->getColumnDimension('G')->setWidth(35);

                            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                            if ($sheet->getCell('A4')->getValue() !== null && str_contains($sheet->getCell('A4')->getValue(), 'Capaian GLOBAL')) {
                                $sheet->mergeCells('A4:G4');
                                $isSuccess = str_contains($sheet->getCell('A4')->getValue(), 'TARGET TERCAPAI');
                                $sheet->getStyle('A4:G4')->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => $isSuccess ? '065F46' : '991B1B'], 'size' => 10],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $isSuccess ? 'D1FAE5' : 'FEE2E2']],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                                    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $isSuccess ? '10B981' : 'EF4444']]],
                                ]);
                                $sheet->getRowDimension(4)->setRowHeight(24);
                            }

                            foreach ($this->cardRanges as $card) {
                                $tRow   = $card['title'];
                                $stRow  = $card['sub_title'];
                                $hRow   = $card['col_header'];
                                $dStart = $card['start_data'];
                                $dEnd   = $card['end_data'];
                                $fRow   = $card['footer'];

                                $sheet->mergeCells("A{$tRow}:G{$tRow}");
                                $sheet->getStyle("A{$tRow}:G{$tRow}")->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27272A']],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getRowDimension($tRow)->setRowHeight(24);

                                $sheet->mergeCells("A{$stRow}:G{$stRow}");
                                $sheet->getStyle("A{$stRow}:G{$stRow}")->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F3F46']],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getRowDimension($stRow)->setRowHeight(22);

                                $sheet->getStyle("A{$hRow}:G{$hRow}")->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 10],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getRowDimension($hRow)->setRowHeight(20);

                                $sheet->getStyle("A{$dStart}:G{$dEnd}")->applyFromArray([
                                    'font' => ['size' => 10],
                                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getStyle("A{$dStart}:A{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("B{$dStart}:B{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                                $sheet->getStyle("C{$dStart}:C{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("D{$dStart}:D{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("E{$dStart}:E{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("F{$dStart}:F{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                                $sheet->getStyle("G{$dStart}:G{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                                $sheet->getStyle("F{$dStart}:F{$dEnd}")->getNumberFormat()->setFormatCode('"Rp "#,##0;("Rp "#,##0);"-"');

                                $sheet->mergeCells("A{$fRow}:G{$fRow}");
                                $sheet->getStyle("A{$fRow}:G{$fRow}")->applyFromArray([
                                    'font' => ['bold' => true, 'color' => ['rgb' => '27272A'], 'size' => 10],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F5']],
                                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getRowDimension($fRow)->setRowHeight(22);

                                $sheet->getStyle("A{$tRow}:G{$fRow}")->applyFromArray([
                                    'borders' => [
                                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4D4D8']],
                                        'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '71717A']],
                                    ],
                                ]);
                            }
                        },
                    ];
                }
            },

            // ==========================================
            // SHEET 2: REKAP HASIL (DENGAN PEMBERSIH KOMA)
            // ==========================================
            new class($this->dataProduksi, $this->dataPekerja, $this->tanggal) implements FromCollection, WithTitle, WithEvents {
                protected array $dataProduksi;
                protected array $dataPekerja;
                protected ?string $tanggal;

                public function __construct(array $dataProduksi, array $dataPekerja, ?string $tanggal)
                {
                    $this->dataProduksi = $dataProduksi;
                    $this->dataPekerja = $dataPekerja;
                    $this->tanggal = $tanggal;
                }

                public function title(): string
                {
                    return 'Rekap Hasil';
                }

                public function collection(): Collection
                {
                    $allRows = [];

                    if (empty($this->dataProduksi)) {
                        return collect([['Tidak ada data rekap untuk ditampilkan.']]);
                    }

                    $firstItem = (array) reset($this->dataProduksi);
                    $tanggalLaporan = $this->tanggal ?? ($firstItem['tanggal'] ?? date('d/m/Y'));

                    $allRows[] = ['REKAP HASIL PRODUKSI SANDING JOINT'];
                    $allRows[] = ['TANGGAL: ' . $tanggalLaporan];
                    $allRows[] = array_fill(0, 10, '');

                    $allRows[] = ['P', 'L', 'T', 'Jenis', 'Kw 2', 'Kw 3', 'Kw Af', 'Banyak', 'Kubikasi', 'Total Pkj'];

                    $totalBanyakSum = 0;
                    $totalKubikasiSum = 0.0;
                    $totalPkjSum = 0;

                    foreach ($this->dataProduksi as $rawItem) {
                        $data = (array) $rawItem;

                        // Membersihkan string angka secara total dari karakter koma
                        $cleanNum = function ($val) {
                            if (is_numeric($val)) return (float) $val;
                            if (is_string($val)) {
                                $val = trim(str_replace([' ', ','], '', $val));
                            }
                            return (float) $val;
                        };

                        $p = $cleanNum($data['panjang'] ?? 0);
                        $l = $cleanNum($data['lebar'] ?? 0);
                        $t = $cleanNum($data['tebal'] ?? 0);

                        if ($p == 0 || $l == 0 || $t == 0) {
                            $ukuranStr = $data['ukuran'] ?? '';
                            if (!empty($ukuranStr)) {
                                $parts = array_map('trim', explode('x', strtolower($ukuranStr)));
                                $p = $cleanNum($parts[0] ?? 0);
                                $l = $cleanNum($parts[1] ?? 0);
                                $t = $cleanNum($parts[2] ?? 0);
                            }
                        }

                        // Mengubah ke integer jika tidak ada nilai desimal di belakangnya
                        $pVal = ($p > 0 && $p == (int)$p) ? (int)$p : $p;
                        $lVal = ($l > 0 && $l == (int)$l) ? (int)$l : $l;
                        $tVal = ($t > 0 && $t == (int)$t) ? (int)$t : $t;

                        $jenis = $data['jenis_kayu'] ?? $data['nama_kayu'] ?? '-';

                        $kw2  = (int) ($data['kw2'] ?? 0);
                        $kw3  = (int) ($data['kw3'] ?? 0);
                        $kwaf = (int) ($data['kwaf'] ?? 0);

                        if ($kw2 == 0 && $kw3 == 0 && $kwaf == 0) {
                            $kwVal = (string) ($data['kw'] ?? '');
                            $hasilVal = (int) ($data['hasil'] ?? $data['total'] ?? 0);
                            if ($kwVal === '2') {
                                $kw2 = $hasilVal;
                            } elseif ($kwVal === '3') {
                                $kw3 = $hasilVal;
                            } elseif (strtolower($kwVal) === 'af') {
                                $kwaf = $hasilVal;
                            } else {
                                $kw3 = $hasilVal;
                            }
                        }

                        $banyak = (int) ($data['banyak'] ?? ($kw2 + $kw3 + $kwaf > 0 ? ($kw2 + $kw3 + $kwaf) : ($data['hasil'] ?? $data['total'] ?? 0)));

                        // Perhitungan Kubikasi Otomatis: (P x L x T x Banyak) / 1.000.000
                        $kubikasi = ($p > 0 && $l > 0 && $t > 0 && $banyak > 0)
                            ? ($p * $l * $t * $banyak) / 1000000
                            : (float) ($data['kubikasi'] ?? 0);

                        $pekerjaItem = !empty($data['pekerja']) ? $data['pekerja'] : $this->dataPekerja;
                        $totalPkj = (int) ($data['total_pekerja'] ?? $data['total_pkj'] ?? count($pekerjaItem));

                        $totalBanyakSum += $banyak;
                        $totalKubikasiSum += $kubikasi;
                        $totalPkjSum += $totalPkj;

                        $allRows[] = [
                            $pVal > 0 ? $pVal : '-',
                            $lVal > 0 ? $lVal : '-',
                            $tVal > 0 ? $tVal : '-',
                            $jenis,
                            $kw2 > 0 ? $kw2 : '',
                            $kw3 > 0 ? $kw3 : '',
                            $kwaf > 0 ? $kwaf : '',
                            $banyak > 0 ? $banyak : '',
                            $kubikasi > 0 ? $kubikasi : '',
                            $totalPkj > 0 ? $totalPkj : '',
                        ];
                    }

                    $allRows[] = [
                        'Total Keseluruhan',
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        $totalBanyakSum,
                        $totalKubikasiSum > 0 ? $totalKubikasiSum : '',
                        $totalPkjSum > 0 ? $totalPkjSum : ''
                    ];

                    return collect($allRows);
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();

                            $sheet->getColumnDimension('A')->setWidth(10);
                            $sheet->getColumnDimension('B')->setWidth(10);
                            $sheet->getColumnDimension('C')->setWidth(10);
                            $sheet->getColumnDimension('D')->setWidth(18);
                            $sheet->getColumnDimension('E')->setWidth(12);
                            $sheet->getColumnDimension('F')->setWidth(12);
                            $sheet->getColumnDimension('G')->setWidth(12);
                            $sheet->getColumnDimension('H')->setWidth(14);
                            $sheet->getColumnDimension('I')->setWidth(16);
                            $sheet->getColumnDimension('J')->setWidth(14);

                            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                            $highestRow = $sheet->getHighestRow();
                            $headerRow = 4;
                            $lastDataRow = $highestRow - 1;

                            $sheet->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 10],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            ]);
                            $sheet->getRowDimension($headerRow)->setRowHeight(22);

                            if ($lastDataRow >= 5) {
                                $sheet->getStyle("A5:J{$lastDataRow}")->applyFromArray([
                                    'font' => ['size' => 10],
                                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                                ]);
                                $sheet->getStyle("A5:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("D5:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                                $sheet->getStyle("E5:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                                // Format angka standar agar bersih tanpa koma trailing yang tidak diinginkan
                                $sheet->getStyle("A5:C{$lastDataRow}")
                                    ->getNumberFormat()
                                    ->setFormatCode(NumberFormat::FORMAT_GENERAL);
                                $sheet->getStyle("E5:H{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                                $sheet->getStyle("I5:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                                $sheet->getStyle("J5:J{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                            }

                            $sheet->mergeCells("A{$highestRow}:G{$highestRow}");
                            $sheet->getStyle("A{$highestRow}:J{$highestRow}")->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['rgb' => '27272A'], 'size' => 10],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F5']],
                                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                            ]);
                            $sheet->getStyle("A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            $sheet->getStyle("H{$highestRow}:J{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                            $sheet->getStyle("H{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getStyle("I{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
                            $sheet->getStyle("J{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getRowDimension($highestRow)->setRowHeight(24);

                            $sheet->getStyle("A{$headerRow}:J{$highestRow}")->applyFromArray([
                                'borders' => [
                                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4D4D8']],
                                    'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '71717A']],
                                ],
                            ]);
                        },
                    ];
                }
            },
        ];
    }
}