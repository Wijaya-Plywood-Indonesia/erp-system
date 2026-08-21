<?php

namespace App\Exports;

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

/**
 * Export Laporan Potong Jelek.
 *
 * PENTING: $dataProduksi di sini adalah HASIL dari PotJelekDataMap::make(),
 * yaitu array of produksi, masing-masing berbentuk:
 * [
 *   'tanggal' => '04/08/2026',
 *   'kendala' => '-',
 *   'pekerja' => [
 *       [
 *           'kode_pegawai' => '1217',
 *           'nama' => "JUMA'YA",
 *           'jam_masuk' => '06:00',
 *           'jam_pulang' => '16:00',
 *           'jam_aktual_bersih' => 9.0,
 *           'ijin' => '-',
 *           'keterangan' => '-',
 *           'total_hasil' => 690,
 *           'capaian_global_persen' => 102.6,
 *           'potongan' => 0,
 *           'items' => [
 *               [
 *                   'ukuran' => '48mm x 48mm x 2.2mm',
 *                   'jenis_kayu' => 'SENGON',
 *                   'kw' => 'AF',
 *                   'hasil' => 225,
 *                   'target' => 0,
 *                   'capaian_persen' => null,
 *                   'has_target' => false,
 *                   'no_palet_list' => '1',
 *               ],
 *               ...
 *           ],
 *       ],
 *       ...
 *   ],
 * ]
 */
class LaporanPotJelekExport implements WithMultipleSheets
{
    protected array $data;
    protected $tanggal;

    public function __construct(array $dataProduksi, $tanggal = null)
    {
        $this->data = $dataProduksi;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotJelekDetailSheet($this->data),
            new LaporanPotJelekRekapSheet($this->data),
        ];
    }
}

/**
 * Sheet 1: Detail per pekerja, tiap ukuran yang dikerjakan jadi 1 baris.
 */
class LaporanPotJelekDetailSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected Collection $data;

    public function __construct(array $dataProduksi)
    {
        $this->data = collect($dataProduksi);
    }

    public function title(): string
    {
        return 'Detail Per Pekerja';
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Kode', 'Nama Pegawai', 'Ukuran', 'Jenis Kayu', 'KW',
            'No. Palet', 'Hasil', 'Target', 'Capaian (%)',
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
class LaporanPotJelekRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected Collection $data;

    public function __construct(array $dataProduksi)
    {
        $this->data = collect($dataProduksi);
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
        return ['Ukuran', 'Jenis Kayu', 'KW', 'Total Hasil', 'Jumlah Entri'];
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