<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class RekapSandingExport implements FromArray, WithTitle, WithStyles, WithEvents
{
    protected array $rekapPerMesin;
    protected ?string $tanggalAwal;
    protected ?string $tanggalAkhir;

    /** Dipakai styles()/registerEvents() untuk tahu posisi tiap blok di sheet. */
    protected array $layout = [];

    public function __construct(array $rekapPerMesin, ?string $tanggalAwal, ?string $tanggalAkhir)
    {
        $this->rekapPerMesin = $rekapPerMesin;
        $this->tanggalAwal = $tanggalAwal;
        $this->tanggalAkhir = $tanggalAkhir;
    }

    public function title(): string
    {
        return 'Rekap Sanding';
    }

    protected function maxKolomUkuran(): int
    {
        $max = 0;
        foreach ($this->rekapPerMesin as $data) {
            $max = max($max, count($data['daftarUkuran']));
        }
        return $max;
    }

    /** Jumlah kolom tabel ukuran: Tanggal, Shift, ...ukuran (max)..., Total */
    protected function totalCols(): int
    {
        return max(4, 2 + $this->maxKolomUkuran() + 1);
    }

    /**
     * Baris pemisah/spacer. PENTING: jangan pakai array kosong `[]` —
     * Laravel Excel/PhpSpreadsheet men-skip baris yang benar-benar kosong
     * saat menulis ke sheet, sehingga baris itu tidak "memakan" nomor baris
     * dan semua konten di bawahnya jadi geser ke atas. Dengan 1 sel string
     * kosong, baris tetap dianggap ada meski tampil kosong secara visual.
     */
    protected function spacerRow(): array
    {
        return [''];
    }

    public function array(): array
    {
        $rows = [];
        $this->layout = [];

        $judulRange = Carbon::parse($this->tanggalAwal)->format('d M Y') . ' - ' . Carbon::parse($this->tanggalAkhir)->format('d M Y');

        $rows[] = ['REKAP PRODUKSI SANDING'];
        $rows[] = [$judulRange];
        $rows[] = $this->spacerRow();

        foreach ($this->rekapPerMesin as $kategori => $data) {
            $rekapTanggal = $data['rekapTanggal'];
            $rekapUkuran = $data['rekapUkuran'];
            $daftarUkuran = $data['daftarUkuran'];

            // ── Judul Kategori Mesin ──
            $rows[] = ["MESIN SANDING " . strtoupper($kategori)];
            // Nomor baris diambil dari count($rows) SETELAH push,
            // jadi selalu sinkron dengan baris yang benar-benar tertulis.
            $this->layout[] = ['type' => 'kategori', 'row' => count($rows)];

            $rows[] = $this->spacerRow();

            // ── Tabel 1: Rekap Per Tanggal ──
            $rows[] = ['Tanggal', 'Pagi', 'Malam', 'Total'];
            $tabel1HeaderRow = count($rows);

            $totalPagi = 0; $totalMalam = 0; $totalAllT1 = 0;
            foreach ($rekapTanggal as $r) {
                $rows[] = [
                    Carbon::parse($r['tanggal'])->format('d-m-Y'),
                    $r['pagi'],
                    $r['malam'],
                    $r['total'],
                ];
                $totalPagi += $r['pagi'];
                $totalMalam += $r['malam'];
                $totalAllT1 += $r['total'];
            }
            $rows[] = ['TOTAL', $totalPagi, $totalMalam, $totalAllT1];
            $tabel1FooterRow = count($rows);

            $this->layout[] = [
                'type' => 'tabel1',
                'headerRow' => $tabel1HeaderRow,
                'footerRow' => $tabel1FooterRow,
            ];

            $rows[] = $this->spacerRow();

            // ── Tabel 2: Rekap Per Tanggal, Per Shift, Per Ukuran ──
            $header = ['Tanggal', 'Shift'];
            foreach ($daftarUkuran as $u) {
                $header[] = $u;
            }
            $header[] = 'Total';
            $rows[] = $header;
            $tabel2HeaderRow = count($rows);

            $totalPerUkuran = array_fill_keys($daftarUkuran, 0);
            $grandTotalT2 = 0;
            $mergeRanges = [];
            $currentTanggal = null;
            $mergeStart = null;
            $mergeCount = 0;

            foreach ($rekapUkuran as $r) {
                $row = [
                    Carbon::parse($r['tanggal'])->format('d-m-Y'),
                    $r['shift'],
                ];
                foreach ($daftarUkuran as $u) {
                    $qty = $r['ukuran'][$u] ?? 0;
                    $row[] = $qty;
                    $totalPerUkuran[$u] += $qty;
                }
                $row[] = $r['total'];
                $grandTotalT2 += $r['total'];
                $rows[] = $row;
                $thisRow = count($rows);

                // Hitung rowspan(merge) untuk kolom tanggal — pakai nomor baris NYATA
                // ($thisRow, dari count($rows)), bukan hasil hitungan manual.
                if ($r['tanggal'] !== $currentTanggal) {
                    if ($currentTanggal !== null && $mergeCount > 1) {
                        $mergeRanges[] = ['start' => $mergeStart, 'span' => $mergeCount];
                    }
                    $currentTanggal = $r['tanggal'];
                    $mergeStart = $thisRow;
                    $mergeCount = 1;
                } else {
                    $mergeCount++;
                }
            }
            if ($currentTanggal !== null && $mergeCount > 1) {
                $mergeRanges[] = ['start' => $mergeStart, 'span' => $mergeCount];
            }

            $totalRow = ['TOTAL', ''];
            foreach ($daftarUkuran as $u) {
                $totalRow[] = $totalPerUkuran[$u];
            }
            $totalRow[] = $grandTotalT2;
            $rows[] = $totalRow;
            $tabel2FooterRow = count($rows);

            $this->layout[] = [
                'type' => 'tabel2',
                'headerRow' => $tabel2HeaderRow,
                'footerRow' => $tabel2FooterRow,
                'jumlahKolomUkuran' => count($daftarUkuran),
                'mergeRanges' => $mergeRanges,
            ];

            $rows[] = $this->spacerRow();
            $rows[] = $this->spacerRow();
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = Coordinate::stringFromColumnIndex($this->totalCols());

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

        foreach ($this->layout as $blok) {
            if ($blok['type'] === 'kategori') {
                $row = $blok['row'];
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13)->setColor(
                    new Color(Color::COLOR_WHITE)
                );
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
                continue;
            }

            if ($blok['type'] === 'tabel1') {
                $headerRow = $blok['headerRow'];
                $footerRow = $blok['footerRow'];

                $sheet->getStyle("A{$headerRow}:D{$headerRow}")->getFont()->setBold(true)->setColor(
                    new Color(Color::COLOR_WHITE)
                );
                $sheet->getStyle("A{$headerRow}:D{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle("A{$headerRow}:D{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$footerRow}:D{$footerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$footerRow}:D{$footerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

                $sheet->getStyle("B" . ($headerRow + 1) . ":D{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($headerRow + 1) . ":A{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$headerRow}:D{$footerRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                continue;
            }

            if ($blok['type'] === 'tabel2') {
                $headerRow = $blok['headerRow'];
                $footerRow = $blok['footerRow'];
                $lastColTabel2 = Coordinate::stringFromColumnIndex(2 + $blok['jumlahKolomUkuran'] + 1);

                $sheet->getStyle("A{$headerRow}:{$lastColTabel2}{$headerRow}")->getFont()->setBold(true)->setColor(
                    new Color(Color::COLOR_WHITE)
                );
                $sheet->getStyle("A{$headerRow}:{$lastColTabel2}{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
                $sheet->getStyle("A{$headerRow}:{$lastColTabel2}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$footerRow}:{$lastColTabel2}{$footerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$footerRow}:{$lastColTabel2}{$footerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

                $sheet->getStyle("C" . ($headerRow + 1) . ":{$lastColTabel2}{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($headerRow + 1) . ":B{$footerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A{$headerRow}:{$lastColTabel2}{$footerRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Merge kolom Tanggal untuk baris dengan 2 shift (Pagi + Malam)
                foreach ($blok['mergeRanges'] as $range) {
                    $rowAwal = $range['start'];
                    $rowAkhir = $rowAwal + $range['span'] - 1;
                    $sheet->mergeCells("A{$rowAwal}:A{$rowAkhir}");
                    $sheet->getStyle("A{$rowAwal}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }
                continue;
            }
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }
        $sheet->getColumnDimension('A')->setWidth(16);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Placeholder jika ada penyesuaian tambahan di masa depan
            },
        ];
    }
}