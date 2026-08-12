<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NewRekapAbsensiExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected int $originalPrecision;

    protected int $originalSerializePrecision;

    public function __construct(
        protected Collection $rekap,
        protected string $tanggal,
    ) {
        // Sama seperti AbsenExport: pakai presisi tinggi supaya konversi
        // jam -> serial Excel tidak kena pembulatan float PHP default.
        $this->originalPrecision = (int) ini_get('precision');
        $this->originalSerializePrecision = (int) ini_get('serialize_precision');

        ini_set('precision', 16);
        ini_set('serialize_precision', -1);
    }

    public function __destruct()
    {
        ini_set('precision', $this->originalPrecision);
        ini_set('serialize_precision', $this->originalSerializePrecision);
    }

    public function collection(): Collection
    {
        return $this->rekap;
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
            'Keterangan',
        ];
    }

    /**
     * @param  array  $row  1 baris hasil NewRekapAbsensiPegawaiService::getRekap()
     */
    public function map($row): array
    {
        return [
            $row['kode_pegawai'] ?? '-',
            $row['nama_pegawai'] ?? '-',
            $this->convertTimeToExcel($row['jam_masuk_finger'] ?? null),
            $this->convertTimeToExcel($row['jam_pulang_finger'] ?? null),
            $this->convertTimeToExcel($row['jam_masuk'] ?? null),
            $this->convertTimeToExcel($row['jam_pulang'] ?? null),
            $this->formatDivisi($row),
            $row['izin'] ?? '',
            $row['keterangan'] ?? '',
        ];
    }

    /**
     * Konversi string waktu (H:i:s) ke Serial Number Excel, identik dengan
     * logika di AbsenExport supaya kolom waktu ke-render sebagai jam asli
     * (bukan teks) dan format hh:mm:ss di styles() berlaku dengan benar.
     */
    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        try {
            $parts = explode(':', $time);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0);

            $totalSeconds = ($h * 3600) + ($m * 60) + $s;

            return $totalSeconds / 86400;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sama seperti pembersihan Divisi di AbsenExport: uppercase, dedup,
     * dan untuk entri "LAIN-LAIN" ambil detailnya kalau ada. Di sini
     * sumbernya array 'sumber_label' dari service, bukan string
     * comma-separated seperti di AbsenExport, jadi tidak perlu explode.
     */
    protected function formatDivisi(array $row): string
    {
        $sumber = $row['sumber_label'] ?? [];

        if (empty($sumber)) {
            return '-';
        }

        return collect((array) $sumber)
            ->map(function ($item) {
                $itemUpper = strtoupper(trim($item));

                if (str_contains($itemUpper, 'LAIN-LAIN')) {
                    $detail = trim(str_ireplace(['LAIN-LAIN', ':', '-'], '', $item));

                    return $detail !== '' ? "LAIN-LAIN ($detail)" : 'LAIN-LAIN';
                }

                $name = trim(explode(':', explode('(', $item)[0])[0]);

                return strtoupper($name);
            })
            ->unique()
            ->implode(', ') ?: '-';
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI_'.$this->tanggal;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rekap->count() + 1;

        // 1. Style Header
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
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
                    'color' => ['rgb' => 'AAAAAA'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // 4. Alignment
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Wrap Text untuk kolom Divisi & Keterangan
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
            'G' => 40,
            'H' => 10,
            'I' => 45,
        ];
    }
}
