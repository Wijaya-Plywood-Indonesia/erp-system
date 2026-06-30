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
     * Mengolah Baris Data Excel
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

            // --- LOGIKA PEMBERSIHAN LABEL HASIL ---
            $hasilRaw = trim($row['hasil'] ?? '-');
            $labelBersih = $hasilRaw;

            if (str_contains($hasilRaw, ':')) {
                $parts = explode(':', $hasilRaw);
                $labelDepan = trim($parts[0]);

                if (strtoupper($labelDepan) !== 'LAIN-LAIN') {
                    $labelBersih = $labelDepan;
                }
            } else {
                $keywords = ['REPAIR', 'SANDING', 'DRYER', 'ROTARY', 'STIK', 'JOINT', 'HOT PRESS', 'NYUSUP', 'PILIH PLYWOOD'];
                foreach ($keywords as $key) {
                    if (str_contains(strtoupper($hasilRaw), $key)) {
                        $labelBersih = $key;
                        break;
                    }
                }
            }

            $result[] = [
                $row['kodep'] ?? '-',
                $row['nama'] ?? '-',
                $jamMasuk,       // Column C (Formatted as Time)
                $jamPulang,      // Column D (Formatted as Time)
                $jamKerja,       // Column E (Number)
                $labelBersih,    // Column F
                $row['ijin'] ?? '',
                (isset($row['potongan_targ']) && $row['potongan_targ'] > 0) ? $row['potongan_targ'] : '',
                $row['keterangan'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Helper: Konversi jam ke desimal Excel (Stabil untuk semua Regional Setting)
     */
    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        try {
            $t = Carbon::parse($time);
            return ($t->hour / 24) + ($t->minute / 1440) + ($t->second / 86400);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Hitung Durasi Jam Kerja (Mendukung Shift Malam)
     */
    protected function calculateWorkingHours(?string $masuk, ?string $pulang): ?float
    {
        if (empty($masuk) || empty($pulang) || $masuk === '-' || $pulang === '-') {
            return null;
        }

        try {
            $start = Carbon::parse($masuk);
            $end = Carbon::parse($pulang);

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            return round($start->diffInMinutes($end) / 60, 2);
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
            'Jam Kerja',
            'Hasil / Divisi',
            'Ijin',
            'Potongan Target',
            'Keterangan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;

        // Header Style (A1:I1)
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D3748']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);

        // General Data Border
        $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3']
                ]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // FORMAT TIME EXCEL (Kolom C & D)
        // Gunakan HH:mm:ss untuk memaksa 24 jam agar tidak terpengaruh setting AM/PM laptop
        $sheet->getStyle("C2:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('HH:mm:ss');

        // FORMAT JAM KERJA (Kolom E)
        $sheet->getStyle("E2:E{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // FORMAT POTONGAN TARGET (Kolom H)
        $sheet->getStyle("H2:H{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // ALIGNMENT CUSTOM
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Wrap Text Keterangan
        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);

        // LOGIKA WARNA KHUSUS
        for ($i = 2; $i <= $lastRow; $i++) {
            $hasilVal = (string) $sheet->getCell("F{$i}")->getValue();

            if (str_contains($hasilVal, 'LAIN-LAIN')) {
                $sheet->getStyle("F{$i}")->getFont()->setBold(true)->getColor()->setRGB('B45309');
            }

            if ($hasilVal === '-' || empty($hasilVal)) {
                $sheet->getStyle("A{$i}:I{$i}")->getFont()->getColor()->setRGB('A0AEC0');
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:I{$lastRow}");

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 30,
            'C' => 12,
            'D' => 12,
            'E' => 12,
            'F' => 45,
            'G' => 10,
            'H' => 18,
            'I' => 35,
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_HARIAN';
    }
}
