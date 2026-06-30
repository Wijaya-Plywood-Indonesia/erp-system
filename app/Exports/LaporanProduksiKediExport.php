<?php

namespace App\Exports;

use App\Exports\Sheets\JurnalKediSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanProduksiKediExport implements FromCollection, WithTitle, ShouldAutoSize, WithStyles, WithMultipleSheets, WithEvents
{
    protected Collection $data;
    protected array $mergeRanges = []; // Menyimpan koordinat untuk di-merge
    protected int $mainTableEndRow = 0;
    protected int $downtimeStartRow = 0;
    protected int $downtimeSubHeaderRow = 0;
    protected int $downtimeEndRow = 0;
    protected bool $hasDowntime = false;

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
            'kw AF',
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
            'kw AF',
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

        $totals = [
            'm_kw1' => 0, 'm_kw2' => 0, 'm_kw3' => 0, 'm_kw4' => 0, 'm_kwaf' => 0,
            'm_byk' => 0, 'm_m3' => 0, 'm_pkj' => 0,
            'b_kw1' => 0, 'b_kw2' => 0, 'b_kw3' => 0, 'b_kw4' => 0, 'b_kwaf' => 0,
            'b_byk' => 0, 'b_m3' => 0, 'b_pkj' => 0
        ];
        $currentRow = 4; // Data mulai di baris 4 (karena ada baris header 1, sub-header 2, dan summary 3)

        foreach ($this->data as $produksi) {
            $maxDetail = max(count($produksi['detail_masuk'] ?? []), count($produksi['detail_bongkar'] ?? []), 1);
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

                    $kwVal = (int)($dm['kw'] ?? 0);
                    $isKw1 = ($kwVal === 1);
                    $isKw2 = ($kwVal === 2);
                    $isKw3 = ($kwVal === 3);
                    $isKw4 = ($kwVal === 4);
                    $isKwAf = (!$isKw1 && !$isKw2 && !$isKw3 && !$isKw4);

                    $row[6] = $isKw1 ? $dm['jumlah'] : '';
                    $row[7] = $isKw2 ? $dm['jumlah'] : '';
                    $row[8] = $isKw3 ? $dm['jumlah'] : '';
                    $row[9] = $isKw4 ? $dm['jumlah'] : '';
                    $row[10] = $isKwAf ? $dm['jumlah'] : '';
                    $row[11] = $dm['jumlah'];
                    $row[12] = round($m3, 4);
                    $row[13] = $produksi['total_pekerja'];

                    if ($isKw1) $totals['m_kw1'] += $dm['jumlah'];
                    if ($isKw2) $totals['m_kw2'] += $dm['jumlah'];
                    if ($isKw3) $totals['m_kw3'] += $dm['jumlah'];
                    if ($isKw4) $totals['m_kw4'] += $dm['jumlah'];
                    if ($isKwAf) $totals['m_kwaf'] += $dm['jumlah'];

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

                    $kwVal = (int)($db['kw'] ?? 0);
                    $isKw1 = ($kwVal === 1);
                    $isKw2 = ($kwVal === 2);
                    $isKw3 = ($kwVal === 3);
                    $isKw4 = ($kwVal === 4);
                    $isKwAf = (!$isKw1 && !$isKw2 && !$isKw3 && !$isKw4);

                    $row[27] = $isKw1 ? $db['jumlah'] : '';
                    $row[28] = $isKw2 ? $db['jumlah'] : '';
                    $row[29] = $isKw3 ? $db['jumlah'] : '';
                    $row[30] = $isKw4 ? $db['jumlah'] : '';
                    $row[31] = $isKwAf ? $db['jumlah'] : '';
                    $row[32] = $db['jumlah'];
                    $row[33] = round($m3, 4);
                    $row[34] = $produksi['total_pekerja'];

                    if ($isKw1) $totals['b_kw1'] += $db['jumlah'];
                    if ($isKw2) $totals['b_kw2'] += $db['jumlah'];
                    if ($isKw3) $totals['b_kw3'] += $db['jumlah'];
                    if ($isKw4) $totals['b_kw4'] += $db['jumlah'];
                    if ($isKwAf) $totals['b_kwaf'] += $db['jumlah'];

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
        $summaryRow[6] = $totals['m_kw1'] ?: '';
        $summaryRow[7] = $totals['m_kw2'] ?: '';
        $summaryRow[8] = $totals['m_kw3'] ?: '';
        $summaryRow[9] = $totals['m_kw4'] ?: '';
        $summaryRow[10] = $totals['m_kwaf'] ?: '';
        $summaryRow[11] = $totals['m_byk'];
        $summaryRow[12] = round($totals['m_m3'], 3);
        $summaryRow[13] = $totals['m_pkj'];
        
        $summaryRow[27] = $totals['b_kw1'] ?: '';
        $summaryRow[28] = $totals['b_kw2'] ?: '';
        $summaryRow[29] = $totals['b_kw3'] ?: '';
        $summaryRow[30] = $totals['b_kw4'] ?: '';
        $summaryRow[31] = $totals['b_kwaf'] ?: '';
        $summaryRow[32] = $totals['b_byk'];
        $summaryRow[33] = round($totals['b_m3'], 3);
        $summaryRow[34] = $totals['b_pkj'];
        
        $rows->splice(2, 0, [$summaryRow]);

        // Hitung baris akhir tabel utama
        $totalDetailRows = 0;
        foreach ($this->data as $produksi) {
            $totalDetailRows += max(count($produksi['detail_masuk'] ?? []), count($produksi['detail_bongkar'] ?? []), 1);
        }
        $this->mainTableEndRow = 3 + $totalDetailRows;

        // Kumpulkan kendala jika ada
        $allKendala = collect();
        foreach ($this->data as $produksi) {
            if (!empty($produksi['kendala_kedis'])) {
                foreach ($produksi['kendala_kedis'] as $k) {
                    $allKendala->push($k);
                }
            }
        }

        if ($allKendala->isNotEmpty()) {
            $this->hasDowntime = true;
            $this->downtimeStartRow = $this->mainTableEndRow + 3;
            $this->downtimeSubHeaderRow = $this->mainTableEndRow + 4;
            $this->downtimeEndRow = $this->mainTableEndRow + 4 + $allKendala->count();

            // Tambah baris kosong untuk pemisah
            $rows->push(array_fill(0, 41, ''));
            $rows->push(array_fill(0, 41, ''));

            // Baris Judul Tabel Downtime
            $downtimeTitle = array_fill(0, 41, '');
            $downtimeTitle[0] = 'DAFTAR DOWNTIME & KENDALA MESIN';
            $rows->push($downtimeTitle);

            // Baris Sub-Header Tabel Downtime
            $downtimeSubHeader = array_fill(0, 41, '');
            $downtimeSubHeader[0] = 'No';
            $downtimeSubHeader[1] = 'Tanggal';
            $downtimeSubHeader[2] = 'Mesin';
            $downtimeSubHeader[3] = 'Waktu Mulai';
            $downtimeSubHeader[4] = 'Waktu Selesai';
            $downtimeSubHeader[5] = 'Durasi';
            $downtimeSubHeader[6] = 'Keterangan Kendala';
            $rows->push($downtimeSubHeader);

            // Baris Data Downtime
            $no = 1;
            foreach ($allKendala as $k) {
                $downtimeRow = array_fill(0, 41, '');
                $downtimeRow[0] = $no++;
                $downtimeRow[1] = $k['tanggal'];
                $downtimeRow[2] = $k['mesin'];
                $downtimeRow[3] = $k['waktu_mulai'];
                $downtimeRow[4] = $k['waktu_selesai'];
                $downtimeRow[5] = $k['durasi_menit'] ? $k['durasi_menit'] . ' menit' : '-';
                $downtimeRow[6] = $k['kendala'];
                $rows->push($downtimeRow);
            }
        }

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
        if (str_contains($n, 'mahoni')) return 'mh';
        if (str_contains($n, 'jabon')) return 'j';
        if (str_contains($n, 'waru')) return 'wr';
        return $name;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'MASUK')->mergeCells('A1:T1');
        $sheet->setCellValue('V1', 'BONGKAR')->mergeCells('V1:AO1');
        
        $sheet->getStyle('A1:T2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        $sheet->getStyle('V1:AO2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        $sheet->getStyle('A3:AO3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'font' => ['bold' => true]
        ]);

        $sheet->getStyle('A1:T' . $this->mainTableEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('V1:AO' . $this->mainTableEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        if ($this->hasDowntime) {
            $start = $this->downtimeStartRow;
            $subHeader = $this->downtimeSubHeaderRow;
            $end = $this->downtimeEndRow;

            // Merge header judul
            $sheet->mergeCells("A{$start}:G{$start}");
            $sheet->setCellValue("A{$start}", 'DAFTAR DOWNTIME & KENDALA MESIN');

            // Style judul
            $sheet->getStyle("A{$start}:G{$start}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']], // Dark red accent
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);

            // Style sub-header
            $sheet->getStyle("A{$subHeader}:G{$subHeader}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);

            // Border untuk tabel downtime
            $sheet->getStyle("A{$subHeader}:G{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Alignment data downtime
            $sheet->getStyle("A" . ($subHeader + 1) . ":A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B" . ($subHeader + 1) . ":B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D" . ($subHeader + 1) . ":E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F" . ($subHeader + 1) . ":F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
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
