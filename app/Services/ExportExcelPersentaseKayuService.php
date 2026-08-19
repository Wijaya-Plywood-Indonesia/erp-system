<?php

namespace App\Services;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportExcelPersentaseKayuService implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    protected array $laporan;

    protected array $rekap;

    protected string $activeSheet;

    protected string $date;

    protected array $mergeBatches = [];

    /**
     * true kalau MINIMAL SATU baris outflow di laporan ini punya bahan penolong
     * (Solasi). Kalau semua kosong, kolom "Solasi" dan "Biaya Bahan Penolong"
     * disembunyikan total (header + sel), bukan cuma diisi "-", sama seperti
     * pola yang dipakai di halaman Blade-nya.
     *
     * CATATAN: kolom "Harga Veneer+Ongkos+Penyusutan+Bahan Penolong" DIHAPUS
     * dari export ini atas permintaan — sekarang hanya ada 2 kolom bahan
     * penolong (Solasi, Biaya Bahan Penolong), bukan 3.
     */
    protected bool $adaBahanPenolong;

    public function __construct(array $laporan, array $rekap, string $activeSheet, string $date)
    {
        $this->laporan = $laporan;
        $this->rekap = $rekap;
        $this->activeSheet = $activeSheet;
        $this->date = $date;

        $this->adaBahanPenolong = collect($laporan)->contains(
            fn ($item) => ($item['summary']['total_bahan_penolong'] ?? 0) > 0
        );
    }

    public function array(): array
    {
        $rows = [];

        // Kolom "Harga Total / m³" SELALU ada di paling akhir, baik ada bahan
        // penolong maupun tidak. Nilainya = (harga_vopb kalau batch punya bahan
        // penolong, kalau tidak harga_vop) DIBAGI total kubikasi produksi (m³)
        // batch tersebut — jadi murni rate per m³, bukan nominal total lagi.
        // Total kolom: 20 kolom dasar (A-T) + 2 kolom bahan penolong (U,V)
        // kalau adaBahanPenolong + 1 kolom "Harga Total/m3" (selalu ada).
        $jumlahKolom = $this->adaBahanPenolong ? 23 : 21;

        // BARIS TOTAL (Sesuai gambar di bawah header)
        $totalRow = [
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
            (float) $this->rekap['total_harga_veneer'],
            '',
            '',
            (float) $this->rekap['total_harga_v_ongkos'],
            '',
            (float) $this->rekap['total_harga_vop'],
        ];

        if ($this->adaBahanPenolong) {
            // Kolom Solasi di baris Total dikosongkan (bukan daftar, cuma info per baris),
            // dan kolom "Biaya Bahan Penolong" diisi rata-rata biaya bahan penolong per m³
            // dari SELURUH batch (total biaya bahan penolong / total kubikasi keluar).
            // Ini MEMANG rate per m³ (bukan nominal) — sudah dikonfirmasi benar.
            $totalBahanSemua = collect($this->laporan)->sum(fn ($item) => $item['summary']['total_bahan_penolong'] ?? 0);
            $totalKeluarM3Semua = collect($this->laporan)->sum(fn ($item) => (float) ($item['summary']['total_keluar_m3'] ?? 0));
            $bahanPerM3Total = $totalKeluarM3Semua > 0 ? $totalBahanSemua / $totalKeluarM3Semua : 0;

            $totalRow[] = '';
            $totalRow[] = (float) $bahanPerM3Total;

            // Kolom "Harga Veneer+Ongkos+Penyusutan+Bahan Penolong" DIHAPUS dari export ini.
        }

        // Kolom "Harga Total / m³" di baris Total = (Harga VOPB atau VOP total) /
        // total kubikasi veneer keseluruhan. Ini murni rate per m³.
        $totalHargaVOPorBSemua = $this->adaBahanPenolong
            ? (float) ($this->rekap['total_harga_vopb'] ?? 0)
            : (float) ($this->rekap['total_harga_vop'] ?? 0);
        $totalKubikasiVeneerSemua = (float) ($this->rekap['total_kubikasi_veneer'] ?? 0);

        $totalRow[] = $totalKubikasiVeneerSemua > 0
            ? $totalHargaVOPorBSemua / $totalKubikasiVeneerSemua
            : 0;

        $rows[] = $totalRow;

        $currentRow = 5; // Data dimulai dari baris 4 (1&2 Header, 3 Total)
        foreach ($this->laporan as $item) {
            $outflowCount = count($item['outflow']);
            $totalPoin = (float) str_replace('.', '', $item['summary']['total_poin'] ?? 0);
            $totalM3Keluar = (float) ($item['summary']['total_keluar_m3'] ?: 1);

            // Tentukan posisi start dan end merge sebelum manipulasi row
            $this->mergeBatches[] = [
                'start' => $currentRow,
                'end' => $currentRow + $outflowCount - 1,
            ];

            foreach ($item['outflow'] as $index => $prod) {
                $isFirstInBatch = ($index === 0);
                $isLastInBatch = ($index === $outflowCount - 1);

                $row = [
                    // Kolom Tanggal: hanya diisi di baris pertama batch (akan di-merge),
                    // dan nilainya adalah tanggal outflow TERAKHIR dalam batch ini
                    // (bukan tanggal pertama), sesuai permintaan.
                    $isFirstInBatch ? $item['outflow'][$outflowCount - 1]['tgl'] : '',
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
                    $isFirstInBatch ? $item['summary']['harga_veneer'] : '',
                    $prod['pekerja'],
                    (float) $prod['ongkos'],
                    $isFirstInBatch ? (float) $item['summary']['harga_v_ongkos'] : '',
                    (float) $prod['penyusutan'],
                    $isFirstInBatch ? (float) $item['summary']['harga_vop'] : '',
                ];

                if ($this->adaBahanPenolong) {
                    // Kolom Solasi: total NOMINAL per baris produksi ini (jumlah
                    // roll dibulatkan normal, dikali harga_satuan) — Opsi B,
                    // ditampilkan langsung sebagai Rupiah, bukan format "3 x Rp ...".
                    $bahanList = collect($prod['bahan_penolong'] ?? []);
                    $solasiText = $bahanList->isNotEmpty()
                        ? $bahanList->map(fn ($b) => 'Rp '.number_format(round($b['jumlah'] ?? 0) * ($b['harga_satuan'] ?? 0), 0, ',', '.'))->implode(', ')
                        : '-';

                    // Kolom Biaya Bahan Penolong: subtotal bahan penolong baris ini
                    // (dari jumlah DESIMAL ASLI, bukan dibulatkan) dibagi kubikasi
                    // baris ini (Rp / m³) — SUDAH DIKONFIRMASI BENAR, ini memang
                    // rate per m³, bukan nominal total, dan TIDAK ikut dibulatkan
                    // seperti kolom Solasi di atas.
                    $subtotalBahanBaris = $bahanList->sum('subtotal');
                    $kubikasiBaris = (float) $prod['total_kubikasi'];
                    $bahanPerM3Baris = $kubikasiBaris > 0 ? $subtotalBahanBaris / $kubikasiBaris : 0;

                    $row[] = $solasiText;
                    $row[] = $subtotalBahanBaris > 0 ? (float) $bahanPerM3Baris : '-';

                    // Kolom "Harga Veneer+Ongkos+Penyusutan+Bahan Penolong" DIHAPUS
                    // dari export ini — tidak ada lagi $row[] untuk itu.
                }

                // Kolom "Harga Total / m³" SELALU ada, diisi hanya di baris pertama
                // batch (akan di-merge sama seperti kolom harga lain). Nilainya =
                // (harga_vopb kalau batch ini punya bahan penolong, kalau tidak
                // harga_vop) DIBAGI total kubikasi produksi batch ini
                // ($totalM3Keluar, sudah dihitung di atas dari
                // $item['summary']['total_keluar_m3'], fallback 1 biar tidak div/0).
                if ($isFirstInBatch) {
                    $adaBahanDiBatchIni = ($item['summary']['total_bahan_penolong'] ?? 0) > 0;
                    $hargaVOPorBBatch = $adaBahanDiBatchIni
                        ? (float) $item['summary']['harga_vopb']
                        : (float) $item['summary']['harga_vop'];

                    $row[] = $totalM3Keluar > 0 ? $hargaVOPorBBatch / $totalM3Keluar : 0;
                } else {
                    $row[] = '';
                }

                $rows[] = $row;
                $currentRow++;
            }

            // TAMBAHKAN BARIS KOSONG SETIAP SELESAI SATU BATCH
            $rows[] = array_fill(0, $jumlahKolom, ''); // Membuat kolom kosong (menyesuaikan jumlah kolom aktif)
            $currentRow++; // Loncat satu baris agar batch berikutnya tidak menabrak baris kosong
        }

        return $rows;
    }

    public function headings(): array
    {
        $row1 = ["KAYU {$this->activeSheet} ( {$this->date} )", '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $row2 = ['Tanggal', 'Habis', 'Kayu', '', '', '', '', 'Veneer', '', '', '', '', 'Jam Kerja', '%', 'harga veneer/m3', 'Pekerja', 'Ongkos/pkj', 'Harga Veneer + Ongkos', 'Penyusutan', 'Harga Veneer + Ongkos + penyusutan'];
        $row3 = ['', '', 'Lahan', 'Batang', 'Pecah', 'm3', 'Poin', 'Panjang', 'Lebar', 'Tebal', 'Lembar', 'm3', '', '', '', '', '', '', '', ''];

        if ($this->adaBahanPenolong) {
            $row1[] = '';
            $row1[] = '';

            $row2[] = 'Solasi';
            $row2[] = 'Solasi / m³';

            $row3[] = '';
            $row3[] = '';
        }

        // Kolom "Harga Total / m³" SELALU ada di paling akhir, ada atau tidaknya
        // bahan penolong.
        $row1[] = '';
        $row2[] = 'Harga Total / m³';
        $row3[] = '';

        return [$row1, $row2, $row3];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Kolom terakhir: 'W' kalau ada bahan penolong (U=Solasi, V=Biaya Bahan
        // Penolong, W=Harga Total/m³), 'U' kalau tidak (cuma kolom "Harga
        // Total / m³" saja yang ditambahkan).
        $lastCol = $this->adaBahanPenolong ? 'W' : 'U';

        // Posisi kolom "Harga Total / m³":
        // - 'W' kalau adaBahanPenolong (setelah U=Solasi, V=Biaya Bahan Penolong)
        // - 'U' kalau tidak (langsung setelah T=Harga VOP)
        $totalPerM3Col = $this->adaBahanPenolong ? 'W' : 'U';

        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(4)->setRowHeight(18);

        $sheet->getStyle("A1:{$lastCol}4")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A1:{$lastCol}4")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // MERGING HEADERS
        $sheet->mergeCells("A1:{$lastCol}1"); // ! NAMA LAHAN
        $sheet->mergeCells('A4:B4'); // ! TOTAL
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

        if ($this->adaBahanPenolong) {
            $sheet->mergeCells('U2:U3'); // Solasi
            $sheet->mergeCells('V2:V3'); // Biaya Bahan Penolong
        }

        // Merge header kolom "Harga Total / m³" (selalu ada)
        $sheet->mergeCells("{$totalPerM3Col}2:{$totalPerM3Col}3");

        // HEADER STYLE
        $sheet->getStyle("A1:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // BARIS TOTAL (Baris 3)
        $sheet->getStyle('A4:L4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("M4:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF88BA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // COLUMN COLORS (Sesuai Gambar)
        $sheet->getStyle('F5:G'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $sheet->getStyle('L5:L'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $sheet->getStyle('N5:N'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $sheet->getStyle('O5:O'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
        $sheet->getStyle('R5:R'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000');
        $sheet->getStyle('S5:S'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BDD7EE');
        $sheet->getStyle('T5:T'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        if ($this->adaBahanPenolong) {
            $sheet->getStyle('V5:V'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6E0B4');
        }

        // Warna kuning terang (senada kolom VOP) untuk kolom ringkasan akhir
        // "Harga Total / m³" yang selalu ada.
        $sheet->getStyle("{$totalPerM3Col}5:{$totalPerM3Col}{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');

        // ! TOTAL
        $sheet->getStyle('C4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('E4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('H4:K4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('P4:Q4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle('S4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        if ($this->adaBahanPenolong) {
            $sheet->getStyle('U4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        }

        // ALIGNMENT & BORDERS
        $sheet->getStyle("A5:{$lastCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A5:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle('A5:F'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M5:N'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('P5:P'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if ($this->adaBahanPenolong) {
            $sheet->getStyle('U5:V'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // FORMAT ANGKA
        $sheet->getStyle('F4:F'.$lastRow)->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle('L4:L'.$lastRow)->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle('D4:D'.$lastRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G4:G'.$lastRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');
        $sheet->getStyle('O4:O'.$lastRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');
        $sheet->getStyle('Q4:T'.$lastRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0_);_("Rp"* (#,##0);_("Rp"* "-"_);_(@_)');

        if ($this->adaBahanPenolong) {
            // Format "Rp .../m3" untuk kolom Biaya Bahan Penolong (per kubikasi, bukan total).
            $sheet->getStyle('V4:V'.$lastRow)->getNumberFormat()->setFormatCode('_("Rp"* #,##0.00_)"/m³";_("Rp"* (#,##0.00)"/m³";_("Rp"* "-"_);_(@_)');
        }

        // Format "Rp .../m3" untuk kolom "Harga Total / m³" (rate per m³).
        $sheet->getStyle("{$totalPerM3Col}4:{$totalPerM3Col}{$lastRow}")->getNumberFormat()->setFormatCode('_("Rp"* #,##0.00_)"/m³";_("Rp"* (#,##0.00)"/m³";_("Rp"* "-"_);_(@_)');

        // Kolom yang di-merge per batch. 'A' (Tanggal) ditambahkan agar tanggal
        // ikut digabung menjadi satu sel per batch (menampilkan tanggal terakhir
        // dari outflow batch tersebut, yang sudah diset di method array()).
        $mergeRow = ['A', 'C', 'D', 'E', 'F', 'G', 'N', 'O', 'R', 'T'];
        // Kolom "Harga Total / m³" juga hanya diisi di baris pertama batch,
        // jadi harus ikut di-merge per batch juga.
        $mergeRow[] = $totalPerM3Col;
        foreach ($this->mergeBatches as $batch) {
            foreach ($mergeRow as $column) {
                $sheet->mergeCells("{$column}{$batch['start']}:{$column}{$batch['end']}");
                $sheet->getStyle("{$column}{$batch['start']}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $theEnd = $batch['end'] + 1;
            $sheet->getStyle("A{$theEnd}:{$lastCol}{$theEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        }

        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12,
            'B' => 7,
            'C' => 8,
            'D' => 9,
            'E' => 8,
            'F' => 12,
            'G' => 18,
            'H' => 10,
            'I' => 8,
            'J' => 8,
            'K' => 10,
            'L' => 12,
            'M' => 14,
            'N' => 9,
            'O' => 18,
            'P' => 12,
            'Q' => 16,
            'R' => 20,
            'S' => 12,
            'T' => 20,
        ];

        if ($this->adaBahanPenolong) {
            $widths['U'] = 22; // Solasi (nominal langsung, Opsi B)
            $widths['V'] = 20; // Biaya Bahan Penolong (Rp / m³)
            $widths['W'] = 20; // Harga Total / m³ (selalu ada, sekarang di posisi W)
        } else {
            $widths['U'] = 20; // Harga Total / m³ (selalu ada, posisi U kalau tanpa bahan penolong)
        }

        return $widths;
    }

    public function title(): string
    {
        return "Kayu {$this->activeSheet}";
    }
}
