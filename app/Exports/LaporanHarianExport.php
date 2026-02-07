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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class LaporanHarianExport implements
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
     * =========================
     * DATA ROW EXCEL
     * =========================
     */
    public function array(): array
    {
        $result = [];

        foreach ($this->data as $row) {

            $jamMasuk = $this->convertTimeToExcel($row['masuk'] ?? null);
            $jamPulang = $this->convertTimeToExcel($row['pulang'] ?? null);
            $jamKerja = $this->calculateWorkingHours(
                $row['masuk'] ?? null,
                $row['pulang'] ?? null
            );

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                $jamMasuk,                 // TIME Excel
                $jamPulang,                // TIME Excel
                $jamKerja,               // 👈 KOLOM BARU
                $row['hasil'] ?? '-',
                $row['ijin'] ?? '',
                (isset($row['potongan_targ']) && $row['potongan_targ'] > 0)
                    ? $row['potongan_targ']
                    : '',
                $row['keterangan'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * =========================
     * HELPER: CONVERT JAM KE TIME EXCEL
     * =========================
     */
    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-') {
            return null;
        }

        // Normalisasi HH:mm
        [$hour, $minute] = explode(':', substr($time, 0, 5));

        // Excel time = jam / 24
        return ($hour / 24) + ($minute / 1440);
    }
    /**
     * =========================
     * HELPER: HITUNG JAM KERJA
     * =========================
     */
    protected function calculateWorkingHours(?string $masuk, ?string $pulang): ?float
    {
        if (empty($masuk) || empty($pulang) || $masuk === '-' || $pulang === '-') {
            return null;
        }

        [$inH, $inM] = explode(':', substr($masuk, 0, 5));
        [$outH, $outM] = explode(':', substr($pulang, 0, 5));

        $masukMenit = ($inH * 60) + $inM;
        $pulangMenit = ($outH * 60) + $outM;

        // Shift malam
        if ($pulangMenit <= $masukMenit) {
            $pulangMenit += 1440; // +1 hari
        }

        $totalMenit = $pulangMenit - $masukMenit;

        // Excel durasi jadi angka aja
        return round($totalMenit / 60, 2);
    }
    /**
     * =========================
     * HEADINGS
     * =========================
     */
    public function headings(): array
    {
        return [
            'Kodep',
            'Nama Pegawai',
            'Masuk',
            'Pulang',
            'Jam Kerja',        // 👈 BARU
            'Hasil / Divisi',
            'Ijin',
            'Potongan Target',
            'Keterangan',
        ];
    }

    /**
     * =========================
     * STYLING
     * =========================
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // HEADER
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2D3748'],
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

        // DATA BORDER
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

        //JAM KERJA
        $sheet->getStyle("E2:E{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('0'); // atau '0' kalau mau bulat

        $sheet->getStyle("E2:E{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // FORMAT JAM (PENTING)
        $sheet->getStyle("C2:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        // FORMAT POTONGAN
        $sheet->getStyle("G2:G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // ALIGNMENT
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        // KETERANGAN → WRAP TEXT
        $sheet->getStyle("H2:H{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        // LOGIKA WARNA
        for ($i = 2; $i <= $lastRow; $i++) {
            $hasil = (string) $sheet->getCell("E{$i}")->getValue();

            if (str_contains($hasil, 'LAIN-LAIN')) {
                $sheet->getStyle("E{$i}")->getFont()->applyFromArray([
                    'bold' => true,
                    'color' => ['rgb' => 'B45309'],
                ]);
            }

            if ($hasil === '-') {
                $sheet->getStyle("A{$i}:H{$i}")
                    ->getFont()
                    ->getColor()
                    ->setRGB('A0AEC0');
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:H{$lastRow}");

        return [];
    }

    /**
     * =========================
     * COLUMN WIDTH
     * =========================
     */
    public function columnWidths(): array
    {
        return [
            'A' => 12, // Tanggal
            'B' => 24, // Nama / Unit
            'C' => 9,  // Jam Masuk
            'D' => 9,  // Jam Pulang
            'E' => 32, // Proses / Keterangan utama
            'F' => 8,  // Jam Kerja (angka)
            'G' => 12, // Shift
            'H' => 28, // Catatan
        ];
    }

    /**
     * =========================
     * SHEET TITLE
     * =========================
     */
    public function title(): string
    {
        return 'LAPORAN_HARIAN';
    }
}
