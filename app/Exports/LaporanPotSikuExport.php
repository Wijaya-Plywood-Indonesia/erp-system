<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LaporanPotSikuExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $data;
    protected $mergeRows = [];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $exportData = collect();
        $currentRow = 2; // Data dimulai dari baris ke-2 (setelah header)

        foreach ($this->data as $laporan) {
            foreach ($laporan['pekerja_list'] as $pekerja) {
                $rowCount = count($pekerja['detail_barang']);
                
                // Jika pekerja memiliki detail, tandai baris untuk di-merge
                if ($rowCount > 1) {
                    $this->mergeRows[] = [
                        'start' => $currentRow,
                        'end' => $currentRow + $rowCount - 1
                    ];
                }

                foreach ($pekerja['detail_barang'] as $detail) {
                    $exportData->push([
                        'tanggal' => $laporan['tanggal'],
                        'kode_pegawai' => $pekerja['kode_pegawai'],
                        'nama_pegawai' => $pekerja['nama_pegawai'],
                        'jam_masuk' => $pekerja['jam_masuk'],
                        'jam_pulang' => $pekerja['jam_pulang'],
                        'jenis_kayu' => $detail['jenis_kayu'],
                        'ukuran' => $detail['ukuran'],
                        'kw' => $detail['kw'],
                        'tinggi' => $detail['tinggi'],
                        'hasil_total' => $pekerja['hasil'],
                        'potongan' => $pekerja['potongan_target'],
                        'keterangan' => $pekerja['ket'],
                    ]);
                }
                $currentRow += $rowCount;
            }
        }
        return $exportData;
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Kode', 'Nama Pegawai', 'Masuk', 'Pulang', 
            'Jenis Kayu', 'Ukuran', 'KW', 'Hasil (Tinggi)', 
            'Total Hasil', 'Potongan Target', 'Keterangan'
        ];
    }

    public function map($row): array
    {
        return [
            $row['tanggal'],
            $row['kode_pegawai'],
            $row['nama_pegawai'],
            $row['jam_masuk'],
            $row['jam_pulang'],
            $row['jenis_kayu'],
            $row['ukuran'],
            $row['kw'],
            $row['tinggi'],
            $row['hasil_total'],
            $row['potongan'],
            $row['keterangan'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Styling Header
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Eksekusi Merging Cells untuk kolom yang datanya berulang (A-E dan J-L)
        foreach ($this->mergeRows as $range) {
            foreach (['A', 'B', 'C', 'D', 'E', 'J', 'K', 'L'] as $col) {
                $sheet->mergeCells("{$col}{$range['start']}:{$col}{$range['end']}");
                $sheet->getStyle("{$col}{$range['start']}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
        }

        // Format angka/rupiah untuk kolom Potongan (K)
        $sheet->getStyle('K2:K' . $sheet->getHighestRow())
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        return [];
    }
}