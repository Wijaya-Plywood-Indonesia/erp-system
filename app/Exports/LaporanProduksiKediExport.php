<?php

namespace App\Exports;

use App\Exports\Sheets\JurnalKediSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets; // 1. TAMBAHAN UNTUK MULTI-SHEET
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 3. TAMBAHKAN "WithMultipleSheets" DI SINI
class LaporanProduksiKediExport implements FromCollection, WithTitle, ShouldAutoSize, WithStyles, WithMultipleSheets
{
    protected Collection $data;
    protected array $mergeRanges = []; // Menyimpan koordinat untuk di-merge

    public function __construct(array $data)
    {
        $this->data = collect($data);
    }

    public function collection(): Collection
    {
        $rows = collect();
        $rows->push(array_fill(0, 41, '')); // Row 1: Header

        $subHeader = [
            'Tanggal',
            'Mesin',
            'p',
            'l',
            't',
            'jenis',
            'kw1',
            'kw2',
            'kw3',
            'kw4',
            'kw LB',
            'byk',
            'm3',
            'TTL PKJ',
            'HARGA',
            'MESIN',
            'ONGKOS PER M3',
            'ONGKOS MESIN',
            'ONGKOS PER M3+mesin',
            'ONGKOS PER LB',
            '',
            'Tanggal',
            'Mesin',
            'p',
            'l',
            't',
            'jenis',
            'kw1',
            'kw2',
            'kw3',
            'kw4',
            'kw LB',
            'byk',
            'm3',
            'TTL PKJ',
            'HARGA',
            'MESIN',
            'ONGKOS PER M3',
            'ONGKOS MESIN',
            'ONGKOS PER M3+mesin',
            'ONGKOS PER LB'
        ];
        $rows->push($subHeader);

        $totals = ['m_byk' => 0, 'm_m3' => 0, 'm_pkj' => 0, 'b_byk' => 0, 'b_m3' => 0, 'b_pkj' => 0];
        $currentRow = 4; // Data mulai di baris 4 (karena ada baris header 1, sub-header 2, dan summary 3)

        foreach ($this->data as $produksi) {
            $maxDetail = max(count($produksi['detail_masuk']), count($produksi['detail_bongkar']), 1);
            $startRow = $currentRow;

            for ($i = 0; $i < $maxDetail; $i++) {
                $row = array_fill(0, 41, '');

                if (isset($produksi['detail_masuk'][$i])) {
                    $dm = $produksi['detail_masuk'][$i];
                    $d = explode(' x ', $dm['ukuran']);
                    $p = (float)str_replace('mm', '', $d[0] ?? 0);
                    $l = (float)str_replace('mm', '', $d[1] ?? 0);
                    $t = (float)str_replace('mm', '', $d[2] ?? 0);
                    $m3 = ($p * $l * $t * (int)$dm['jumlah']) / 10000000;

                    $row[0] = $produksi['tanggal_masuk'];
                    $row[1] = $dm['mesin'];
                    $row[2] = $p;
                    $row[3] = $l;
                    $row[4] = $t;
                    $row[5] = $this->getJenisKayuShort($dm['jenis_kayu']);
                    $row[6] = ($dm['kw'] == 1 ? $dm['jumlah'] : '');
                    $row[7] = ($dm['kw'] == 2 ? $dm['jumlah'] : '');
                    $row[8] = ($dm['kw'] == 3 ? $dm['jumlah'] : '');
                    $row[9] = ($dm['kw'] == 4 ? $dm['jumlah'] : '');
                    $row[10] = ($dm['kw'] == 5 ? $dm['jumlah'] : '');
                    $row[11] = $dm['jumlah'];
                    $row[12] = round($m3, 4);
                    $row[13] = $produksi['total_pekerja'];

                    $totals['m_byk'] += $dm['jumlah'];
                    $totals['m_m3'] += $m3;
                }

                if (isset($produksi['detail_bongkar'][$i])) {
                    $db = $produksi['detail_bongkar'][$i];
                    $d = explode(' x ', $db['ukuran']);
                    $p = (float)str_replace('mm', '', $d[0] ?? 0);
                    $l = (float)str_replace('mm', '', $d[1] ?? 0);
                    $t = (float)str_replace('mm', '', $d[2] ?? 0);
                    $m3 = ($p * $l * $t * (int)$db['jumlah']) / 10000000;

                    $row[21] = $produksi['tanggal_keluar'];
                    $row[22] = $db['mesin'];
                    $row[23] = $p;
                    $row[24] = $l;
                    $row[25] = $t;
                    $row[26] = $this->getJenisKayuShort($db['jenis_kayu']);
                    $row[27] = ($db['kw'] == 1 ? $db['jumlah'] : '');
                    $row[28] = ($db['kw'] == 2 ? $db['jumlah'] : '');
                    $row[29] = ($db['kw'] == 3 ? $db['jumlah'] : '');
                    $row[30] = ($db['kw'] == 4 ? $db['jumlah'] : '');
                    $row[31] = ($db['kw'] == 5 ? $db['jumlah'] : '');
                    $row[32] = $db['jumlah'];
                    $row[33] = round($m3, 4);
                    $row[34] = $produksi['total_pekerja'];

                    $totals['b_byk'] += $db['jumlah'];
                    $totals['b_m3'] += $m3;
                }
                $rows->push($row);
                $currentRow++;
            }

            // Jika ada lebih dari satu baris detail, tandai untuk di-merge
            if ($maxDetail > 1) {
                $this->mergeRanges[] = ['start' => $startRow, 'end' => $currentRow - 1];
            }
            $totals['m_pkj'] += $produksi['total_pekerja'];
            $totals['b_pkj'] += $produksi['total_pekerja'];
        }

        $summaryRow = array_fill(0, 41, '');
        $summaryRow[0] = 'TOTAL';
        $summaryRow[11] = $totals['m_byk'];
        $summaryRow[12] = round($totals['m_m3'], 3);
        $summaryRow[13] = $totals['m_pkj'];
        $summaryRow[32] = $totals['b_byk'];
        $summaryRow[33] = round($totals['b_m3'], 3);
        $summaryRow[34] = $totals['b_pkj'];
        $rows->splice(2, 0, [$summaryRow]);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->mergeRanges as $r) {
                    // Sisi MASUK (A=0, B=1, N=13)
                    foreach (['A', 'B', 'N'] as $col) {
                        $sheet->mergeCells("{$col}{$r['start']}:{$col}{$r['end']}");
                        $sheet->getStyle("{$col}{$r['start']}:{$col}{$r['end']}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Sisi BONGKAR
                    // Berdasarkan makeRow/collection Anda: 
                    // Row 21=V, 22=W, 34=AI (TTL PKJ pada bongkar)
                    // Pastikan huruf kolom ini sesuai dengan posisi array $row Anda
                    foreach (['V', 'W', 'AI'] as $col) {
                        $sheet->mergeCells("{$col}{$r['start']}:{$col}{$r['end']}");
                        $sheet->getStyle("{$col}{$r['start']}:{$col}{$r['end']}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    }
                }
            }
        ];
    }

    private function getJenisKayuShort($name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'sengon')) return 's';
        if (str_contains($n, 'meranti')) return 'm';
        return $name;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'MASUK')->mergeCells('A1:T1');
        $sheet->setCellValue('V1', 'BONGKAR')->mergeCells('V1:AO1');
        $sheet->getStyle('A1:AO2')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']], 'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $sheet->getStyle('A3:AO3')->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']], 'font' => ['bold' => true]]);
        $hRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:T' . $hRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('V1:AO' . $hRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    public function title(): string
    {
        return 'Laporan Produksi Kedi';
    }

    // 4. INI ADALAH FUNGSI TAMBAHAN UNTUK MENAMPILKAN MULTI-SHEET
    public function sheets(): array
    {
        return [
            $this, // Sheet ke-1: Mengambil dari fungsi collection() di atas (Laporan Kedi Asli)
            new JurnalKediSheet($this->data->toArray()) // Sheet ke-2: Memanggil file JurnalKediSheet yang baru saja dibuat
        ];
    }
}
