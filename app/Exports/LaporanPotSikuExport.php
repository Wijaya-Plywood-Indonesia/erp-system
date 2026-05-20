<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPotSikuExport implements WithMultipleSheets
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
            new LaporanPotSikuDetailSheet($this->data, $this->tanggal),
            new LaporanPotSikuRekapSheet($this->data, $this->tanggal),
        ];
    }
}

class LaporanPotSikuDetailSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $data;
    protected $tanggal;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;
    }

    public function title(): string
    {
        return 'Detail Per Pekerja';
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $produksi) {
            foreach ($produksi['pekerja_list'] as $pekerja) {
                foreach ($pekerja['detail_barang'] as $idx => $detail) {
                    $row = [
                        'd_tgl' => $produksi['tanggal'],
                        'd_pegawai' => $pekerja['nama_pegawai'],
                        'd_jenis' => $detail['jenis_kayu'],
                        'd_ukuran' => $detail['ukuran'],
                        'd_kw' => $detail['kw'],
                        'd_hasil' => $detail['tinggi'],
                        'spacer' => '',
                        's_tgl' => $idx === 0 ? $produksi['tanggal'] : '',
                        's_pegawai' => $idx === 0 ? $pekerja['nama_pegawai'] : '',
                        's_total_hasil' => $idx === 0 ? $pekerja['hasil'] : '',
                        's_target' => $idx === 0 ? $pekerja['target'] : '',
                        's_selisih' => $idx === 0 ? $pekerja['selisih'] : '',
                        's_potongan' => $idx === 0 ? $pekerja['potongan_target'] : '',
                        's_ket' => $idx === 0 ? $pekerja['ket'] : '',
                    ];
                    $rows->push($row);
                }
                $rows->push(array_fill(0, 14, ''));
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Pegawai', 'Jenis Kayu', 'Ukuran', 'KW', 'Hasil (cm)',
            '',
            'Tanggal', 'Pegawai', 'Total Hasil', 'Target', 'Selisih', 'Potongan (Rp)', 'Keterangan'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle("A1:F" . $lastRow)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                $sheet->getStyle("H1:N" . $lastRow)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                $sheet->getStyle("G1:G" . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('000000');

                foreach (range('A', 'N') as $col) {
                    if ($col == 'G') $sheet->getColumnDimension($col)->setWidth(3);
                    else $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}

class LaporanPotSikuRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $data;
    protected $tanggal;
    protected $hasAf = false;

    public function __construct($data, $tanggal)
    {
        $this->data = $data;
        $this->tanggal = $tanggal;

        // Cek apakah ada data 'af' dalam daftar
        foreach ($this->data as $produksi) {
            foreach ($produksi['pekerja_list'] as $pekerja) {
                foreach ($pekerja['detail_barang'] as $detail) {
                    if (isset($detail['kw']) && strtolower((string)$detail['kw']) === 'af') {
                        $this->hasAf = true;
                        break 3;
                    }
                }
            }
        }
    }

    public function title(): string
    {
        return 'Rekap Ukuran';
    }

    public function collection()
    {
        $rows = collect();
        $details = collect();

        // Flatten all details from all production records and workers
        foreach ($this->data as $produksi) {
            foreach ($produksi['pekerja_list'] as $pekerja) {
                foreach ($pekerja['detail_barang'] as $detail) {
                    $details->push($detail);
                }
            }
        }

        // Group by P, L, T and Jenis Kayu
        $grouped = $details->groupBy(function ($item) {
            return $item['p'] . '|' . $item['l'] . '|' . $item['t'] . '|' . $item['jenis_kayu'];
        });

        foreach ($grouped as $key => $items) {
            [$p, $l, $t, $jenis] = explode('|', $key);
            
            $row = [
                'p' => $p,
                'l' => $l,
                't' => $t,
                'jenis' => $jenis,
            ];

            // KW categories
            $kws = ['1', '2', '3', '4'];
            foreach ($kws as $kw) {
                $row['kw_' . $kw] = $items->where('kw', $kw)->sum('tinggi') ?: '';
            }

            // Tambahkan kolom AF jika terdeteksi
            if ($this->hasAf) {
                $row['af'] = $items->filter(fn($i) => strtolower((string)($i['kw'] ?? '')) === 'af')->sum('tinggi') ?: '';
            }

            $row['total'] = $items->sum('tinggi');

            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $headers = [
            'Panjang (P)', 'Lebar (L)', 'Tebal (T)', 'Jenis Kayu',
            'KW 1', 'KW 2', 'KW 3', 'KW 4',
        ];

        if ($this->hasAf) {
            $headers[] = 'AF';
        }

        $headers[] = 'Total (cm)';

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));

                $sheet->getStyle("A1:{$lastCol}" . $lastRow)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                for ($i = 1; $i <= count($this->headings()); $i++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
