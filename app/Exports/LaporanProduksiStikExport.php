<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LaporanProduksiStikExport implements WithMultipleSheets
{
    protected array $dataStik;

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiStikSheetPekerja($this->dataStik),
            new LaporanProduksiStikSheetHasil($this->dataStik),
        ];
    }
}

class LaporanProduksiStikSheetPekerja implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected Collection $dataStik;
    protected array $cardRanges = [];

    public function __construct(array $dataStik)
    {
        $this->dataStik = collect($dataStik);
    }

    public function collection(): Collection
    {
        $allRows = [];
        $this->cardRanges = [];

        if ($this->dataStik->isEmpty()) {
            return collect([['Tidak ada data produksi stik untuk tanggal ini.']]);
        }

        // Header Dokumen Utama
        $allRows[] = ['LAPORAN TERPADU PRODUKSI STIK'];
        $firstTanggal = $this->dataStik->first()['tanggal'] ?? date('d/m/Y');
        $allRows[] = ['TANGGAL PRODUKSI: ' . $firstTanggal];
        $allRows[] = array_fill(0, 7, '');

        foreach ($this->dataStik as $produksi) {
            $mesinNama     = $produksi['mesin'] ?? 'MESIN STIK';
            $tanggalFormat = $produksi['tanggal'] ?? $firstTanggal;

            $daftarHasil   = $produksi['daftar_hasil'] ?? [];
            $pekerja       = $produksi['pekerja'] ?? [];

            $hasilPalet    = (int) ($produksi['hasil_palet'] ?? count($daftarHasil));
            $totalLembar   = (int) ($produksi['total_lembar'] ?? 0);
            $targetPalet   = (int) ($produksi['target_palet'] ?? ($produksi['target'] ?? 9));
            $selisihPalet  = (int) ($produksi['selisih_palet'] ?? ($produksi['selisih'] ?? ($hasilPalet - $targetPalet)));
            $jamKerja      = (float) ($produksi['jam_kerja'] ?? 9.0);

            $totalDowntime = $produksi['total_downtime_formatted'] ?? ($produksi['total_downtime'] ?? '-');
            $kendala       = $produksi['kendala'] ?? '-';
            $daftarKendala = $produksi['daftar_kendala'] ?? [];
            $totalPekerja  = count($pekerja);

            // 1. JUDUL HEADER CARD
            $titleRow = count($allRows) + 1;
            $allRows[] = ['PRODUKSI: ' . strtoupper($mesinNama) . '  |  TANGGAL: ' . $tanggalFormat, '', '', '', '', '', ''];

            // 2. SUBHEADER SECTION 1: RINCIAN HASIL STIK
            $sec1TitleRow = count($allRows) + 1;
            $allRows[] = ['1. RINCIAN HASIL STIK (PER NOMOR PALET)', '', '', '', '', '', ''];

            // 3. HEADER KOLOM HASIL PALET
            $sec1HeaderRow = count($allRows) + 1;
            $allRows[] = ['No. Palet', 'Jenis Kayu', 'Ukuran (P x L x T)', 'Kualitas (KW)', 'Total Lembar', '', ''];

            // 4. BARIS DATA HASIL PALET
            $sec1DataStart = count($allRows) + 1;
            if (empty($daftarHasil)) {
                $allRows[] = ['-', 'Belum ada data palet stik terdaftar.', '-', '-', 0, '', ''];
            } else {
                foreach ($daftarHasil as $h) {
                    $allRows[] = [
                        $h['no_palet']    ?? '-',
                        $h['jenis_kayu']  ?? '-',
                        $h['ukuran']      ?? '-',
                        $h['kualitas']    ?? '-',
                        (int) ($h['total_lembar'] ?? 0),
                        '',
                        ''
                    ];
                }
            }
            $sec1DataEnd = count($allRows);

            // 5. SUBTOTAL HASIL LEMBAR
            $sec1FooterRow = count($allRows) + 1;
            $allRows[] = ['SUBTOTAL HASIL (LEMBAR):', '', '', '', $totalLembar, '', ''];

            // 6. SUBHEADER SECTION 2: DATA PEKERJA
            $sec2TitleRow = count($allRows) + 1;
            $allRows[] = ['2. DATA PEKERJA SHIFT INI', '', '', '', '', '', ''];

            // 7. HEADER KOLOM PEKERJA
            $sec2HeaderRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama Pekerja', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan'];

            // 8. BARIS DATA PEKERJA
            $sec2DataStart = count($allRows) + 1;
            if (empty($pekerja)) {
                $allRows[] = ['-', 'Tidak ada data pekerja untuk shift ini.', '-', '-', '-', 0, '-'];
            } else {
                foreach ($pekerja as $p) {
                    $potRaw = (int) str_replace(['.', 'Rp ', '-', ' '], '', $p['pot_target'] ?? '0');
                    $allRows[] = [
                        $p['id']         ?? '-',
                        $p['nama']       ?? '-',
                        $p['jam_masuk']  ?? '-',
                        $p['jam_pulang'] ?? '-',
                        $p['ijin']       ?? '-',
                        $potRaw > 0 ? $potRaw : 0,
                        $p['keterangan'] ?? '-',
                    ];
                }
            }
            $sec2DataEnd = count($allRows);

            // 9. SUMMARY FOOTER BARIS 1 (SATUAN PALET)
            $tandaSelisih = $selisihPalet > 0 ? '+' : '';
            $summaryText = "Pekerja: {$totalPekerja} Orang  |  Target: {$targetPalet} Palet  |  Jam Produksi: " . number_format($jamKerja, 1) . " jam" .
                "  |  Hasil: {$hasilPalet} Palet (" . number_format($totalLembar, 0, ',', '.') . " Lembar)" .
                "  |  Selisih: {$tandaSelisih}{$selisihPalet} Palet  |  Total Downtime: {$totalDowntime}";

            $summaryRow1 = count($allRows) + 1;
            $allRows[] = [$summaryText, '', '', '', '', '', ''];

            // 10. SUMMARY FOOTER BARIS 2 (KENDALA JIKA ADA)
            $summaryRow2 = null;
            $textKendala = '';
            if (!empty($daftarKendala)) {
                $listK = [];
                foreach ($daftarKendala as $k) {
                    $str = $k['kendala'] ?? '';
                    if (!empty($k['durasi_menit'])) $str .= " ({$k['durasi_menit']} menit)";
                    $listK[] = $str;
                }
                $textKendala = 'Kendala: ' . implode(', ', $listK);
            } elseif (!empty($kendala) && $kendala !== '-' && $kendala !== 'Tidak ada kendala.') {
                $textKendala = 'Kendala: ' . $kendala;
            }

            if (!empty($textKendala)) {
                $summaryRow2 = count($allRows) + 1;
                $allRows[] = [$textKendala, '', '', '', '', '', ''];
            }

            $this->cardRanges[] = [
                'title'            => $titleRow,
                'sec1_title'       => $sec1TitleRow,
                'sec1_header'      => $sec1HeaderRow,
                'sec1_start'       => $sec1DataStart,
                'sec1_end'         => $sec1DataEnd,
                'sec1_footer'      => $sec1FooterRow,
                'sec2_title'       => $sec2TitleRow,
                'sec2_header'      => $sec2HeaderRow,
                'sec2_start'       => $sec2DataStart,
                'sec2_end'         => $sec2DataEnd,
                'summary_1'        => $summaryRow1,
                'summary_2'        => $summaryRow2,
            ];

            // Space antar card
            $allRows[] = array_fill(0, 7, '');
            $allRows[] = array_fill(0, 7, '');
        }

        return collect($allRows);
    }

    public function headings(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Laporan Produksi Stik';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set Lebar Kolom A..G
                $sheet->getColumnDimension('A')->setWidth(14); // No Palet / ID
                $sheet->getColumnDimension('B')->setWidth(26); // Jenis Kayu / Nama
                $sheet->getColumnDimension('C')->setWidth(26); // Ukuran / Jam Masuk
                $sheet->getColumnDimension('D')->setWidth(16); // KW / Pulang
                $sheet->getColumnDimension('E')->setWidth(18); // Total Lembar / Ijin
                $sheet->getColumnDimension('F')->setWidth(22); // Potongan Target
                $sheet->getColumnDimension('G')->setWidth(28); // Keterangan

                // Styling Judul Utama Dokumen
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('71717A'));

                foreach ($this->cardRanges as $card) {
                    $tRow   = $card['title'];
                    $s1Title = $card['sec1_title'];
                    $s1H    = $card['sec1_header'];
                    $s1Start = $card['sec1_start'];
                    $s1End  = $card['sec1_end'];
                    $s1Foot = $card['sec1_footer'];

                    $s2Title = $card['sec2_title'];
                    $s2H    = $card['sec2_header'];
                    $s2Start = $card['sec2_start'];
                    $s2End  = $card['sec2_end'];

                    $sum1   = $card['summary_1'];
                    $sum2   = $card['summary_2'];
                    $lastRow = $sum2 ?? $sum1;

                    // 1. Header Card Utama (Background Zinc 800)
                    $sheet->mergeCells("A{$tRow}:G{$tRow}");
                    $sheet->getStyle("A{$tRow}:G{$tRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27272A']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($tRow)->setRowHeight(24);

                    // Subheader 1
                    $sheet->mergeCells("A{$s1Title}:G{$s1Title}");
                    $sheet->getStyle("A{$s1Title}:G{$s1Title}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'D97706'], 'size' => 10], // Amber-600
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], // Amber-100
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    ]);
                    $sheet->getRowDimension($s1Title)->setRowHeight(20);

                    // Header Kolom Section 1
                    $sheet->mergeCells("E{$s1H}:G{$s1H}");
                    $sheet->getStyle("A{$s1H}:G{$s1H}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 9],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$s1H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$s1H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$s1H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("D{$s1H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$s1H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Data Baris Section 1
                    for ($r = $s1Start; $r <= $s1End; $r++) {
                        $sheet->mergeCells("E{$r}:G{$r}");
                    }
                    $sheet->getStyle("A{$s1Start}:G{$s1End}")->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$s1Start}:A{$s1End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$s1Start}:D{$s1End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$s1Start}:E{$s1End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E{$s1Start}:E{$s1End}")->getNumberFormat()->setFormatCode('#,##0');

                    // Footer Subtotal Section 1
                    $sheet->mergeCells("A{$s1Foot}:D{$s1Foot}");
                    $sheet->mergeCells("E{$s1Foot}:G{$s1Foot}");
                    $sheet->getStyle("A{$s1Foot}:G{$s1Foot}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'D97706']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$s1Foot}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E{$s1Foot}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E{$s1Foot}")->getNumberFormat()->setFormatCode('#,##0" Lembar"');

                    // Subheader 2
                    $sheet->mergeCells("A{$s2Title}:G{$s2Title}");
                    $sheet->getStyle("A{$s2Title}:G{$s2Title}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '2563EB'], 'size' => 10], // Blue-600
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']], // Blue-100
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    ]);
                    $sheet->getRowDimension($s2Title)->setRowHeight(20);

                    // Header Kolom Section 2
                    $sheet->getStyle("A{$s2H}:G{$s2H}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 9],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$s2H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$s2H}:E{$s2H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$s2H}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Data Baris Section 2
                    $sheet->getStyle("A{$s2Start}:G{$s2End}")->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$s2Start}:A{$s2End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$s2Start}:E{$s2End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$s2Start}:F{$s2End}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("F{$s2Start}:F{$s2End}")->getNumberFormat()->setFormatCode('"Rp "#,##0;("Rp "#,##0);"-"');

                    // Summary Baris 1
                    $sheet->mergeCells("A{$sum1}:G{$sum1}");
                    $sheet->getStyle("A{$sum1}:G{$sum1}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 9.5],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F5']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($sum1)->setRowHeight(22);

                    // Summary Baris 2 (Kendala)
                    if ($sum2) {
                        $sheet->mergeCells("A{$sum2}:G{$sum2}");
                        $sheet->getStyle("A{$sum2}:G{$sum2}")->applyFromArray([
                            'font' => ['italic' => true, 'color' => ['rgb' => 'DC2626'], 'size' => 9], // Red-600
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($sum2)->setRowHeight(18);
                    }

                    // Border Grid untuk Seluruh Card
                    $sheet->getStyle("A{$tRow}:G{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E4E4E7'],
                            ],
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '71717A'],
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}

class LaporanProduksiStikSheetHasil implements FromArray, WithTitle, WithStyles
{
    protected array $dataStik;
    protected array $styleMap    = [];
    protected array $mergeRanges = [];

    public function __construct(array $dataStik)
    {
        $this->dataStik = $dataStik;
    }

    public function array(): array
    {
        $rows     = [];
        $rowIndex = 1;

        if (empty($this->dataStik)) {
            $rows[] = ['Tidak ada data untuk tanggal ini.'];
            return $rows;
        }

        foreach ($this->dataStik as $produksi) {
            $tanggal     = $produksi['tanggal'] ?? '-';
            $daftarHasil = $produksi['daftar_hasil'] ?? [];
            $pekerja     = $produksi['pekerja'] ?? [];
            $totalPkrj   = count($pekerja);
            $mesinNama   = $produksi['mesin'] ?? 'MESIN STIK';

            // Judul Section
            $rows[] = ["REKAP PALET HASIL STIK - {$mesinNama} ({$tanggal})", '', '', '', '', '', ''];
            $this->styleMap[$rowIndex] = 'section_title';
            $rowIndex++;

            // Header Kolom
            $rows[] = ['No. Palet', 'Jenis Kayu', 'Ukuran (P x L x T)', 'Kualitas (KW)', 'Total Lembar', 'Pekerja', 'Tanggal'];
            $this->styleMap[$rowIndex] = 'col_header';
            $rowIndex++;

            $dataStart = $rowIndex;
            if (empty($daftarHasil)) {
                $rows[] = ['-', '-', '-', '-', 0, $totalPkrj, $tanggal];
                $this->styleMap[$rowIndex] = 'data';
                $rowIndex++;
            } else {
                foreach ($daftarHasil as $i => $h) {
                    $rows[] = [
                        $h['no_palet']   ?? '-',
                        $h['jenis_kayu'] ?? '-',
                        $h['ukuran']     ?? '-',
                        $h['kualitas']   ?? '-',
                        (int) ($h['total_lembar'] ?? 0),
                        $i === 0 ? $totalPkrj : '',
                        $i === 0 ? $tanggal : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEnd = $rowIndex - 1;
                if (count($daftarHasil) > 1) {
                    $this->mergeRanges[] = "F{$dataStart}:F{$dataEnd}";
                    $this->mergeRanges[] = "G{$dataStart}:G{$dataEnd}";
                }
            }

            // Separator
            $rows[] = array_fill(0, 7, '');
            $rowIndex++;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Rekap Hasil Stik';
    }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F4E79';
        $blueLight = '2E75B6';

        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        foreach ($this->styleMap as $rowNum => $type) {
            switch ($type) {
                case 'section_title':
                    $sheet->mergeCells("A{$rowNum}:G{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blueDark]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(24);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $blueLight]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(20);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:G{$rowNum}")->applyFromArray([
                        'font' => ['size' => 9],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'BDD7EE'],
                            ],
                        ],
                    ]);
                    $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("F{$rowNum}:G{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($rowNum)->setRowHeight(18);
                    break;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(16);

        return [];
    }
}
