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
            'Tanggal', 'Kode', 'Nama Pegawai', 'Ukuran', 'Jenis Kayu', 'KW',
            'No. Palet', 'Hasil (cm)', 'Target (cm)', 'Capaian (%)',
            'Masuk', 'Pulang', 'Jam Aktual (jam)', 'Ijin',
            'Total Hasil', 'Capaian Global (%)', 'Potongan (Rp)', 'Keterangan',
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
                        '-', '-', '-', '-', 0, '-', '-',
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

    public function collection()
    {
        $flatItems = collect();

        foreach ($this->data as $produksi) {
            foreach ($produksi['pekerja'] ?? [] as $pekerja) {
                foreach ($pekerja['items'] ?? [] as $item) {
                    $flatItems->push($item);
                }
            }
        }

        $grouped = $flatItems->groupBy(function ($item) {
            return ($item['ukuran'] ?? '-') . '|' . ($item['jenis_kayu'] ?? '-') . '|' . ($item['kw'] ?? '-');
        });

        $rows = collect();

        foreach ($grouped as $key => $items) {
            [$ukuran, $jenis, $kw] = array_pad(explode('|', $key), 3, '-');

            $rows->push([
                'ukuran' => $ukuran,
                'jenis_kayu' => $jenis,
                'kw' => $kw,
                'total_hasil' => $items->sum('hasil'),
                'jumlah_pekerja' => $items->count(),
            ]);
        }

        return $rows->sortBy('ukuran')->values();
    }

    public function headings(): array
    {
        return ['Ukuran', 'Jenis Kayu', 'KW', 'Total Hasil (cm)', 'Jumlah Entri'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                foreach (range('A', 'E') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}