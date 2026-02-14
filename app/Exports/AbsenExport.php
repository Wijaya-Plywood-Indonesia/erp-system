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

    /**
     * Mengolah data array untuk ditampilkan di baris Excel
     */
    public function array(): array
    {
        $result = [];
        foreach ($this->data as $row) {
            // Pembersihan Nama Divisi agar rapi di Excel
            $divisiRaw = is_array($row['hasil']) ? $row['hasil'] : explode(', ', $row['hasil'] ?? '');
            $cleanDivisi = collect($divisiRaw)->map(function ($item) {
                // Mengambil nama divisi utama sebelum tanda kurung atau titik dua
                $name = trim(explode(':', explode('(', $item)[0])[0]);
                return strtoupper($name);
            })->unique()->implode(', ');

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',

                // FINGER (Data Mesin) - Mendukung hingga Detik
                $this->convertTimeToExcel($row['f_masuk']),
                $this->convertTimeToExcel($row['f_pulang']),

                // MANUAL (Data dari Database DetailAbsensi)
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
     * Konversi string waktu (HH:mm:ss) ke Serial Number Excel.
     * Excel menghitung waktu sebagai pecahan dari 24 jam.
     */
    protected function convertTimeToExcel($time)
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        try {
            // Memecah jam:menit:detik
            $parts = explode(':', $time);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0); // Menangkap detik jika ada

            // Rumus: (Jam/24) + (Menit/1440) + (Detik/86400)
            return ($h / 24) + ($m / 1440) + ($s / 86400);
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
            'Sistem Masuk',
            'Sistem Pulang',
            'Divisi',
            'Ijin',
            'Keterangan'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // 1. Style Header (Latar Gelap, Teks Putih)
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
        ]);

        // 2. Format Kolom Waktu (C, D, E, F) ke hh:mm:ss
        // Ini kunci agar detik muncul di Excel
        $sheet->getStyle("C2:F{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        // 3. Grid / Border untuk seluruh tabel
        $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'AAAAAA']
                ]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // 4. Center Alignment untuk kolom tertentu
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 5. Memberi warna pada kolom Divisi (G) agar lebih terbaca
        for ($i = 2; $i <= $lastRow; $i++) {
            $divisi = $sheet->getCell("G{$i}")->getValue();
            if ($divisi && $divisi !== '-') {
                $sheet->getStyle("G{$i}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '005500']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6FFFA']],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Kodep
            'B' => 35, // Nama Pegawai
            'C' => 18, // Finger Masuk
            'D' => 18, // Finger Pulang
            'E' => 18, // Sistem Masuk
            'F' => 18, // Sistem Pulang
            'G' => 30, // Divisi
            'H' => 10, // Ijin
            'I' => 45  // Keterangan
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI_' . date('Y-m-d');
    }
}
