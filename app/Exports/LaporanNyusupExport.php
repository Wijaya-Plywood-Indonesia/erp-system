<?php

namespace App\Exports;

use App\Models\ProduksiNyusup;
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

class LaporanNyusupExport implements WithMultipleSheets
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
            new LaporanNyusupPotonganGajiSheet($this->data, $this->tanggal),
            new LaporanNyusupProduksiSheet($this->data),
            new LaporanNyusupTargetSheet($this->data),
        ];
    }
}

class LaporanNyusupPotonganGajiSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
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

    public function collection()
    {
        $blok = $this->data['produksi'] ?? [];

        $ids = collect($blok)->pluck('id_produksi')->filter()->all();
        $kendalaMap = ProduksiNyusup::whereIn('id', $ids)->pluck('kendala', 'id');

        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        foreach ($blok as $prod) {
            $pegawai = $prod['pegawai'] ?? [];
            $N = count($pegawai);

            $allRows[] = ['NYUSUP - TANGGAL: ' . ($prod['tanggal'] ?? $this->tanggal)];
            $allRows[] = array_fill(0, 9, '');

            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Jam Aktual', 'Hasil (Pcs)', 'Capaian', 'Kendala'];

            $startRow = count($allRows) + 1;
            $endRow = $startRow + $N - 1;
            $totalRow = $startRow + $N;

            $kendala = trim((string) ($kendalaMap[$prod['id_produksi'] ?? 0] ?? ''));
            if ($kendala === '' || $kendala === '-') {
                $kendala = 'Tidak ada kendala';
            }

            if ($N > 1) {
                $this->mergeRanges[] = "I{$startRow}:I{$endRow}";
            }

            foreach ($pegawai as $idx => $p) {
                $ketParts = [];
                if (($p['jam_masuk'] ?? '-') !== '-') {
                    $ketParts[] = 'Masuk: ' . $p['jam_masuk'] . (($p['jam_pulang'] ?? '-') !== '-' ? ' - ' . $p['jam_pulang'] : '');
                }
                if (!empty($p['ijin']) && $p['ijin'] !== '-') {
                    $ketParts[] = 'Ijin: ' . $p['ijin'];
                }
                if (!empty($p['keterangan']) && $p['keterangan'] !== '-') {
                    $ketParts[] = $p['keterangan'];
                }
                $ketString = !empty($ketParts) ? implode(' | ', $ketParts) : '-';

                $capaianCell = ($p['capaian_global'] ?? null) !== null
                    ? ((float) $p['capaian_global']) / 100
                    : 'Target belum ada';

                $allRows[] = [
                    $p['id'] ?? '-',
                    $p['nama'] ?? 'TANPA NAMA',
                    (int) ($p['pot_target'] ?? 0),
                    $ketString,
                    '',
                    $p['jam_aktual_bersih'] ?? '-',
                    (int) round($p['hasil_total'] ?? 0),
                    $capaianCell,
                    $idx === 0 ? $kendala : '',
                ];
            }

            $allRows[] = [
                'TOTAL',
                $N . ' pekerja',
                $N > 0 ? "=SUM(C{$startRow}:C{$endRow})" : 0,
                '',
                '',
                '',
                $N > 0 ? "=SUM(G{$startRow}:G{$endRow})" : 0,
                '',
                '',
            ];

            $allRows[] = array_fill(0, 9, '');
            $allRows[] = array_fill(0, 9, '');

            $this->tableRanges[] = [
                'header' => $headerRow,
                'start' => $startRow,
                'end' => $endRow,
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

                $widths = ['A' => 10, 'B' => 25, 'C' => 15, 'D' => 34, 'E' => 5, 'F' => 12, 'G' => 13, 'H' => 16, 'I' => 40];
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

                    $sheet->getStyle("A{$headerRow}:I{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
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

                    $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
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
                        $sheet->getStyle("F{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("I{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("G{$startRow}:G{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("H{$startRow}:H{$endRow}")->getNumberFormat()->setFormatCode('0.0%');
                    }
                }

                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("I1:I{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }
}

class LaporanNyusupProduksiSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
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
                $row['d_p'] = $d['p'];
                $row['d_l'] = $d['l'];
                $row['d_t'] = $d['t'];
                $row['d_jenis'] = $d['jenis'];
                $row['d_byk'] = $d['byk'];
                $row['d_m3'] = '';
            } else {
                $row['d_tgl'] = $row['d_p'] = $row['d_l'] = $row['d_t'] = $row['d_jenis'] = $row['d_byk'] = $row['d_m3'] = '';
            }

            $row['spacer'] = '';

            // Right side (Summary)
            if ($i < count($summaryProduksi)) {
                $s = $summaryProduksi[$i];
                $row['s_tgl'] = $s['tanggal'];
                $row['s_ttl_pkj'] = $s['ttl_pkj'];
                $row['s_harga'] = '';
                $row['s_total_produksi'] = '';
                $row['s_ongkos_m3'] = '';
                $row['s_ongkos_lb'] = '';
            } else {
                $row['s_tgl'] = $row['s_ttl_pkj'] = $row['s_harga'] = $row['s_total_produksi'] = $row['s_ongkos_m3'] = $row['s_ongkos_lb'] = '';
            }

            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'p', 'l', 't', 'jenis', 'byk', 'm3',
            '',
            'Tanggal', 'TTL PKJ', 'HARGA', 'TOTAL PRODUKSI (M3)', 'ONGKOS PER M3', 'ONGKOS PER LB',
        ];
    }

    public function title(): string
    {
        return 'Laporan Produksi';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('I1:O' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('H1:H' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');
                $sheet->getStyle('N1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

                $widths = ['A' => 15, 'B' => 8, 'C' => 8, 'D' => 8, 'E' => 15, 'F' => 8, 'G' => 10, 'H' => 3, 'I' => 15, 'J' => 10, 'K' => 15, 'L' => 20, 'M' => 18, 'N' => 18, 'O' => 18];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}

class LaporanNyusupTargetSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
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
            foreach (($prod['pegawai'] ?? []) as $p) {
                foreach (($p['items'] ?? []) as $u) {
                    $adaTarget = (bool) ($u['has_target'] ?? false);

                    $rows->push([
                        $prod['tanggal'] ?? '',
                        $p['id'] ?? '-',
                        $p['nama'] ?? '-',
                        $u['ukuran'] ?? '-',
                        $u['jenis_kayu'] ?? '-',
                        $u['grade'] ?? '-',
                        (int) round($u['hasil'] ?? 0),
                        $adaTarget ? round((float) $u['target'], 1) : '-',
                        $adaTarget ? round((float) ($u['target_normal'] ?? 0), 1) : '-',
                        $adaTarget ? round((float) $u['selisih'], 1) : '-',
                        $adaTarget ? ((float) ($u['capaian_persen'] ?? 0)) / 100 : 'Target ?',
                    ]);
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'ID', 'Nama', 'Ukuran', 'Jenis Kayu', 'Grade',
            'Hasil', 'Target (Adjusted)', 'Target Normal', 'Selisih', 'Capaian',
        ];
    }

    public function title(): string
    {
        return 'Target per Barang';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, $sheet->getHighestRow());

                $sheet->getStyle('A1:K' . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
                $sheet->getStyle('K2:K' . $lastRow)->getNumberFormat()->setFormatCode('0.0%');
                $sheet->getStyle('G2:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $widths = ['A' => 12, 'B' => 10, 'C' => 24, 'D' => 20, 'E' => 14, 'F' => 18, 'G' => 10, 'H' => 17, 'I' => 14, 'J' => 12, 'K' => 12];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}