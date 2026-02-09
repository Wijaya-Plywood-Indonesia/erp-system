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
            // LOGIKA: Ambil murni Nama Divisinya saja (Membersihkan hasil produksi)
            $divisiRaw = is_array($row['hasil']) ? $row['hasil'] : explode(', ', $row['hasil']);

            $cleanDivisi = collect($divisiRaw)->map(function ($item) {
                // Mengambil kata pertama atau membersihkan teks sebelum tanda ':' atau '('
                $name = trim(explode(':', explode('(', $item)[0])[0]);
                return strtoupper($name);
            })->unique()->implode(', ');

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                $this->convertTimeToExcel($row['masuk']),
                $this->convertTimeToExcel($row['pulang']),
                $cleanDivisi ?: '-', // Hanya nama divisi, misal: "REPAIR, HOT PRESS"
                $row['ijin'] ?? '',
                $row['keterangan'] ?? '',
            ];
        }
        return $result;
    }

    protected function convertTimeToExcel($time)
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) return null;
        [$h, $m] = explode(':', substr($time, 0, 5));
        return ($h / 24) + ($m / 1440);
    }

    public function headings(): array
    {
        return ['Kodep', 'Nama Pegawai', 'Masuk', 'Pulang', 'Divisi', 'Ijin', 'Keterangan'];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // Header Style
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D3748']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Standarisasi Font Hitam dan Border
        $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
            'font' => ['color' => ['rgb' => '000000']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D3D3D3']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // SIMULASI BADGE: Memberikan warna background pada Kolom E (Divisi)
        for ($i = 2; $i <= $lastRow; $i++) {
            $divisiText = $sheet->getCell("E{$i}")->getValue();

            if ($divisiText !== '-' && !empty($divisiText)) {
                $sheet->getStyle("E{$i}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']], // Teks Biru Gelap
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'DBEAFE'], // Background Biru Muda (Simulasi Badge)
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
        }

        $sheet->getStyle("C2:D{$lastRow}")->getNumberFormat()->setFormatCode('hh:mm:ss');
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 30, 'C' => 12, 'D' => 12, 'E' => 35, 'F' => 10, 'G' => 40];
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI';
    }
}
