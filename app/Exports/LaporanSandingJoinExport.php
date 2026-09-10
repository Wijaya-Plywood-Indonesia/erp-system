<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanSandingJoinExport implements FromCollection, WithTitle, WithEvents
{
    protected array $dataProduksi;
    protected array $dataPekerja;
    protected ?string $tanggal;
    protected array $cardRanges = [];

    /**
     * Mendukung fleksibilitas argumen:
     * - new LaporanSandingJoinExport($dataProduksi, $dataPekerja, $tanggal)
     * - new LaporanSandingJoinExport($dataProduksi, $tanggal)
     */
    public function __construct(array $dataProduksi, array|string $dataPekerja = [], ?string $tanggal = null)
    {
        $this->dataProduksi = $dataProduksi;

        if (is_string($dataPekerja)) {
            $this->tanggal = $dataPekerja;
            $this->dataPekerja = [];
        } else {
            $this->dataPekerja = $dataPekerja;
            $this->tanggal = $tanggal;
        }
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

        // 1. JUDUL LAPORAN UTAMA
        $allRows[] = ['LAPORAN PRODUKSI SANDING JOINT'];
        $allRows[] = ['TANGGAL: ' . $tanggalLaporan];
        $allRows[] = array_fill(0, 7, '');

        // 2. BANNER CAPAIAN GLOBAL TIM
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

        // 3. LOOPING KARTU PER UKURAN
        foreach ($this->dataProduksi as $data) {
            $isUkuranUnknown = ($data['kode_ukuran'] === 'SANDING-JOINT-NOT-FOUND') || !($data['has_target'] ?? true);
            $cardTitle = $isUkuranUnknown
                ? "SANDING JOINT ({$data['ukuran']}) - Ukuran tidak dikenal"
                : strtoupper($data['kode_ukuran']);

            $hasil = (int) ($data['hasil'] ?? 0);
            $target = (int) ($data['target'] ?? $data['target_adjusted'] ?? 0);
            $selisih = (int) ($data['selisih'] ?? ($hasil - $target));
            $jamKerja = (float) ($data['jam_standar'] ?? $data['jam_aktual'] ?? 9.0);
            $pekerjaList = !empty($data['pekerja']) ? $data['pekerja'] : $this->dataPekerja;
            $totalPekerja = count($pekerjaList);

            // Baris Header Card (Ukuran Mesin)
            $titleRow = count($allRows) + 1;
            $allRows[] = ["SANDING JOINT: {$cardTitle}", '', '', '', '', '', ''];

            // Baris Sub-Header (DATA PEKERJA)
            $subTitleRow = count($allRows) + 1;
            $allRows[] = ['DATA PEKERJA SANDING JOINT', '', '', '', '', '', ''];

            // Header Kolom Tabel
            $colHeaderRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan'];

            // Data Pekerja
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

            // Footer Summary Per Ukuran
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

            // Jarak pemisah antar ukuran
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

                // Set lebar kolom A-G
                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(20);
                $sheet->getColumnDimension('G')->setWidth(35);

                // Style Judul Laporan
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                // Style Banner Capaian Global jika ada di baris 4
                if ($sheet->getCell('A4')->getValue() !== null && str_contains($sheet->getCell('A4')->getValue(), 'Capaian GLOBAL')) {
                    $sheet->mergeCells('A4:G4');
                    $isSuccess = str_contains($sheet->getCell('A4')->getValue(), 'TARGET TERCAPAI');
                    $sheet->getStyle('A4:G4')->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => $isSuccess ? '065F46' : '991B1B'],
                            'size' => 10,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $isSuccess ? 'D1FAE5' : 'FEE2E2'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => $isSuccess ? '10B981' : 'EF4444'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension(4)->setRowHeight(24);
                }

                // Styling untuk masing-masing blok card ukuran
                foreach ($this->cardRanges as $card) {
                    $tRow   = $card['title'];
                    $stRow  = $card['sub_title'];
                    $hRow   = $card['col_header'];
                    $dStart = $card['start_data'];
                    $dEnd   = $card['end_data'];
                    $fRow   = $card['footer'];

                    // 1. Header Ukuran Mesin
                    $sheet->mergeCells("A{$tRow}:G{$tRow}");
                    $sheet->getStyle("A{$tRow}:G{$tRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27272A']], // Zinc 800
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($tRow)->setRowHeight(24);

                    // 2. Sub-Header (DATA PEKERJA SANDING JOINT)
                    $sheet->mergeCells("A{$stRow}:G{$stRow}");
                    $sheet->getStyle("A{$stRow}:G{$stRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F3F46']], // Zinc 700
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($stRow)->setRowHeight(22);

                    // 3. Kolom Header
                    $sheet->getStyle("A{$hRow}:G{$hRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']], // Zinc 200
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($hRow)->setRowHeight(20);

                    // 4. Data Baris Pekerja
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

                    // Format Rupiah untuk kolom F (Potongan Target)
                    $sheet->getStyle("F{$dStart}:F{$dEnd}")->getNumberFormat()->setFormatCode('"Rp "#,##0;("Rp "#,##0);"-"');

                    // 5. Footer Summary Baris Bawah
                    $sheet->mergeCells("A{$fRow}:G{$fRow}");
                    $sheet->getStyle("A{$fRow}:G{$fRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '27272A'], 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F5']], // Zinc 100
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($fRow)->setRowHeight(22);

                    // 6. Border Seluruh Tabel
                    $sheet->getStyle("A{$tRow}:G{$fRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'D4D4D8'],
                            ],
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color'       => ['rgb' => '71717A'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}
