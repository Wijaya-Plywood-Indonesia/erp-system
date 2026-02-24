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
                $itemUpper = strtoupper(trim($item));

                // LOGIKA KHUSUS UNTUK LAIN-LAIN (Agar detail muncul di samping label)
                if (str_contains($itemUpper, 'LAIN-LAIN')) {
                    // Membersihkan label agar menyisakan keterangan saja
                    $detail = trim(str_ireplace(['LAIN-LAIN', ':', '-'], '', $item));
                    return !empty($detail) ? "LAIN-LAIN ($detail)" : "LAIN-LAIN";
                }

                // Logika standar untuk divisi lainnya (Pembersihan angka/detail produksi)
                $name = trim(explode(':', explode('(', $item)[0])[0]);
                return strtoupper($name);
            })->unique()->implode(', ');

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',

                // FINGER (Data Mesin)
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
     */
    protected function convertTimeToExcel($time)
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        try {
            $parts = explode(':', $time);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0);

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

        // 1. Style Header
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
        ]);

        // 2. Format Kolom Waktu
        $sheet->getStyle("C2:F{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        // 3. Grid / Border
        $sheet->getStyle("A1:I{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'AAAAAA']
                ]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // 4. Alignment
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Bungkus teks (Wrap Text) untuk kolom Divisi & Keterangan agar detail muncul semua
        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);

        // 5. Warna Kolom Divisi (G)
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
            'A' => 12,
            'B' => 35,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 40, // Diperlebar untuk menampung detail Lain-lain
            'H' => 10,
            'I' => 45
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI_' . date('Y-m-d');
    }
}
