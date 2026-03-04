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

    protected array $mergeBatches = [];

    public function __construct(array $laporan, array $rekap, string $activeSheet)
    {
        $this->laporan = $laporan;
        $this->rekap = $rekap;
        $this->activeSheet = $activeSheet;
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

        $currentRow = 4; // Data dimulai dari baris 4 (1&2 Header, 3 Total)
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
                    $isFirstInBatch ? $item['summary']['total_poin'] : '',
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
            ['Tanggal', 'Habis', 'Kayu', '', '', '', '', 'Veneer', '', '', '', '', 'Jam Kerja', '%', 'harga veneer/m3', 'Pekerja', 'Ongkos/pkj', 'Harga Veneer + Ongkos', 'Penyusutan', 'Harga Veneer + Ongkos + penyusutan'],
            ['', '', 'Lahan', 'Batang', 'Pecah', 'm3', 'Poin', 'Panjang', 'Lebar', 'Tebal', 'Lembar', 'm3', '', '', '', '', '', '', '', '']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getRowDimension(1)->setRowHeight(25); // Angka 35 bisa Anda sesuaikan (default sekitar 15)
        $sheet->getRowDimension(3)->setRowHeight(18); // Angka 35 bisa Anda sesuaikan (default sekitar 15)

        $sheet->getStyle('A1:T3')->getAlignment()->setWrapText(true);

        // Tambahkan juga Vertical Center agar teks berada di tengah secara vertikal
        $sheet->getStyle('A1:T3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // MERGING HEADERS
        $sheet->mergeCells('A1:A2'); // Tanggal
        $sheet->mergeCells('B1:B2'); // Habis
        $sheet->mergeCells('C1:G1'); // Group Kayu
        $sheet->mergeCells('H1:L1'); // Group Veneer
        $sheet->mergeCells('M1:M2'); // Jam Kerja
        $sheet->mergeCells('N1:N2'); // %
        $sheet->mergeCells('O1:O2'); // harga veneer/m3
        $sheet->mergeCells('P1:P2'); // Pekerja
        $sheet->mergeCells('Q1:Q2'); // Ongkos
        $sheet->mergeCells('R1:R2'); // Harga V+O
        $sheet->mergeCells('S1:S2'); // Penyusutan
        $sheet->mergeCells('T1:T2'); // Harga V+O+P

        // HEADER STYLE
        $sheet->getStyle('A1:T2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);

        // BARIS TOTAL (Baris 3)
        $sheet->getStyle('A3:T3')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']], // Oranye/Kuning Emas
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // COLUMN COLORS (Sesuai Gambar)
        $sheet->getStyle('F4:G' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Kayu
        $sheet->getStyle('L4:L' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Veneer m3
        $sheet->getStyle('N4:N' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda %
        $sheet->getStyle('O4:O' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050'); // Hijau Harga m3
        $sheet->getStyle('R4:R' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000'); // Oranye Harga V+O
        $sheet->getStyle('S4:S' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE'); // Biru Muda Penyusutan
        $sheet->getStyle('T4:T' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00'); // Kuning Terang VOP

        // ALIGNMENT & BORDERS
        $sheet->getStyle('A4:T' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:T' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle('A4:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M4:N' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('P4:P' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // FORMAT ANGKA
        $sheet->getStyle('F3:F' . $lastRow)->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle('L3:L' . $lastRow)->getNumberFormat()->setFormatCode('0.0000');
        // ! POINT
        // $sheet->getStyle('G3:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G3' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        // !
        $sheet->getStyle('O3:O' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('Q3:T' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

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