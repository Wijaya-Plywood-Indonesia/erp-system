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
    protected int $originalPrecision;          // ← Simpan nilai asli sebelum diubah
    protected int $originalSerializePrecision; // ← Simpan nilai asli sebelum diubah

    public function __construct(array $data)
    {
        // --- 1. LOGIKA CUSTOM SORTING ---
        // Kita urutkan data sebelum disimpan ke property $this->data
        usort($data, function ($a, $b) {
            $kodeA = (string)($a['kodep'] ?? '');
            $kodeB = (string)($b['kodep'] ?? '');

            // Menentukan bobot prioritas
            $getWeight = function ($kode) {
                if (str_starts_with($kode, '8') || str_starts_with($kode, '9')) {
                    return 1; // Prioritas pertama (Paling Atas)
                }
                if (str_starts_with($kode, '7')) {
                    return 3; // Prioritas terakhir (Paling Bawah)
                }
                return 2; // Kode lainnya (1, 2, 3, 4, 5, 6) ada di tengah
            };

            $weightA = $getWeight($kodeA);
            $weightB = $getWeight($kodeB);

            // Jika prioritas grup berbeda, gunakan perbandingan bobot
            if ($weightA !== $weightB) {
                return $weightA <=> $weightB;
            }

            // Jika berada dalam grup yang sama, urutkan berdasarkan nomor secara alami
            return strnatcasecmp($kodeA, $kodeB);
        });

        $this->data = $data;

        // --- 2. PENGATURAN PRESISI (Tetap Sama) ---
        $this->originalPrecision = (int) ini_get('precision');
        $this->originalSerializePrecision = (int) ini_get('serialize_precision');

        ini_set('precision', 16);
        ini_set('serialize_precision', -1);
    }

    /**
     * Destructor — kembalikan precision ke nilai semula setelah export selesai
     * agar tidak mempengaruhi proses lain di aplikasi
     */
    public function __destruct()
    {
        ini_set('precision', $this->originalPrecision);
        ini_set('serialize_precision', $this->originalSerializePrecision);
    }

    /**
     * Mengolah data array untuk ditampilkan di baris Excel
     */
    public function array(): array
    {
        $result = [];
        foreach ($this->data as $row) {
            $divisiRaw = is_array($row['hasil']) ? $row['hasil'] : explode(', ', $row['hasil'] ?? '');

            $cleanDivisi = collect($divisiRaw)->map(function ($item) {
                $itemUpper = strtoupper(trim($item));

                if (str_contains($itemUpper, 'LAIN-LAIN')) {
                    $detail = trim(str_ireplace(['LAIN-LAIN', ':', '-'], '', $item));
                    return !empty($detail) ? "LAIN-LAIN ($detail)" : "LAIN-LAIN";
                }

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
     * Menggunakan totalSeconds / 86400 agar hanya 1x operasi divisi
     * sehingga floating point error tidak menumpuk.
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

            // 1 operasi divisi saja — lebih presisi dari 3 operasi terpisah
            $totalSeconds = ($h * 3600) + ($m * 60) + $s;
            return $totalSeconds / 86400;
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
            'I' => 45
        ];
    }

    public function title(): string
    {
        return 'LAPORAN_ABSENSI_' . date('Y-m-d');
    }
}
