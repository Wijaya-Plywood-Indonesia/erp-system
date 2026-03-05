<?php

namespace App\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportExcelPersentaseKayuService implements
    FromArray,
    WithHeadings,
    WithStyles,
    WithColumnWidths,
    WithTitle
{
    protected array $laporan;
    protected array $rekap;
    protected string $activeSheet;
    protected string $date;

    protected array $mergeBatches = [];

    public function __construct(array $laporan, array $rekap, string $activeSheet, string $date)
    {
        $this->laporan = $laporan;
        $this->rekap = $rekap;
        $this->activeSheet = $activeSheet;
        $this->date = $date;
    }

    public function array(): array
    {
        $rows = [];

        // BARIS TOTAL (Sesuai gambar di bawah header)
        $rows[] = [
            'Total',
            '',
            '',
            $this->rekap['total_kayu_masuk'],
            $this->rekap['total_pecah_masuk'] ?? 0,
            (float) $this->rekap['total_kubikasi_kayu_masuk'],
            (float) $this->rekap['total_poin_masuk'],
            '',
            '',
            '',
            '',
            (float) $this->rekap['total_kubikasi_veneer'],
            'Rata-rata',
            $this->rekap['rata_rata_rendemen'],
            (float) ($this->rekap['total_poin_masuk'] / ($this->rekap['total_kubikasi_veneer'] ?: 1)),
            '',
            '',
            (float) $this->rekap['total_harga_v_ongkos'],
            '',
            (float) $this->rekap['total_harga_vop']
        ];

        $currentRow = 5; // Data dimulai dari baris 4 (1&2 Header, 3 Total)
        foreach ($this->laporan as $item) {
            $outflowCount = count($item['outflow']);
            $totalPoin = (float) str_replace('.', '', $item['summary']['total_poin'] ?? 0);
            $totalM3Keluar = (float) ($item['summary']['total_keluar_m3'] ?: 1);

            // Tentukan posisi start dan end merge sebelum manipulasi row
            $this->mergeBatches[] = [
                'start' => $currentRow,
                'end' => $currentRow + $outflowCount - 1
            ];

            foreach ($item['outflow'] as $index => $prod) {
                $isFirstInBatch = ($index === 0);
                $isLastInBatch = ($index === $outflowCount - 1);

                $rows[] = [
                    $prod['tgl'],
                    $isLastInBatch ? '✓' : '',
                    $isFirstInBatch ? $item['batch_info']['kode'] : '',
                    $isFirstInBatch ? $item['summary']['total_kayu_masuk'] : '',
                    '',
                    $isFirstInBatch ? $item['summary']['total_masuk_m3'] : '',
                    $isFirstInBatch ? $totalPoin : '',
                    $prod['panjang'],
                    $prod['lebar'],
                    $prod['tebal'],
                    $prod['total_banyak'],
                    (float) $prod['total_kubikasi'],
                    '06:00 - 16:00',
                    $isFirstInBatch ? $item['summary']['rendemen'] : '',
                    $isFirstInBatch ? ($totalPoin / $totalM3Keluar) : '',
                    $prod['pekerja'],
                    (float) $prod['ongkos'],
                    $isFirstInBatch ? (float) $item['summary']['harga_v_ongkos'] : '',
                    (float) $prod['penyusutan'],
                    $isFirstInBatch ? (float) $item['summary']['harga_vop'] : '',
                ];
                $currentRow++;
            }

            // TAMBAHKAN BARIS KOSONG SETIAP SELESAI SATU BATCH
            $rows[] = array_fill(0, 20, ''); // Membuat 20 kolom kosong
            $currentRow++; // Loncat satu baris agar batch berikutnya tidak menabrak baris kosong
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            ["KAYU {$this->activeSheet} ( {$this->date} )", '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Tanggal', 'Habis', 'Kayu', '', '', '', '', 'Veneer', '', '', '', '', 'Jam Kerja', '%', 'harga veneer/m3', 'Pekerja', 'Ongkos/pkj', 'Harga Veneer + Ongkos', 'Penyusutan', 'Harga Veneer + Ongkos + penyusutan'],
            ['', '', 'Lahan', 'Batang', 'Pecah', 'm3', 'Poin', 'Panjang', 'Lebar', 'Tebal', 'Lembar', 'm3', '', '', '', '', '', '', '', '']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getRowDimension(1)->setRowHeight(18); // Angka 35 bisa Anda sesuaikan (default sekitar 15)
        $sheet->getRowDimension(2)->setRowHeight(25); // Angka 35 bisa Anda sesuaikan (default sekitar 15)
        $sheet->getRowDimension(4)->setRowHeight(18); // Angka 35 bisa Anda sesuaikan (default sekitar 15)

        $sheet->getStyle('A1:T4')->getAlignment()->setWrapText(true);

        // Tambahkan juga Vertical Center agar teks berada di tengah secara vertikal
        $sheet->getStyle('A1:T4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // MERGING HEADERS
        $sheet->mergeCells('A1:T1'); // ! NAMA LAHAN
        $sheet->mergeCells('A2:A3'); // Tanggal
        $sheet->mergeCells('B2:B3'); // Habis
        $sheet->mergeCells('C2:G2'); // Group Kayu
        $sheet->mergeCells('H2:L2'); // Group Veneer
        $sheet->mergeCells('M2:M3'); // Jam Kerja
        $sheet->mergeCells('N2:N3'); // %
        $sheet->mergeCells('O2:O3'); // harga veneer/m3
        $sheet->mergeCells('P2:P3'); // Pekerja
        $sheet->mergeCells('Q2:Q3'); // Ongkos
        $sheet->mergeCells('R2:R3'); // Harga V+O
        $sheet->mergeCells('S2:S3'); // Penyusutan
        $sheet->mergeCells('T2:T3'); // Harga V+O+P

        // HEADER STYLE
        $sheet->getStyle('A1:T4')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);

        // BARIS TOTAL (Baris 3)
        $sheet->getStyle('A4:T4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']], // Oranye/Kuning Emas
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // COLUMN COLORS (Sesuai Gambar)
        $sheet->getStyle('F5:G' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Kayu
        $sheet->getStyle('L5:L' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Veneer m3
        $sheet->getStyle('N5:N' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda %
        $sheet->getStyle('O5:O' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050'); // Hijau Harga m3
        $sheet->getStyle('R5:R' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000'); // Oranye Harga V+O
        $sheet->getStyle('S5:S' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Penyusutan
        $sheet->getStyle('T5:T' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00'); // Kuning Terang VOP

        // ALIGNMENT & BORDERS
        $sheet->getStyle('A5:T' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5:T' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle('A5:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M5:N' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('P5:P' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // FORMAT ANGKA
        $sheet->getStyle('F4:F' . $lastRow)->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle('L4:L' . $lastRow)->getNumberFormat()->setFormatCode('0.0000');
        // ! POINT
        // $sheet->getStyle('G4:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G4:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        // !
        $sheet->getStyle('O4:O' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('Q4:T' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

        $mergeRow = ['C', 'D', 'E', 'F', 'G', 'N', 'O', 'R', 'T'];
        foreach ($this->mergeBatches as $batch) {
            foreach ($mergeRow as $column) {
                $sheet->mergeCells("{$column}{$batch['start']}:{$column}{$batch['end']}");

                // Tambahkan ini agar teks berada di tengah secara vertikal (Center)
                $sheet->getStyle("{$column}{$batch['start']}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $theEnd = $batch['end'] + 1;
            $sheet->getStyle("A{$theEnd}:T{$theEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 7,
            'C' => 8,
            'D' => 9,
            'E' => 8,
            'F' => 12,
            'G' => 14,
            'H' => 10,
            'I' => 8,
            'J' => 8,
            'K' => 10,
            'L' => 12,
            'M' => 14,
            'N' => 9,
            'O' => 18,
            'P' => 9,
            'Q' => 12,
            'R' => 20,
            'S' => 12,
            'T' => 20,
        ];
    }


    public function title(): string
    {
        return "Kayu {$this->activeSheet}";
    }
}