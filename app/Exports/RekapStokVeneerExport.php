<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Rekap Stok Veneer ke Excel.
 *
 * Struktur kolom:
 *   A  : Ukuran (p×l×t mm)
 *   B  : Jenis Kayu
 *   C  : KW
 *   D  : {localLabel} Basah
 *   E  : {localLabel} Kering
 *   F  : {localLabel} Jadi
 *   G  : {externalLabel} Basah
 *   H  : {externalLabel} Kering
 *   I  : {externalLabel} Jadi
 *   J  : Total
 *
 * Satu baris per kombinasi (ukuran × KW).
 */
class RekapStokVeneerExport implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        protected array $stocks,
        protected array $kws,
        protected string $localLabel,
        protected string $externalLabel,
        protected string $tanggal,
    ) {}

    public function title(): string
    {
        return 'Rekap Stok Veneer';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build rows
    // ─────────────────────────────────────────────────────────────────────────

    public function array(): array
    {
        $rows = [];

        // Baris 1 – judul
        $rows[] = ['Rekap Stok Veneer', null, null, null, null, null, null, null, null, null];

        // Baris 2 – tanggal
        $rows[] = ['Per tanggal: ' . $this->tanggal, null, null, null, null, null, null, null, null, null];

        // Baris 3 – kosong
        $rows[] = [null, null, null, null, null, null, null, null, null, null];

        // Baris 4 – header grup kolom (merge)
        $rows[] = [
            null, null, null,
            $this->localLabel,    null, null,
            $this->externalLabel, null, null,
            null,
        ];

        // Baris 5 – sub-header
        $rows[] = [
            'Ukuran', 'Jenis Kayu', 'KW',
            'Basah', 'Kering', 'Jadi',
            'Basah', 'Kering', 'Jadi',
            'Total',
        ];

        // Data rows
        foreach ($this->stocks as $group) {
            // Tentukan KW yang relevan untuk grup ini
            $activeKws = [];
            foreach ($this->kws as $kw) {
                $total =
                    ($group['localBasah'][$kw] ?? 0) +
                    ($group['localKering'][$kw] ?? 0) +
                    ($group['localJadi'][$kw] ?? 0) +
                    ($group['externalBasah'][$kw] ?? 0) +
                    ($group['externalKering'][$kw] ?? 0) +
                    ($group['externalJadi'][$kw] ?? 0);

                if ($total != 0) {
                    $activeKws[] = $kw;
                }
            }

            if (empty($activeKws)) {
                continue;
            }

            $ukuran    = (float) $group['panjang'] . ' × ' . (float) $group['lebar'] . ' × ' . (float) $group['tebal'] . ' mm';
            $jenisKayu = $group['jenis_kayu'];
            $isFirst   = true;

            foreach ($activeKws as $kw) {
                $lBasah   = $group['localBasah'][$kw]    ?? 0;
                $lKering  = $group['localKering'][$kw]   ?? 0;
                $lJadi    = $group['localJadi'][$kw]     ?? 0;
                $eBasah   = $group['externalBasah'][$kw]  ?? 0;
                $eKering  = $group['externalKering'][$kw] ?? 0;
                $eJadi    = $group['externalJadi'][$kw]   ?? 0;
                $total    = $lBasah + $lKering + $lJadi + $eBasah + $eKering + $eJadi;

                $rows[] = [
                    $isFirst ? $ukuran    : '',
                    $isFirst ? $jenisKayu : '',
                    'KW ' . $kw,
                    $lBasah  ?: '',
                    $lKering ?: '',
                    $lJadi   ?: '',
                    $eBasah  ?: '',
                    $eKering ?: '',
                    $eJadi   ?: '',
                    $total   ?: '',
                ];

                $isFirst = false;
            }
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Styling
    // ─────────────────────────────────────────────────────────────────────────

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet     = $event->sheet->getDelegate();
                $lastRow   = $sheet->getHighestRow();
                $lastCol   = 'J'; // 10 kolom (A–J)
                $lastColIdx = 10;

                // ── Lebar kolom ──────────────────────────────────────────────
                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(10);
                foreach (['D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(14);
                }

                // ── Baris 1: Judul ───────────────────────────────────────────
                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D6F42']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // ── Baris 2: Tanggal ─────────────────────────────────────────
                $sheet->mergeCells('A2:J2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font'      => ['italic' => true, 'color' => ['rgb' => '374151']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Baris 4: Grup header (local / external) ──────────────────
                // D4:F4  → localLabel
                $sheet->mergeCells('D4:F4');
                // G4:I4  → externalLabel
                $sheet->mergeCells('G4:I4');

                $sheet->getStyle('D4:J4')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                // Kolom Total (J4) beda warna
                $sheet->getStyle('J4')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(20);

                // ── Baris 5: Sub-header ──────────────────────────────────────
                $sheet->getStyle('A5:J5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
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
                $sheet->getRowDimension(5)->setRowHeight(18);

                // ── Data rows (baris 6 s/d akhir) ───────────────────────────
                if ($lastRow >= 6) {
                    // Border seluruh data
                    $sheet->getStyle("A6:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Warna kolom Total (J) pada data
                    $sheet->getStyle("J6:J{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Kolom angka (D–I) rata tengah
                    $sheet->getStyle("D6:I{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // KW (C) rata tengah
                    $sheet->getStyle("C6:C{$lastRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Ukuran (A) & Jenis Kayu (B) – wrap text
                    $sheet->getStyle("A6:B{$lastRow}")->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);

                    // Zebra striping per baris ganjil
                    for ($r = 6; $r <= $lastRow; $r++) {
                        if ($r % 2 === 0) {
                            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType'   => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F9FAFB'],
                                ],
                            ]);
                        }
                    }
                }

                // ── Freeze panes pada baris 6 ────────────────────────────────
                $sheet->freezePane('A6');

                // ── Auto filter pada baris 5 ─────────────────────────────────
                $sheet->setAutoFilter("A5:{$lastCol}5");
            },
        ];
    }
}
