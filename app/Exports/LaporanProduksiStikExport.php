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

// ============================================================
//  MAIN EXPORT — Membungkus 2 Sheet (Pekerja & Hasil)
// ============================================================
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

// ============================================================
//  SHEET 1 — "Laporan Produksi Stik" (Sesuai Desain Web Card)
// ============================================================
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
            return collect([['Tidak ada data produksi untuk tanggal ini.']]);
        }

        $allRows[] = ['LAPORAN PRODUKSI STIK'];
        $allRows[] = ['TANGGAL: ' . ($this->dataStik->first()['tanggal'] ?? '')];
        $allRows[] = array_fill(0, 7, '');

        foreach ($this->dataStik as $produksi) {
            $mesinNama     = $produksi['mesin'] ?? 'MESIN STIK';
            $kodeUkuran    = $produksi['ukuran'] ?? 'TIDAK ADA UKURAN';
            $pekerja       = $produksi['pekerja'] ?? [];
            $target        = (int) ($produksi['target'] ?? 0);
            $jamKerja      = (float) ($produksi['jam_kerja'] ?? 9);
            $hasil         = (int) ($produksi['hasil'] ?? 0);
            $selisih       = (int) ($produksi['selisih'] ?? 0);
            $tanggal       = $produksi['tanggal'] ?? '-';
            $totalDowntime = $produksi['total_downtime_formatted'] ?? '-';
            $kendala       = $produksi['kendala'] ?? '-';
            $totalPekerja  = count($pekerja);

            // 1. JUDUL HEADER CARD
            $titleRow = count($allRows) + 1;
            $allRows[] = ['PEKERJA STIK: ' . strtoupper($mesinNama) . ' - ' . strtoupper($kodeUkuran), '', '', '', '', '', ''];

            // 2. HEADER TABEL "DATA PEKERJA"
            $subTitleRow = count($allRows) + 1;
            $allRows[] = ['DATA PEKERJA', '', '', '', '', '', ''];

            // 3. HEADER KOLOM PEKERJA (7 Kolom)
            $colHeaderRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan'];

            // 4. DATA BARIS PEKERJA
            $workerStartRow = count($allRows) + 1;
            if (empty($pekerja)) {
                $allRows[] = ['-', 'Tidak ada data pekerja untuk ukuran ini.', '-', '-', '-', 0, '-'];
            } else {
                foreach ($pekerja as $p) {
                    $potRaw = (int) str_replace(['.', 'Rp ', '-', ' '], '', $p['pot_target'] ?? '0');
                    $allRows[] = [
                        $p['id']         ?? '-',
                        $p['nama']       ?? '-',
                        $p['jam_masuk']  ?? '-',
                        $p['jam_pulang'] ?? '-',
                        $p['ijin']       ?? '-',
                        $potRaw > 0 ? (int) $potRaw : 0,
                        $p['keterangan'] ?? '-',
                    ];
                }
            }
            $workerEndRow = count($allRows);

            // 5. FOOTER SUMMARY BARIS 1 (Format Ringkasan)
            $tanda = $selisih >= 0 ? '+' : '';
            $summaryText = "Pekerja: {$totalPekerja} | Target: " . number_format($target, 0, ',', '.') .
                " | Jam Produksi: " . number_format($jamKerja, 1) . " jam" .
                " | Hasil: " . number_format($hasil, 0, ',', '.') .
                " | Selisih: {$tanda}" . number_format($selisih, 0, ',', '.') .
                " | Tanggal: {$tanggal} | Total Downtime: {$totalDowntime}";

            $footerRow1 = count($allRows) + 1;
            $allRows[] = [$summaryText, '', '', '', '', '', ''];

            // 6. FOOTER BARIS 2 (Kendala jika ada)
            $hasKendala = (!empty($kendala) && $kendala !== 'Tidak ada kendala.' && $kendala !== '-');
            $footerRow2 = null;
            if ($hasKendala) {
                $footerRow2 = count($allRows) + 1;
                $allRows[] = ["Kendala: {$kendala}", '', '', '', '', '', ''];
            }

            // Simpan posisi baris untuk styling dinamis
            $this->cardRanges[] = [
                'title'       => $titleRow,
                'sub_title'   => $subTitleRow,
                'col_header'  => $colHeaderRow,
                'start_data'  => $workerStartRow,
                'end_data'    => $workerEndRow,
                'footer_1'    => $footerRow1,
                'footer_2'    => $footerRow2,
            ];

            // Jarak pemisah antar card
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

                // Lebar 7 Kolom
                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(26);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(20);
                $sheet->getColumnDimension('G')->setWidth(35);

                // Style Judul Utama Dokumen
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

                foreach ($this->cardRanges as $card) {
                    $tRow   = $card['title'];
                    $stRow  = $card['sub_title'];
                    $hRow   = $card['col_header'];
                    $dStart = $card['start_data'];
                    $dEnd   = $card['end_data'];
                    $fRow1  = $card['footer_1'];
                    $fRow2  = $card['footer_2'];
                    $lastRow = $fRow2 ?? $fRow1;

                    // 1. Header Card (PEKERJA STIK: ...)
                    $sheet->mergeCells("A{$tRow}:G{$tRow}");
                    $sheet->getStyle("A{$tRow}:G{$tRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27272A']], // Zinc 800
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($tRow)->setRowHeight(24);

                    // 2. Sub-Header (DATA PEKERJA)
                    $sheet->mergeCells("A{$stRow}:G{$stRow}");
                    $sheet->getStyle("A{$stRow}:G{$stRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3F3F46']], // Zinc 700
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($stRow)->setRowHeight(22);

                    // 3. Kolom Header (ID, Nama, Masuk, Pulang, ...)
                    $sheet->getStyle("A{$hRow}:G{$hRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '18181B'], 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E4E4E7']], // Zinc 200
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($hRow)->setRowHeight(20);

                    // 4. Data Baris Pekerja (Border & Alignment)
                    $sheet->getStyle("A{$dStart}:G{$dEnd}")->applyFromArray([
                        'font' => ['size' => 10],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Alignment per kolom
                    $sheet->getStyle("A{$dStart}:A{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$dStart}:B{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$dStart}:C{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$dStart}:D{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$dStart}:E{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$dStart}:F{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("G{$dStart}:G{$dEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Format Rupiah untuk kolom Potongan Target (F)
                    $sheet->getStyle("F{$dStart}:F{$dEnd}")->getNumberFormat()->setFormatCode('"Rp "#,##0;("Rp "#,##0);"-"');

                    // 5. Footer Summary Baris 1
                    $sheet->mergeCells("A{$fRow1}:G{$fRow1}");
                    $sheet->getStyle("A{$fRow1}:G{$fRow1}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '27272A'], 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F4F4F5']], // Zinc 100
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($fRow1)->setRowHeight(22);

                    // 6. Footer Kendala Baris 2 (jika ada)
                    if ($fRow2) {
                        $sheet->mergeCells("A{$fRow2}:G{$fRow2}");
                        $sheet->getStyle("A{$fRow2}:G{$fRow2}")->applyFromArray([
                            'font' => ['italic' => true, 'color' => ['rgb' => 'CA8A04'], 'size' => 9], // Yellow-600
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($fRow2)->setRowHeight(18);
                    }

                    // 7. Seluruh Kotak Tabel (Grid Border)
                    $sheet->getStyle("A{$tRow}:G{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D4D4D8'], // Zinc 300
                            ],
                            'outline' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '71717A'], // Zinc 500
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}

// ============================================================
//  SHEET 2 — "Hasil Stik"
//  (Rincian Hasil P x L x T & Rekap Kw1 - Kw4 / AF)
// ============================================================
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
            $tanggal      = $produksi['tanggal']      ?? '-';
            $pekerja      = $produksi['pekerja']      ?? [];
            $detailHasil  = $produksi['detail_hasil']  ?? [];
            $totalPekerja = count($pekerja);
            $kodeUkuran   = $produksi['ukuran']       ?? 'STIK';

            // ── JUDUL SEKSI CARD ───────────────────────────────────
            $rows[] = ["PRODUKSI STIK - {$kodeUkuran}", '', '', '', '', '', '', '', '', '', '', ''];
            $this->styleMap[$rowIndex] = 'section_title';
            $rowIndex++;

            // ── HEADER KOLOM ───────────────────────────────────────
            $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'kw af', 'byk', 'TTL PKJ'];
            $this->styleMap[$rowIndex] = 'col_header';
            $rowIndex++;

            // ── DATA ROWS ──────────────────────────────────────────
            $dataStartRow = $rowIndex;

            if (empty($detailHasil)) {
                $rows[] = [$tanggal, '-', '-', '-', '-', '', '', '', '', '', $produksi['hasil'] ?? 0, $totalPekerja];
                $this->styleMap[$rowIndex] = 'data';
                $rowIndex++;
            } else {
                foreach ($detailHasil as $i => $detail) {
                    $rows[] = [
                        $i === 0 ? $tanggal : '',
                        $detail['panjang']    ?? '-',
                        $detail['lebar']      ?? '-',
                        $detail['tebal']      ?? '-',
                        $detail['jenis_kayu'] ?? '-',
                        $detail['kw1']        ?? '',
                        $detail['kw2']        ?? '',
                        $detail['kw3']        ?? '',
                        $detail['kw4']        ?? '',
                        $detail['af']         ?? '',
                        $detail['total']      ?? '',
                        $i === 0 ? $totalPekerja : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;

                // Merge Tanggal (A) & TTL PKJ (L) jika lebih dari 1 baris
                if (count($detailHasil) > 1) {
                    $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
                    $this->mergeRanges[] = "L{$dataStartRow}:L{$dataEndRow}";
                }
            }

            // ── BARIS PEMISAH ──────────────────────────────────────
            $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
            $rowIndex++;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Hasil Stik';
    }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F4E79';
        $blueLight = '2E75B6';

        // ── MERGE CELL ─────────────────────────────────────────────
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── STYLE PER BARIS ─────────────────────────────────────────
        foreach ($this->styleMap as $rowNum => $type) {
            switch ($type) {
                case 'section_title':
                    $sheet->mergeCells("A{$rowNum}:L{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'size'  => 12,
                            'color' => ['rgb' => 'FFFFFF'],
                            'name'  => 'Arial',
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueDark],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                            'indent'     => 1,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(26);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                            'size'  => 10,
                            'name'  => 'Arial',
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueLight],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'FFFFFF'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(20);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:L{$rowNum}")->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Arial'],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFFFFF'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'BDD7EE'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(18);
                    break;
            }
        }

        // ── LEBAR KOLOM ────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(13);
        $sheet->getColumnDimension('B')->setWidth(7);
        $sheet->getColumnDimension('C')->setWidth(7);
        $sheet->getColumnDimension('D')->setWidth(7);
        $sheet->getColumnDimension('E')->setWidth(9);
        foreach (['F', 'G', 'H', 'I', 'J', 'K'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
        $sheet->getColumnDimension('L')->setWidth(10);

        return [];
    }
}
