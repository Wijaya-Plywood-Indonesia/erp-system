<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================================================
//  MAIN EXPORT — membungkus 2 sheet
// ============================================================
class LaporanPotAfalanJoinExport implements WithMultipleSheets
{
    protected array $laporan;

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotAfalanSheetPekerja($this->laporan),
            new LaporanPotAfalanSheetHasil($this->laporan),
        ];
    }
}

// ============================================================
//  SHEET 1 — "Data Pekerja" (format lama, tidak berubah)
// ============================================================
class LaporanPotAfalanSheetPekerja implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected array $laporan;

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    public function collection(): Collection
    {
        $rows = collect();

        // Group per nomor_meja + kode_ukuran (sama seperti export lama)
        $grouped = collect($this->laporan)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);

        foreach ($grouped as $items) {
            $first       = $items->first();
            $nomorMeja   = $first['nomor_meja']               ?? '-';
            $ukuran      = $first['ukuran']                   ?? '-';
            $jenisBarang = $first['jenis_barang']
                ?? ($first['jenis_kayu']                      ?? '-');
            $kw          = $first['kw']                       ?? '-';
            $tanggal     = $first['tanggal']                  ?? '-';
            $target      = (int) ($first['target']            ?? 0);
            $hasil       = (int) ($first['hasil']             ?? 0);
            $selisih     = (int) ($first['selisih']           ?? 0);
            $pekerja     = $first['pekerja']                  ?? [];

            // Header blok
            $rows->push(['MEJA / AREA POTONG AFALAN', $nomorMeja]);
            $rows->push(['UKURAN',                    $ukuran]);
            $rows->push(['JENIS KAYU/BARANG',         $jenisBarang]);
            $rows->push(['GRADE / KW',                $kw]);
            $rows->push(['TANGGAL PRODUKSI',          $tanggal]);
            $rows->push([]);

            // Header tabel
            $rows->push([
                'ID PEGAWAI', 'Nama Lengkap', 'Jam Masuk', 'Jam Pulang',
                'Ijin', 'Potongan Target', 'Keterangan',
                '', 'Target Harian', 'Hasil Produksi', 'Selisih',
            ]);

            // Data pekerja
            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);
                $rows->push([
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $p['jam_masuk']  ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin']       ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    $target,
                    $hasil,
                    $selisih >= 0 ? '+' . $selisih : $selisih,
                ]);
            }

            // Total blok
            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL', count($pekerja) . ' Orang', '', '', '',
                $totalPotongan > 0 ? $totalPotongan : '-',
                '', '', $target, $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string   { return 'Data Pekerja'; }
}

// ============================================================
//  SHEET 2 — "Hasil Pot Af" (bergaya dryer: judul biru, merge cell)
// ============================================================
class LaporanPotAfalanSheetHasil implements FromArray, WithTitle, WithStyles
{
    protected array $laporan;
    protected array $styleMap    = [];
    protected array $mergeRanges = [];

    public function __construct(array $laporan)
    {
        $this->laporan = $laporan;
    }

    public function array(): array
    {
        $rows     = [];
        $rowIndex = 1;

        if (empty($this->laporan)) {
            $rows[] = ['Tidak ada data untuk tanggal ini.'];
            return $rows;
        }

        // Group per nomor_meja + kode_ukuran
        $grouped = collect($this->laporan)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);

        foreach ($grouped as $items) {
            $first       = $items->first();
            $nomorMeja   = $first['nomor_meja']   ?? '-';
            $tanggal     = $first['tanggal']       ?? '-';
            $pekerja     = $first['pekerja']        ?? [];
            $totalPekerja = count($pekerja);

            // Kumpulkan semua detail hasil dari semua item dalam group
            $semuaDetail = [];
            foreach ($items as $item) {
                $detailHasil = $item['detail_hasil'] ?? [];
                foreach ($detailHasil as $dh) {
                    $semuaDetail[] = $dh;
                }
            }

            // ── JUDUL SEKSI ──────────────────────────────────────
            // Contoh: "MEJA 1 - POTONG AFALAN JOIN"
            $judulSeksi = 'MEJA ' . $nomorMeja . ' - POTONG AFALAN JOIN';
            $rows[] = [$judulSeksi, '', '', '', '', '', '', '', '', '', ''];
            $this->styleMap[$rowIndex] = 'section_title';
            $rowIndex++;

            // ── HEADER KOLOM ─────────────────────────────────────
            $rows[] = ['Tanggal', 'p', 'l', 't', 'jenis', 'kw1', 'kw2', 'kw3', 'kw4', 'byk', 'TTL PKJ'];
            $this->styleMap[$rowIndex] = 'col_header';
            $rowIndex++;

            // ── DATA ROWS ────────────────────────────────────────
            $dataStartRow = $rowIndex;

            if (empty($semuaDetail)) {
                // Jika tidak ada detail_hasil, coba bangun dari data laporan langsung
                // (fallback: satu baris per item berdasarkan ukuran & kw)
                $fallbackRows = [];
                foreach ($items as $item) {
                    $fallbackRows[] = [
                        'panjang'    => $item['panjang']    ?? '-',
                        'lebar'      => $item['lebar']      ?? '-',
                        'tebal'      => $item['tebal']      ?? '-',
                        'jenis_kayu' => $item['jenis_barang'] ?? ($item['jenis_kayu'] ?? '-'),
                        'kw1'        => strtolower($item['kw'] ?? '') === 'kw1' ? ($item['hasil'] ?? '') : '',
                        'kw2'        => strtolower($item['kw'] ?? '') === 'kw2' ? ($item['hasil'] ?? '') : '',
                        'kw3'        => strtolower($item['kw'] ?? '') === 'kw3' ? ($item['hasil'] ?? '') : '',
                        'kw4'        => strtolower($item['kw'] ?? '') === 'kw4' ? ($item['hasil'] ?? '') : '',
                        'total'      => $item['hasil'] ?? '',
                    ];
                }

                foreach ($fallbackRows as $i => $detail) {
                    $rows[] = [
                        $i === 0 ? $tanggal      : '',
                        $detail['panjang'],
                        $detail['lebar'],
                        $detail['tebal'],
                        $detail['jenis_kayu'],
                        $detail['kw1'],
                        $detail['kw2'],
                        $detail['kw3'],
                        $detail['kw4'],
                        $detail['total'],
                        $i === 0 ? $totalPekerja : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;
                if (count($fallbackRows) > 1) {
                    $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
                    $this->mergeRanges[] = "K{$dataStartRow}:K{$dataEndRow}";
                }

            } else {
                foreach ($semuaDetail as $i => $detail) {
                    $rows[] = [
                        $i === 0 ? $tanggal      : '',
                        $detail['panjang']    ?? '-',
                        $detail['lebar']      ?? '-',
                        $detail['tebal']      ?? '-',
                        $detail['jenis_kayu'] ?? '-',
                        $detail['kw1']        ?? '',
                        $detail['kw2']        ?? '',
                        $detail['kw3']        ?? '',
                        $detail['kw4']        ?? '',
                        $detail['total']      ?? '',
                        $i === 0 ? $totalPekerja : '',
                    ];
                    $this->styleMap[$rowIndex] = 'data';
                    $rowIndex++;
                }

                $dataEndRow = $rowIndex - 1;
                if (count($semuaDetail) > 1) {
                    $this->mergeRanges[] = "A{$dataStartRow}:A{$dataEndRow}";
                    $this->mergeRanges[] = "K{$dataStartRow}:K{$dataEndRow}";
                }
            }

            // ── BARIS KOSONG PEMISAH ─────────────────────────────
            $rows[] = ['', '', '', '', '', '', '', '', '', '', ''];
            $rowIndex++;
        }

        return $rows;
    }

    public function title(): string { return 'Hasil Pot Af'; }

    public function styles(Worksheet $sheet)
    {
        $blueDark  = '1F497D';
        $blueLight = '2E75B6';

        // ── MERGE CELL (Tanggal & TTL PKJ) ───────────────────────
        foreach ($this->mergeRanges as $range) {
            $sheet->mergeCells($range);
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        // ── STYLE PER BARIS ───────────────────────────────────────
        foreach ($this->styleMap as $rowNum => $type) {
            switch ($type) {

                case 'section_title':
                    $sheet->mergeCells("A{$rowNum}:K{$rowNum}");
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'size'  => 12,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueDark],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(22);
                    break;

                case 'col_header':
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $blueLight],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'FFFFFF'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($rowNum)->setRowHeight(18);
                    break;

                case 'data':
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'BFBFBF'],
                            ],
                        ],
                    ]);
                    $sheet->getStyle("A{$rowNum}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    break;
            }
        }

        // ── LEBAR KOLOM ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(14); // Tanggal
        $sheet->getColumnDimension('B')->setWidth(8);  // p
        $sheet->getColumnDimension('C')->setWidth(8);  // l
        $sheet->getColumnDimension('D')->setWidth(8);  // t
        $sheet->getColumnDimension('E')->setWidth(12); // jenis
        $sheet->getColumnDimension('F')->setWidth(8);  // kw1
        $sheet->getColumnDimension('G')->setWidth(8);  // kw2
        $sheet->getColumnDimension('H')->setWidth(8);  // kw3
        $sheet->getColumnDimension('I')->setWidth(8);  // kw4
        $sheet->getColumnDimension('J')->setWidth(8);  // byk
        $sheet->getColumnDimension('K')->setWidth(10); // TTL PKJ

        return [];
    }
}