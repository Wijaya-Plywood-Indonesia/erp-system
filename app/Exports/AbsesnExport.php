<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AbsenExport implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Mengolah Data Row (7 Kolom)
     */
    public function array(): array
    {
        $result = [];

        foreach ($this->data as $row) {
            // Konversi jam ke format Excel agar terbaca sebagai TIME
            $jamMasuk = $this->convertTimeToExcel($row['masuk'] ?? null);
            $jamPulang = $this->convertTimeToExcel($row['pulang'] ?? null);

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                $jamMasuk,
                $jamPulang,
                $row['hasil'] ?? '-',
                $row['ijin'] ?? '',
                $row['keterangan'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Helper: Konversi string jam ke angka desimal Excel
     */
    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        try {
            // Mengambil 5 karakter pertama (HH:mm)
            [$hour, $minute] = explode(':', substr($time, 0, 5));
            return ($hour / 24) + ($minute / 1440);
        } catch (\Exception $e) {
            return null;
        }
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
            'Keterangan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // STYLE HEADER (A1 sampai G1)
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2D3748'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // STYLE DATA BORDER
        $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // FORMAT JAM (Kolom C & D)
        $sheet->getStyle("C2:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        // ALIGNMENT CUSTOM
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // WRAP TEXT KETERANGAN (Kolom G)
        $sheet->getStyle("G2:G{$lastRow}")
            ->getAlignment()
            ->setWrapText(true);

        // LOGIKA WARNA UNTUK PEGAWAI LIBUR (Hasil = '-')
        for ($i = 2; $i <= $lastRow; $i++) {
            $hasil = (string) $sheet->getCell("E{$i}")->getValue();

            if ($hasil === '-' || empty($hasil)) {
                $sheet->getStyle("A{$i}:G{$i}")
                    ->getFont()
                    ->getColor()
                    ->setRGB('A0AEC0'); // Abu-abu untuk yang libur
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:G{$lastRow}");

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, // Kodep
            'B' => 30, // Nama
            'C' => 12, // Masuk
            'D' => 12, // Pulang
            'E' => 45, // Hasil / Divisi
            'F' => 10, // Ijin
            'G' => 40, // Keterangan
        ];
    }

    public function title(): string
    {
        return 'DATA_ABSENSI';
    }
}
