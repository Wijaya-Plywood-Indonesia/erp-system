<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Laporan Pot Siku.
 *
 * PENTING: $dataSiku di sini adalah HASIL dari PotSikuDataMap::make(),
 * yaitu array of produksi, masing-masing berbentuk:
 * [
 *   'tanggal'  => '13/08/2026',
 *   'kendala'  => '-',
 *   'validasi' => [...] | null,
 *   'pekerja'  => [
 *       [
 *           'kode_pegawai' => '1225',
 *           'nama' => 'SARMI',
 *           'jam_masuk' => '06:00',
 *           'jam_pulang' => '16:00',
 *           'jam_aktual_bersih' => 9.0,
 *           'ijin' => '-',
 *           'keterangan' => '-',
 *           'total_hasil' => 180,
 *           'capaian_global_persen' => 236.1,
 *           'potongan' => 0,
 *           'items' => [
 *               [
 *                   'ukuran' => '70x55x0.5',
 *                   'jenis_kayu' => 'SENGON',
 *                   'kw' => 'AF',
 *                   'hasil' => 118,
 *                   'target' => 80,
 *                   'selisih' => 38,
 *                   'capaian_persen' => 147.5,
 *                   'has_target' => true,
 *                   'no_palet_list' => '1',
 *               ],
 *               ...
 *           ],
 *       ],
 *       ...
 *   ],
 * ]
 */
class LaporanPotSikuExport implements WithMultipleSheets
{
    protected array $data;
    protected $tanggal;

    public function __construct(array $dataSiku, $tanggal = null)
    {
        $this->data = $dataSiku;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotSikuDetailSheet($this->data),
            new LaporanPotSikuRekapSheet($this->data),
        ];
    }
}

/**
 * Sheet 1: Detail per pekerja, tiap ukuran yang dikerjakan jadi 1 baris.
 */
class LaporanPotSikuDetailSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected Collection $data;

    public function __construct(array $dataSiku)
    {
        $this->data = collect($dataSiku);
    }

    public function title(): string
    {
        return 'Detail Per Pekerja';
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode',
            'Nama Pegawai',
            'Ukuran',
            'Jenis Kayu',
            'KW',
            'No. Palet',
            'Hasil (cm)',
            'Target (cm)',
            'Capaian (%)',
            'Masuk',
            'Pulang',
            'Jam Aktual (jam)',
            'Ijin',
            'Total Hasil',
            'Capaian Global (%)',
            'Potongan (Rp)',
            'Keterangan',
        ];
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $produksi) {
            $tanggal = $produksi['tanggal'] ?? '-';
            $pekerjaList = $produksi['pekerja'] ?? [];

            foreach ($pekerjaList as $pekerja) {
                $items = $pekerja['items'] ?? [];

                if (empty($items)) {
                    $rows->push([
                        $tanggal,
                        $pekerja['kode_pegawai'] ?? '-',
                        $pekerja['nama'] ?? '-',
                        '-',
                        '-',
                        '-',
                        '-',
                        0,
                        '-',
                        '-',
                        $pekerja['jam_masuk'] ?? '-',
                        $pekerja['jam_pulang'] ?? '-',
                        $pekerja['jam_aktual_bersih'] ?? '-',
                        $pekerja['ijin'] ?? '-',
                        $pekerja['total_hasil'] ?? 0,
                        $pekerja['capaian_global_persen'] ?? 0,
                        $pekerja['potongan'] ?? 0,
                        $pekerja['keterangan'] ?? '-',
                    ]);
                    continue;
                }

                foreach ($items as $idx => $item) {
                    $rows->push([
                        $idx === 0 ? $tanggal : '',
                        $idx === 0 ? ($pekerja['kode_pegawai'] ?? '-') : '',
                        $idx === 0 ? ($pekerja['nama'] ?? '-') : '',
                        $item['ukuran'] ?? '-',
                        $item['jenis_kayu'] ?? '-',
                        $item['kw'] ?? '-',
                        $item['no_palet_list'] ?? '-',
                        $item['hasil'] ?? 0,
                        ($item['has_target'] ?? false) ? round($item['target'] ?? 0) : '-',
                        ($item['has_target'] ?? false) ? round($item['capaian_persen'] ?? 0, 1) : 'Target ?',
                        $idx === 0 ? ($pekerja['jam_masuk'] ?? '-') : '',
                        $idx === 0 ? ($pekerja['jam_pulang'] ?? '-') : '',
                        $idx === 0 ? ($pekerja['jam_aktual_bersih'] ?? '-') : '',
                        $idx === 0 ? ($pekerja['ijin'] ?? '-') : '',
                        $idx === 0 ? ($pekerja['total_hasil'] ?? 0) : '',
                        $idx === 0 ? ($pekerja['capaian_global_persen'] ?? 0) : '',
                        $idx === 0 ? ($pekerja['potongan'] ?? 0) : '',
                        $idx === 0 ? ($pekerja['keterangan'] ?? '-') : '',
                    ]);
                }
            }

            // Baris kosong pemisah antar produksi (kalau lebih dari 1 tanggal/produksi)
            $rows->push(array_fill(0, 18, ''));
        }

        return $rows;
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

                $sheet->getStyle("A1:R{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("H1:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("O1:Q{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                foreach (range('A', 'R') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}

/**
 * Sheet 2: Rekap total hasil per ukuran + jenis kayu + KW, digabung dari
 * SEMUA pekerja & SEMUA produksi pada tanggal terpilih.
 */
class LaporanPotSikuRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected Collection $data;

    public function __construct(array $dataSiku)
    {
        $this->data = collect($dataSiku);
    }

    public function title(): string
    {
        return 'Rekap Ukuran';
    }

    public function headings(): array
    {
        return [
            'Panjang (P)',
            'Lebar (L)',
            'Tebal (T)',
            'Jenis Kayu',
            'KW 1',
            'KW 2',
            'KW 3',
            'KW 4',
            'AF',
            'Total (cm)',
        ];
    }

    public function collection()
    {
        $flatItems = collect();

        // 1. Kumpulkan semua barang dari setiap pekerja & produksi
        foreach ($this->data as $produksi) {
            foreach ($produksi['pekerja'] ?? [] as $pekerja) {
                foreach ($pekerja['items'] ?? [] as $item) {
                    $flatItems->push([
                        'ukuran' => $item['ukuran'] ?? '',
                        'jenis_kayu' => $item['jenis_kayu'] ?? '-',
                        'kw' => strtoupper(trim($item['kw'] ?? '')),
                        'hasil' => (float)($item['hasil'] ?? 0),
                    ]);
                }
            }
        }

        // 2. Grouping berdasarkan Kombinasi (Ukuran + Jenis Kayu)
        $grouped = $flatItems->groupBy(function ($item) {
            return trim($item['ukuran']) . '|' . trim($item['jenis_kayu']);
        });

        $rows = collect();

        foreach ($grouped as $key => $items) {
            $first = $items->first();
            [$p, $l, $t] = $this->parseUkuran($first['ukuran']);

            // Formalisasi penulisan Jenis Kayu (misal: "sengon" -> "Sengon")
            $jenisKayu = ucfirst(strtolower($first['jenis_kayu']));

            // Mapping Pivot per KW
            $kw1 = $items->filter(fn($i) => in_array($i['kw'], ['KW 1', 'KW1', '1']))->sum('hasil');
            $kw2 = $items->filter(fn($i) => in_array($i['kw'], ['KW 2', 'KW2', '2']))->sum('hasil');
            $kw3 = $items->filter(fn($i) => in_array($i['kw'], ['KW 3', 'KW3', '3']))->sum('hasil');
            $kw4 = $items->filter(fn($i) => in_array($i['kw'], ['KW 4', 'KW4', '4']))->sum('hasil');
            $af  = $items->filter(fn($i) => in_array($i['kw'], ['AF', 'KW AF']))->sum('hasil');

            $totalCm = $kw1 + $kw2 + $kw3 + $kw4 + $af;

            // Jika ada KW yang tidak cocok dengan kriteria di atas, tambahkan ke total
            if ($totalCm === 0.0) {
                $totalCm = $items->sum('hasil');
            }

            $rows->push([
                'p' => $p,
                'l' => $l,
                't' => $t,
                'jenis_kayu' => $jenisKayu,
                'kw_1' => $kw1 > 0 ? $kw1 : '',
                'kw_2' => $kw2 > 0 ? $kw2 : '',
                'kw_3' => $kw3 > 0 ? $kw3 : '',
                'kw_4' => $kw4 > 0 ? $kw4 : '',
                'af'   => $af > 0 ? $af : '',
                'total' => $totalCm,
            ]);
        }

        return $rows;
    }

    /**
     * Helper untuk mengekstraksi P, L, T dari string ukuran (misal: "70x55x0.5" atau "70 x 55 x 0.5")
     */
    private function parseUkuran(string $ukuran): array
    {
        preg_match_all('/(?:\d+(?:[\.,]\d+)?)/', $ukuran, $matches);
        $numbers = $matches[0] ?? [];

        $p = isset($numbers[0]) ? str_replace('.', ',', $numbers[0]) : '-';
        $l = isset($numbers[1]) ? str_replace('.', ',', $numbers[1]) : '-';
        $t = isset($numbers[2]) ? str_replace('.', ',', $numbers[2]) : '-';

        return [$p, $l, $t];
    }

    public function styles(Worksheet $sheet)
    {
        // Styling Font Header Bold & Center
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                if ($lastRow < 2) return;

                // Border Tipis untuk Seluruh Sel
                $sheet->getStyle("A1:J{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // Auto Fit Lebar Kolom
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}
