<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HasilProduksiSheet implements FromArray, WithTitle, WithEvents, WithColumnWidths
{
    protected array $dataProduksi;

    // Menyimpan info untuk merge & styling setelah sheet dibuat
    protected array $titleRows  = []; // ['row' => N, 'label' => '...']
    protected array $headerRows = []; // [N, ...]
    protected array $dataGroups = []; // [['start' => N, 'end' => N, 'pkj_col_start' => N, 'pkj_col_end' => N]]

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = $dataProduksi;
    }

    public function title(): string
    {
        return 'Hasil Produksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 13, // Tanggal
            'B' => 7,  // p
            'C' => 7,  // l
            'D' => 7,  // t
            'E' => 9,  // jenis
            'F' => 8,  // kw1
            'G' => 8,  // kw2
            'H' => 8,  // kw3
            'I' => 8,  // kw4
            'J' => 8,  // byk
            'K' => 10, // TTL PKJ
            'L' => 3,  // kosong
        ];
    }

    public function array(): array
    {
        $rows       = [];
        $currentRow = 1;

        $grouped = collect($this->dataProduksi)->groupBy(function ($item) {
            return $item['mesin_only'] . '||' . $item['shift'];
        });

        foreach ($grouped as $key => $produksiList) {
            [$namaMesin, $shift] = explode('||', $key);

            $first        = $produksiList->first();
            $tanggalItem  = $first['tanggal'] ?? '-';
            $totalPekerja = $produksiList->sum(fn($item) => count($item['pekerja'] ?? []));

            // ── JUDUL: simpan teks di kolom A, bukan L ──
            $judulShift = strtoupper($namaMesin) . ' ' . strtoupper($shift);
            $rows[] = [$judulShift, '', '', '', '', '', '', '', '', '', '', ''];
            $this->titleRows[] = $currentRow;
            $currentRow++;

            // ── HEADER KOLOM ──
            $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'byk', 'TTL PKJ', ''];
            $this->headerRows[] = $currentRow;
            $currentRow++;

            // ── DATA ──
            $allDetails = $produksiList->flatMap(fn($item) => $item['detail_hasils'] ?? []);
            $dataStart  = $currentRow;

            if ($allDetails->isNotEmpty()) {
                // Group by ukuran+jenis SAJA (tanpa kw) — kw dijumlah per kolom
                $subGrouped = $allDetails->groupBy(function ($dh) {
                    $p = $dh['ukuran']['p'] ?? '?';
                    $l = $dh['ukuran']['l'] ?? '?';
                    $t = $dh['ukuran']['t'] ?? '?';
                    $j = $dh['jenis_kayu'] ?? '-';
                    return "{$p}|{$l}|{$t}|{$j}";
                });

                foreach ($subGrouped as $detailGroup) {
                    $sample  = $detailGroup->first();
                    $ukuran  = $sample['ukuran'] ?? [];

                    // Sum per kw
                    $kw1 = $detailGroup->where('kw', 1)->sum('isi');
                    $kw2 = $detailGroup->where('kw', 2)->sum('isi');
                    $kw3 = $detailGroup->where('kw', 3)->sum('isi');
                    $kw4 = $detailGroup->where('kw', 4)->sum('isi');
                    $totalIsi = $kw1 + $kw2 + $kw3 + $kw4;

                    $rows[] = [
                        $tanggalItem,
                        $ukuran['p'] ?? '-',
                        $ukuran['l'] ?? '-',
                        $ukuran['t'] ?? '-',
                        $sample['jenis_kayu'] ?? '-',
                        $kw1 ?: '',
                        $kw2 ?: '',
                        $kw3 ?: '',
                        $kw4 ?: '',
                        $totalIsi,
                        $totalPekerja,
                        '',
                    ];
                    $currentRow++;
                }
            } else {
                $rows[] = [
                    $tanggalItem,
                    '-', '-', '-', '-',
                    '', '', '', '',
                    $produksiList->sum(fn($i) => $i['hasil'] ?? 0),
                    $totalPekerja,
                    '',
                ];
                $currentRow++;
            }

            // Simpan range data grup untuk merge nanti
            $this->dataGroups[] = ['start' => $dataStart, 'end' => $currentRow - 1];

            // ── PEMISAH ──
            $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // ── 1. Style semua baris berdasarkan konten ──
                for ($row = 1; $row <= $highestRow; $row++) {
                    $colAVal = (string) $sheet->getCell('A' . $row)->getValue();

                    // JUDUL GRUP — baris yang ada di titleRows
                    if (in_array($row, $this->titleRows)) {
                        $sheet->mergeCells("A{$row}:K{$row}");
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                            'font' => [
                                'bold'  => true,
                                'color' => ['rgb' => 'FFFFFF'],
                                'size'  => 14,
                                'name'  => 'Arial',
                            ],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '2E75B6'],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_LEFT,
                                'vertical'   => Alignment::VERTICAL_CENTER,
                                'indent'     => 1,
                            ],
                        ]);
                        $sheet->getRowDimension($row)->setRowHeight(28);
                        continue;
                    }

                    // HEADER KOLOM
                    if (in_array($row, $this->headerRows)) {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                            'font' => [
                                'bold'  => true,
                                'color' => ['rgb' => 'FFFFFF'],
                                'size'  => 10,
                                'name'  => 'Arial',
                            ],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '1F4E79'],
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
                        $sheet->getRowDimension($row)->setRowHeight(20);
                        continue;
                    }

                    // BARIS DATA
                    if ($colAVal !== '') {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
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
                        $sheet->getRowDimension($row)->setRowHeight(16);
                    }
                }

                // ── 2. Merge kolom Tanggal (A) dan TTL PKJ (L) per grup ──
                foreach ($this->dataGroups as $group) {
                    $start = $group['start'];
                    $end   = $group['end'];

                    if ($start >= $end) continue; // hanya 1 baris, tidak perlu merge

                    // Merge kolom A (Tanggal) — semua baris dalam grup punya tanggal sama
                    $sheet->mergeCells("A{$start}:A{$end}");
                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Merge kolom L (TTL PKJ) — semua baris punya jumlah pekerja sama
                    $sheet->mergeCells("K{$start}:K{$end}");
                    $sheet->getStyle("K{$start}:K{$end}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->freezePane('A3');
            },
        ];
    }
}