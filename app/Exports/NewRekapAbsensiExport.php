<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
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
        $tanggalHariIni = Carbon::parse($this->tanggal)->format('d/m/Y');
        $tanggalBesok = Carbon::parse($this->tanggal)->addDay()->format('d/m/Y');

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
            "Finger Masuk ({$tanggalHariIni})",
            "Finger Pulang ({$tanggalHariIni})",
            "Finger Masuk ({$tanggalBesok})",
            "Finger Pulang ({$tanggalBesok})",
        ];
    }

    /**
     * @param  array  $row  1 baris hasil NewRekapAbsensiPegawaiService::getRekap()
     */
    public function map($row): array
    {
        $preview = $row['_finger_preview'] ?? null;
        $simPagiHariIni = is_array($preview) ? ($preview['simulasi_pagi'] ?? null) : null;
        $simPagiBesok = is_array($preview) ? ($preview['simulasi_pagi_besok'] ?? null) : null;

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
            $this->convertTimeToExcel($simPagiHariIni['jam_masuk_finger'] ?? null),
            $this->convertTimeToExcel($simPagiHariIni['jam_pulang_finger'] ?? null),
            $this->convertTimeToExcel($simPagiBesok['jam_masuk_finger'] ?? null),
            $this->convertTimeToExcel($simPagiBesok['jam_pulang_finger'] ?? null),
        ];
    }

    /**
     * Konversi string waktu (H:i:s) ke Serial Number Excel.
     * Dibulatkan 8 digit desimal (~0.86 detik) supaya bersih dari
     * floating-point drift, tanpa perlu event/cell-type tambahan.
     */
    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        $totalSeconds = ($h * 3600) + ($m * 60) + $s;

        return round($totalSeconds / 86400, 8);
    }

    protected function formatDivisi(array $row): string
    {
        $sumber = $row['sumber_label'] ?? [];

        if (empty($sumber)) {
            return '-';
        }

        return collect((array) $sumber)
            ->map(function ($item) {
                $item = trim($item);
                $itemUpper = strtoupper($item);

                if (str_contains($itemUpper, 'LAIN-LAIN')) {
                    $detail = trim(str_ireplace(['LAIN-LAIN', ':', '-'], '', $item));

                    return $detail !== '' ? "LAIN-LAIN ($detail)" : 'LAIN-LAIN';
                }

                if (str_contains($item, ':')) {
                    [$name, $detail] = array_map('trim', explode(':', $item, 2));
                    $name = strtoupper($name);

                    return $detail !== '' ? "{$name} ({$detail})" : $name;
                }

                return strtoupper($item);
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

        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("C2:F{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        $sheet->getStyle("J2:M{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm:ss');

        $sheet->getStyle("A1:M{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'AAAAAA'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J2:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);

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
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 16,
        ];
    }
}
