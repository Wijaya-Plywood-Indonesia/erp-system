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

class LaporanHarianExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Memetakan data dari array gabungan ke baris Excel
     */
    public function array(): array
    {
        $result = [];

        foreach ($this->data as $row) {
            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                $row['masuk'] ?? '-',
                $row['pulang'] ?? '-',
                $row['hasil'] ?? '-',
                $row['ijin'] ?? '',
                // Jika potongan 0 atau null, dikosongkan agar rapi di Excel
                (isset($row['potongan_targ']) && $row['potongan_targ'] > 0) ? $row['potongan_targ'] : '',
                $row['keterangan'] ?? '',
            ];
        }

        return $result;
    }

    public function headings(): array
    {
        return [
            'Kodep',
            'Nama Pegawai',
            'Masuk',
            'Pulang',
            'Hasil / Divisi',
            'Ijin',
            'Potongan Target',
            'Keterangan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // 1. Style Header (Baris 1)
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2D3748'], // Warna abu-abu gelap agar modern
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // 2. Style Seluruh Data (Border & Vertical Center)
        $sheet->getStyle("A2:H{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // 3. Logika Warna Khusus untuk Baris "Lain-lain"
        for ($i = 2; $i <= $lastRow; $i++) {
            $hasilValue = $sheet->getCell("E{$i}")->getValue();

            // Jika baris berisi "LAIN-LAIN", berikan warna teks khusus (Amber/Cokelat)
            if (str_contains($hasilValue, 'LAIN-LAIN')) {
                $sheet->getStyle("E{$i}")->getFont()->applyFromArray([
                    'bold' => true,
                    'color' => ['rgb' => 'B45309'], // Warna Amber sesuai badge UI
                ]);
            }

            // Jika baris adalah Pegawai Libur (Hasil adalah '-')
            if ($hasilValue === '-') {
                $sheet->getStyle("A{$i}:H{$i}")->getFont()->getColor()->setRGB('A0AEC0');
            }
        }

        // 4. Alignment Kolom
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Kodep
        $sheet->getStyle("C2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Jam
        $sheet->getStyle("F2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Ijin
        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Potongan

        // 5. Format Number (Mata Uang/Ribuan)
        $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet->freezePane('A2'); // Freeze header saat scroll
        $sheet->setAutoFilter("A1:H{$lastRow}"); // Tambahkan filter di Excel

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Kodep
            'B' => 30,  // Nama
            'C' => 10,  // Masuk
            'D' => 10,  // Pulang
            'E' => 40,  // Hasil / Divisi (Lebih lebar karena ada detail pekerjaan)
            'F' => 10,  // Ijin
            'G' => 18,  // Potongan
            'H' => 35,  // Keterangan
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_HARIAN';
    }
}
