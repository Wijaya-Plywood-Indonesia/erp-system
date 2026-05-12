<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Models\HargaPegawai;

class LaporanProduksiKediExport implements FromCollection, WithTitle, ShouldAutoSize, WithStyles
{
    protected Collection $data;

    public function __construct(array $data)
    {
        $this->data = collect($data);
    }

    public function collection(): Collection
    {
        $rows = collect();

        // Ambil Harga Pegawai Master
        $masterHargaPkj = HargaPegawai::first()->harga ?? 0;

        // Row 1: Group Headers (Tambah 1 kolom spasi di tengah)
        $groupHeader = array_fill(0, 41, '');
        $groupHeader[0] = 'MASUK';
        $groupHeader[21] = 'BONGKAR';
        $rows->push($groupHeader);

        // Row 2: Sub-headers
        $subHeader = [
            'Tanggal', 'Mesin', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'kw LB', 'byk', 'm3', 'TTL PKJ', 'HARGA', 'MESIN', 'ONGKOS PER M3', 'ONGKOS MESIN', 'ONGKOS PER M3+mesin', 'ONGKOS PER LB',
            '', // SPASI
            'Tanggal', 'Mesin', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'kw LB', 'byk', 'm3', 'TTL PKJ', 'HARGA', 'MESIN', 'ONGKOS PER M3', 'ONGKOS MESIN', 'ONGKOS PER M3+mesin', 'ONGKOS PER LB'
        ];
        $rows->push($subHeader);

        // Calculate Totals for Summary Row (Yellow Row)
        $totalMasukByk = 0; $totalMasukM3 = 0; $totalMasukPkj = 0;
        $totalBongkarByk = 0; $totalBongkarM3 = 0; $totalBongkarPkj = 0;

        $dataRows = collect();

        foreach ($this->data as $produksi) {
            $maxDetail = max(count($produksi['detail_masuk']), count($produksi['detail_bongkar']), 1);
            
            for ($i = 0; $i < $maxDetail; $i++) {
                $row = array_fill(0, 41, '');

                // Fill MASUK section
                if (isset($produksi['detail_masuk'][$i])) {
                    $dm = $produksi['detail_masuk'][$i];
                    $dimensi = explode(' x ', $dm['ukuran']);
                    $p = (float) str_replace('mm', '', $dimensi[0] ?? 0);
                    $l = (float) str_replace('mm', '', $dimensi[1] ?? 0);
                    $t = (float) str_replace('mm', '', $dimensi[2] ?? 0);
                    $m3 = ($p * $l * $t * (int)$dm['jumlah']) / 10000000;

                    $row[0] = $produksi['tanggal_masuk'];
                    $row[1] = $dm['mesin'];
                    $row[2] = $p;
                    $row[3] = $l;
                    $row[4] = $t;
                    $row[5] = $this->getJenisKayuShort($dm['jenis_kayu']);
                    $row[6] = ($dm['kw'] == 1) ? $dm['jumlah'] : '';
                    $row[7] = ($dm['kw'] == 2) ? $dm['jumlah'] : '';
                    $row[8] = ($dm['kw'] == 3) ? $dm['jumlah'] : '';
                    $row[9] = ($dm['kw'] == 4) ? $dm['jumlah'] : '';
                    $row[10] = ($dm['kw'] == 5) ? $dm['jumlah'] : ''; // kw LB
                    $row[11] = $dm['jumlah'];
                    $row[12] = round($m3, 4);
                    $row[13] = $produksi['total_pekerja'];
                    $row[14] = ''; // HARGA
                    $row[15] = 1;  // MESIN (Tetap 1 atau bisa dikosongkan jika diminta)
                    $row[16] = ''; // ONGKOS PER M3
                    $row[17] = ''; // ONGKOS MESIN
                    $row[18] = ''; // ONGKOS PER M3+mesin
                    $row[19] = ''; // ONGKOS PER LB
                    
                    $totalMasukByk += $dm['jumlah'];
                    $totalMasukM3 += $m3;
                }

                // Fill BONGKAR section (Mulai dari indeks 21)
                if (isset($produksi['detail_bongkar'][$i])) {
                    $db = $produksi['detail_bongkar'][$i];
                    $dimensi = explode(' x ', $db['ukuran']);
                    $p = (float) str_replace('mm', '', $dimensi[0] ?? 0);
                    $l = (float) str_replace('mm', '', $dimensi[1] ?? 0);
                    $t = (float) str_replace('mm', '', $dimensi[2] ?? 0);
                    $m3 = ($p * $l * $t * (int)$db['jumlah']) / 10000000;

                    $row[21] = $produksi['tanggal_keluar'];
                    $row[22] = $db['mesin'];
                    $row[23] = $p;
                    $row[24] = $l;
                    $row[25] = $t;
                    $row[26] = $this->getJenisKayuShort($db['jenis_kayu']);
                    $row[27] = ($db['kw'] == 1) ? $db['jumlah'] : '';
                    $row[28] = ($db['kw'] == 2) ? $db['jumlah'] : '';
                    $row[29] = ($db['kw'] == 3) ? $db['jumlah'] : '';
                    $row[30] = ($db['kw'] == 4) ? $db['jumlah'] : '';
                    $row[31] = ($db['kw'] == 5) ? $db['jumlah'] : ''; // kw LB
                    $row[32] = $db['jumlah'];
                    $row[33] = round($m3, 4);
                    $row[34] = $produksi['total_pekerja'];
                    $row[35] = ''; // HARGA
                    $row[36] = 1;  // MESIN
                    $row[37] = ''; // ONGKOS PER M3
                    $row[38] = ''; // ONGKOS MESIN
                    $row[39] = ''; // ONGKOS PER M3+mesin
                    $row[40] = ''; // ONGKOS PER LB

                    $totalBongkarByk += $db['jumlah'];
                    $totalBongkarM3 += $m3;
                }

                $dataRows->push($row);
            }
            $totalMasukPkj += $produksi['total_pekerja'];
            $totalBongkarPkj += $produksi['total_pekerja'];
        }

        // Row 3: Summary Row (Yellow)
        $summaryRow = array_fill(0, 41, '');
        $summaryRow[11] = $totalMasukByk;
        $summaryRow[12] = round($totalMasukM3, 3);
        $summaryRow[13] = $totalMasukPkj;
        
        $summaryRow[32] = $totalBongkarByk;
        $summaryRow[33] = round($totalBongkarM3, 3);
        $summaryRow[34] = $totalBongkarPkj;
        
        $rows->push($summaryRow);

        // Add Data Rows
        foreach ($dataRows as $dataRow) {
            $rows->push($dataRow);
        }

        return $rows;
    }

    private function getJenisKayuShort($name): string
    {
        $name = strtolower($name);
        if (str_contains($name, 'sengon')) return 's';
        if (str_contains($name, 'meranti')) return 'm';
        if (str_contains($name, 'jabur')) return 'jb';
        return $name;
    }

    public function styles(Worksheet $sheet)
    {
        // Merge Group Headers
        $sheet->mergeCells('A1:T1'); // MASUK
        $sheet->mergeCells('V1:AO1'); // BONGKAR
        
        $sheet->getStyle('A1:AO2')->getFont()->setBold(true);
        $sheet->getStyle('A1:AO2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Style for Summary Row (Row 3) - Yellow (Kecuali kolom spasi U)
        $sheet->getStyle('A3:T3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('V3:AO3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle('A3:AO3')->getFont()->setBold(true);

        // Borders - Diterapkan terpisah untuk MASUK dan BONGKAR
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:T' . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('V1:AO' . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Center everything
        $sheet->getStyle('A1:AO' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set width for spacer column U
        $sheet->getColumnDimension('U')->setWidth(5);

        return [];
    }

    public function title(): string
    {
        return 'Laporan Produksi Kedi';
    }
}
