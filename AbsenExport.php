<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AbsenExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $result = [];
        foreach ($this->data as $row) {
            // Pembersihan Nama Divisi
            $divisiRaw = is_array($row['hasil']) ? $row['hasil'] : explode(', ', $row['hasil']);
            $cleanDivisi = collect($divisiRaw)->map(function ($item) {
                $name = trim(explode(':', explode('(', $item)[0])[0]);
                return strtoupper($name);
            })->unique()->implode(', ');

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                // FINGER (Data Mesin) - Kolom C & D
                $this->convertTimeToExcel($row['f_masuk']),
                $this->convertTimeToExcel($row['f_pulang']),
                // MANUAL (Data Input) - Kolom E & F
                $this->convertTimeToExcel($row['masuk']),
                $this->convertTimeToExcel($row['pulang']),
                $cleanDivisi ?: '-',
                $row['ijin'] ?? '',
                $row['keterangan'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Mengonversi string waktu ke format serial Excel agar rumus matematika Excel jalan
     */
    protected function convertTimeToExcel($time)
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) return null;

        try {
            // Ambil HH:mm
            $parts = explode(':', substr($time, 0, 5));
            if (count($parts) < 2) return null;

            $h = (int) $parts[0];
            $m = (int) $parts[1];

            return ($h / 24) + ($m / 1440);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function headings(): array
    {
        return [
            'Kodep',
            'Nama Pegawai',
            'Finger Masuk',
            'Finger Pulang',
            'Manual Masuk',
            'Manual Pulang',
            'Divisi',
            'Ijin',
            'Keterangan'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // Header Style (Dark Gray)
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D3748']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Isi Data Style
        $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
            'font' => ['color' => ['rgb' => '000000']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D3D3D3']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Format Khusus Kolom Waktu (C, D, E, F) agar Excel mengenalnya sebagai jam
        $sheet->getStyle("C2:F{$lastRow}")->getNumberFormat()->setFormatCode('hh:mm');

        // Center alignment untuk Kodep dan Jam
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style Badge untuk Divisi (Kolom G)
        for ($i = 2; $i <= $lastRow; $i++) {
            $divisiText = $sheet->getCell("G{$i}")->getValue();
            if ($divisiText !== '-' && !empty($divisiText)) {
                $sheet->getStyle("G{$i}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, // Kodep
            'B' => 30, // Nama
            'C' => 15, // Finger Masuk
            'D' => 15, // Finger Pulang
            'E' => 15, // Manual Masuk
            'F' => 15, // Manual Pulang
            'G' => 35, // Divisi
            'H' => 10, // Ijin
            'I' => 40  // Keterangan
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI_SINKRON';
    }
}
