<?php

namespace App\Exports;

use App\Models\ProduksiRotary;
use App\Models\DetailHasilPaletRotary;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use App\Models\DetailTurusanKayu;

class LaporanProduksiExport implements WithMultipleSheets
{
    protected $dataProduksi;
    protected $tanggal;

    public function __construct($dataProduksi, $tanggal = null)
    {
        $this->dataProduksi = $dataProduksi;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        $data = $this->dataProduksi;
        if (empty($data) && $this->tanggal) {
            $raw = \App\Filament\Pages\LaporanProduksi\Queries\LoadProduksi::run($this->tanggal);
            $data = \App\Filament\Pages\LaporanProduksi\Transformers\ProduksiDataMap::make($raw);
        }
        return [
            new LaporanProduksiDetailSheet($data),
            new LaporanProduksiRekapSheet($this->tanggal),
            new LaporanProduksiJurnalSheet($this->tanggal),
        ];
    }
}

class LaporanProduksiDetailSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    protected $dataProduksi;
    protected $mergeRanges = [];
    protected $tableRanges = [];

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = collect($dataProduksi)->groupBy('mesin');
    }

    public function collection()
    {
        $allRows = [];
        $this->mergeRanges = [];
        $this->tableRanges = [];

        foreach ($this->dataProduksi as $mesinNama => $produksiList) {
            $first = $produksiList->first();
            $pekerja = $first['pekerja'] ?? [];
            $daftarKendala = $first['daftar_kendala'] ?? [];
            $tanggal = $first['tanggal'] ?? '';
            $target = $first['target'] ?? 0;
            $jamKerja = $first['jam_kerja'] ?? 0;
            $targetPerJam = $first['target_per_jam'] ?? 0;
            $hasil = $first['hasil'] ?? 0;
            $selisih = $first['selisih'] ?? 0;
            $totalDowntimeMenit = $first['total_downtime_menit'] ?? 0;

            $allRows[] = ['MESIN: ' . strtoupper($mesinNama)];
            $allRows[] = ['TANGGAL: ' . $tanggal];
            $allRows[] = array_fill(0, 11, '');
            
            $headerRow = count($allRows) + 1;
            $allRows[] = ['ID', 'Nama', 'Potongan Gaji', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala'];

            $workerStartRow = count($allRows) + 1;
            $N = count($pekerja);
            $workerEndRow = $workerStartRow + $N - 1;
            $totalRow = $workerStartRow + $N;

            // Pre-calculate cell values for Kendala column
            $kendalaCellValues = array_fill(0, $N, '');

            if ($N > 0) {
                if (count($daftarKendala) === 0) {
                    $kendalaCellValues[0] = 'Tidak ada kendala';
                    if ($N > 1) {
                        $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
                    }
                } else {
                    $M = count($daftarKendala);
                    
                    if ($N < $M) {
                        // Jika jumlah pekerja lebih sedikit dari kendala, gabungkan semua kendala dengan newline
                        $text = implode("\n", array_column($daftarKendala, 'text'));
                        $kendalaCellValues[0] = $text;
                        if ($N > 1) {
                            $this->mergeRanges[] = "K{$workerStartRow}:K{$workerEndRow}";
                        }
                    } else {
                        // Jika pekerja cukup, bagi rata secara chunk
                        $chunkSize = (int) ceil($N / $M);

                        for ($i = 0; $i < $M; $i++) {
                            $startIdx = $i * $chunkSize;
                            $endIdx = min(($i + 1) * $chunkSize - 1, $N - 1);

                            if ($startIdx < $N) {
                                $kendalaCellValues[$startIdx] = $daftarKendala[$i]['text'] ?? '';
                                $chunkStartRow = $workerStartRow + $startIdx;
                                $chunkEndRow = $workerStartRow + $endIdx;

                                if ($chunkStartRow < $chunkEndRow) {
                                    $this->mergeRanges[] = "K{$chunkStartRow}:K{$chunkEndRow}";
                                }
                            }
                        }
                    }
                }
            }

            foreach ($pekerja as $idx => $p) {
                $potTargetRaw = (float) str_replace('.', '', $p['pot_target'] ?? '0');
                $allRows[] = [
                    $p['id'] ?? '-', $p['nama'] ?? '-', $potTargetRaw > 0 ? (int) $potTargetRaw : 0, $p['keterangan'] ?? '-', '', 
                    (int) $target, (int) $jamKerja, round((float) $targetPerJam, 2), (int) $hasil, (int) $selisih,
                    $kendalaCellValues[$idx]
                ];
            }

            $allRows[] = [
                'TOTAL', 
                $N . ' pekerja', 
                $N > 0 ? "=SUM(C{$workerStartRow}:C{$workerEndRow})" : 0, 
                '', 
                '', 
                $N > 0 ? "=SUM(F{$workerStartRow}:F{$workerEndRow})" : 0, 
                (int) $jamKerja, 
                $N > 0 ? "=SUM(H{$workerStartRow}:H{$workerEndRow})" : 0, 
                $N > 0 ? "=SUM(I{$workerStartRow}:I{$workerEndRow})" : 0, 
                $N > 0 ? "=SUM(J{$workerStartRow}:J{$workerEndRow})" : 0, 
                $totalDowntimeMenit > 0 ? $totalDowntimeMenit . ' menit' : ''
            ];
            $allRows[] = array_fill(0, 11, '');
            $allRows[] = array_fill(0, 11, '');

            // Record the table range for borders and styling
            $this->tableRanges[] = [
                'header' => $headerRow,
                'start'  => $workerStartRow,
                'end'    => $workerEndRow,
                'total'  => $totalRow
            ];
        }
        return collect($allRows);
    }

    public function headings(): array { return []; }
    public function title(): string { return 'Detail Per Mesin'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set explicit column widths
                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(20);
                $sheet->getColumnDimension('E')->setWidth(5);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);
                $sheet->getColumnDimension('J')->setWidth(12);
                $sheet->getColumnDimension('K')->setWidth(45);

                // Merge cells dynamically
                foreach ($this->mergeRanges as $range) {
                    $sheet->mergeCells($range);
                }

                // Apply styles, borders, alignments and colors for each table
                foreach ($this->tableRanges as $range) {
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    // 1. Grid borders for the entire table (A{header} to K{total})
                    $sheet->getStyle("A{$headerRow}:K{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ]
                        ]
                    ]);

                    // 2. Header row style
                    $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0']
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ]
                    ]);

                    // 3. Total row style
                    $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9']
                        ]
                    ]);

                    // 4. Alignments for worker data cells (A{start} to K{end})
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        
                        $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("G{$startRow}:G{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("I{$startRow}:I{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("J{$startRow}:J{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("K{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                        
                        // Number formats
                        $sheet->getStyle("C{$startRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
                        $sheet->getStyle("F{$startRow}:F{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("I{$startRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Enable wrap text and top vertical alignment for Kendala (K)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("K1:K{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            }
        ];
    }
}

class LaporanProduksiRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;
    protected $mergeData = [];
    protected $uniqueKws = ['1', '2', '3', '4'];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
        $this->determineKws();
    }

    private function determineKws()
    {
        $kws = DetailHasilPaletRotary::whereHas('produksi', function($q) { $q->whereDate('tgl_produksi', $this->tanggal); })->distinct()->pluck('kw')->toArray();
        foreach ($kws as $kw) { $kwStr = (string)$kw; if ($kwStr !== '' && !in_array($kwStr, $this->uniqueKws)) $this->uniqueKws[] = $kwStr; }
        sort($this->uniqueKws);
    }

    public function collection()
    {
        $produksis = ProduksiRotary::with(['mesin', 'detailPaletRotary.ukuran', 'detailPaletRotary.penggunaanLahan.jenisKayu', 'detailPegawaiRotary'])->whereDate('tgl_produksi', $this->tanggal)->get();
        $rows = collect(); $currentRow = 2; $this->mergeData = [];
        foreach ($produksis as $index => $produksi) {
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '-');
            $tgl = Carbon::parse($produksi->tgl_produksi)->format('d/m/Y');
            $details = $produksi->detailPaletRotary->groupBy(function($item) {
                $u = $item->ukuran; $j = $item->penggunaanLahan->jenisKayu->kode_kayu ?? '-';
                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $j;
            });
            if ($details->count() > 0) {
                $this->mergeData[] = ['start' => $currentRow, 'end' => $currentRow + $details->count() - 1];
                foreach ($details as $key => $items) {
                    [$p, $l, $t, $jenis] = explode('|', $key);
                    $rowData = ['mesin' => $namaMesin, 'tanggal' => $tgl, 'p' => str_replace('.', ',', (string)(float)$p), 'l' => str_replace('.', ',', (string)(float)$l), 't' => str_replace('.', ',', (string)(float)$t), 'jenis' => $jenis];
                    foreach ($this->uniqueKws as $kwLabel) { $rowData['kw_' . $kwLabel] = $items->filter(fn($i) => (string)$i->kw === (string)$kwLabel)->sum('total_lembar') ?: ''; }
                    $rowData['ttl_pkj'] = $produksi->detailPegawaiRotary->count();
                    $rows->push($rowData); $currentRow++;
                }
                if ($index < $produksis->count() - 1) { $rows->push(array_fill(0, count($this->headings()), '')); $currentRow++; }
            }
        }
        return $rows;
    }

    public function headings(): array
    {
        $headers = ['Mesin', 'Tanggal', 'p', 'l', 't', 'jenis'];
        foreach ($this->uniqueKws as $kwLabel) $headers[] = 'kw' . $kwLabel;
        $headers[] = 'TTL PKJ';
        return $headers;
    }

    public function styles(Worksheet $sheet) { return [1 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate(); $peachColor = 'F9CB9C';
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'font' => ['bold' => true]]);
                foreach ($this->mergeData as $range) {
                    $start = $range['start']; $end = $range['end'];
                    if ($start < $end) { $sheet->mergeCells("A{$start}:A{$end}"); $sheet->mergeCells("{$lastCol}{$start}:{$lastCol}{$end}"); }
                    $sheet->getStyle("A{$start}:{$lastCol}{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
                    $sheet->getStyle("A{$start}:B{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("F{$start}:{$lastCol}{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("A{$start}:B{$end}")->getFont()->setBold(true);
                    $sheet->getStyle("{$lastCol}{$start}:{$lastCol}{$end}")->getFont()->setBold(true);
                }
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getColumnDimension('A')->setWidth(18); $sheet->getColumnDimension('B')->setWidth(15); $sheet->getColumnDimension('C')->setWidth(8); $sheet->getColumnDimension('D')->setWidth(8); $sheet->getColumnDimension('E')->setWidth(8);
                for ($i = 6; $i < count($this->headings()); $i++) { $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i); $sheet->getColumnDimension($col)->setWidth(10); }
                $sheet->getColumnDimension($lastCol)->setWidth(12);
            },
        ];
    }
    public function title(): string { return 'Rekap Produksi Custom'; }
}

class LaporanProduksiJurnalSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $rows = collect();
        $service = new \App\Services\Akuntansi\RotaryJurnalService();
        $payload = $service->buildJurnalPayloadPreview($this->tanggal);

        if (!$payload || empty($payload['jurnal_items'])) {
            $rows->push(['Tidak ada data jurnal produksi untuk tanggal ini.']);
            return $rows;
        }

        $rawRows = [];

        // Preload mesin dan jenis kayu untuk pencarian dinamis
        $mesins = \App\Models\Mesin::all()->keyBy(fn($m) => strtoupper(trim($m->nama_mesin)));
        $jenisKayus = \App\Models\JenisKayu::all()->keyBy(fn($jk) => strtoupper(trim($jk->nama_kayu)));

        // Preload harga veneer kering
        $hargaVeneerMap = [];
        $referensiHargaKering = \App\Models\ReferensiHargaProduksi::where('jenis_barang', 'Veneer Kering')->get();
        foreach ($referensiHargaKering as $rhp) {
            $ukKey = strtolower(trim($rhp->kw ?? ''));
            if (str_starts_with($ukKey, 'kw 1 - ')) {
                $ukKey = substr($ukKey, 7);
            }
            $ukKey = str_replace(' ', '_', $ukKey);
            $hargaVeneerMap[$rhp->id_jenis_kayu][$ukKey] = (float) $rhp->harga;
        }

        foreach ($payload['jurnal_items'] as $item) {
            $namaAkun = $item['nama_akun'];
            $noAkun   = $item['no_akun'];
            $mapDK    = $item['map'];

            // Skip Upah Tenaga Kerja (510-01) — struktur baru tidak memunculkan sisi debit upah
            if ($noAkun === '510-01') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $bagian = '-';
                $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';

                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $bagian = $subItem['nama_pihak'] ?? '-';
                    if (($subItem['nama_barang'] ?? '') !== 'Mesin' && ($subItem['nama_barang'] ?? '') !== '-') {
                        $keteranganSpesifikasi = $subItem['nama_barang'] ?? '-';
                    } else {
                        $keteranganSpesifikasi = ($subItem['keterangan'] ?? '') . ' (' . ($subItem['ukuran'] ?? '') . ')';
                    }
                } elseif (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';
                    $keteranganSpesifikasi = ''; // Blank as requested to group workers under the same machine
                } elseif (($subItem['jenis_pihak'] ?? '') === 'pemasok') {
                    // Skip Persediaan Kayu — fokus hanya data produksi
                    continue;
                } else {
                    $bagian = '-';
                    $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';
                }

                $tipe = 'b';
                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $tipe = 'm';
                }

                $banyak = $subItem['banyak'];
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $banyak = 1; // set to 1 so we can sum the total workers
                }

                $volume = $subItem['m3'];
                $harga  = $subItem['harga'];
                $jumlah = $subItem['jumlah'];

                // Khusus export Excel: harga veneer mengikuti harga veneer kering dari tabel harga_veneers
                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $namaM = strtoupper(trim($bagian));
                    $jenisHasil = isset($mesins[$namaM]) ? $mesins[$namaM]->jenis_hasil : 'core';

                    // Parse jenis kayu dari keterangan
                    $keterangan = $subItem['keterangan'] ?? '';
                    $parts = explode(' - ', $keterangan);
                    $namaKayu = count($parts) > 2 ? trim($parts[2]) : '';
                    $namaKayuUpper = strtoupper(trim($namaKayu));
                    $jenisKayuObj = $jenisKayus[$namaKayuUpper] ?? null;
                    $idJenisKayu = $jenisKayuObj ? $jenisKayuObj->id : null;

                    $ongkos = 0;
                    if ($idJenisKayu) {
                        $options = strtolower($jenisHasil) === 'f/b' ? ['faceback', 'face', 'back'] : ['core'];
                        foreach ($options as $opt) {
                            if (isset($hargaVeneerMap[$idJenisKayu][$opt])) {
                                $ongkos = $hargaVeneerMap[$idJenisKayu][$opt];
                                break;
                            }
                        }
                    }

                    // Fallback to legacy if no match found
                    if ($ongkos === 0) {
                        $ongkos = isset($mesins[$namaM]) ? (float)($mesins[$namaM]->ongkos_mesin ?? 0) : 0;
                    }

                    $harga  = $ongkos;
                    $jumlah = $volume !== null ? round((float)$volume * $ongkos, 4) : null;
                }

                // Khusus export Excel: harga pekerja di-hardcode 150.000
                // (tidak ambil dari database HargaPegawai)
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $harga  = 150_000;
                    $jumlah = 150_000; // 1 orang × 150.000
                }

                // Map original accounts (115-07, 115-08, 210-02) based on wood type (Sengon vs Meranti)
                $mappedNoAkun = $noAkun;
                $mappedNamaAkun = $namaAkun;

                if ($noAkun === '115-07' || $noAkun === '115-08') {
                    $isWHN = false;
                    if (request()) {
                        $host = request()->getHost();
                        if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                            $isWHN = true;
                        }
                    }

                    $isSengon = (stripos($subItem['keterangan'] ?? '', 'sengon') !== false);
                    if ($noAkun === '115-07') {
                        // Veneer Basah F/B
                        if ($isWHN) {
                            $mappedNoAkun = $isSengon ? '1421.01' : '1422.01';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 260 face/back sengon WHN' : 'Veneer Basah 260 face/back meranti WHN';
                        } else {
                            $mappedNoAkun = $isSengon ? '1421.00' : '1422.00';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 260 face/back sengon WJY' : 'Veneer Basah 260 face/back meranti WJY';
                        }
                    } else {
                        // Veneer Basah CORE
                        if ($isWHN) {
                            $mappedNoAkun = $isSengon ? '1426.01' : '1427.01';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 130 core sengon WHN' : 'Veneer Basah 130 core meranti WHN';
                        } else {
                            $mappedNoAkun = $isSengon ? '1426.00' : '1427.00';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 130 core sengon WJY' : 'Veneer Basah 130 core meranti WJY';
                        }
                    }
                } elseif ($noAkun === '210-02') {
                    // Hutang Gaji
                    $mappedNoAkun = '2231.00';
                    $mappedNamaAkun = 'Hutang Gaji';
                }

                $rawRows[] = [
                    'nama_akun'  => $mappedNamaAkun,
                    'no_akun'    => $mappedNoAkun,
                    'bagian'     => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk'         => $mapDK,
                    'tipe'       => $tipe,
                    'banyak'     => $banyak !== null ? (float)$banyak : null,
                    'volume'     => $volume !== null ? (float)$volume : null,
                    'harga'      => $harga !== null ? (float)$harga : null,
                    'jumlah'     => $jumlah !== null ? (float)$jumlah : null,
                ];
            }
        }

        // Group raw rows by machine (bagian)
        $rawRowsByMachine = [];
        foreach ($rawRows as $row) {
            $machine = $row['bagian'];
            if ($machine === '-') {
                continue;
            }
            $rawRowsByMachine[$machine][] = $row;
        }

        $machineTables = [];
        foreach ($rawRowsByMachine as $machine => $rowsOfMachine) {
            $grouped = [];
            $totalDebit = 0.0;
            $totalKredit = 0.0;

            foreach ($rowsOfMachine as $row) {
                $key = implode('|', [
                    $row['no_akun'],
                    $row['keterangan'],
                    $row['dk'],
                    $row['tipe'],
                    $row['nama_akun']
                ]);

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'nama_akun'  => $row['nama_akun'],
                        'no_akun'    => $row['no_akun'],
                        'bagian'     => $machine,
                        'keterangan' => $row['keterangan'],
                        'dk'         => $row['dk'],
                        'tipe'       => $row['tipe'],
                        'banyak'     => 0.0,
                        'volume'     => 0.0,
                        'harga'      => $row['harga'],
                        'jumlah'     => 0.0,
                        'has_qty'    => $row['banyak'] !== null,
                        'has_vol'    => $row['volume'] !== null,
                    ];
                }

                if ($row['banyak'] !== null) {
                    $grouped[$key]['banyak'] += $row['banyak'];
                    $grouped[$key]['has_qty'] = true;
                }
                if ($row['volume'] !== null) {
                    $grouped[$key]['volume'] += $row['volume'];
                    $grouped[$key]['has_vol'] = true;
                }
                if ($row['jumlah'] !== null) {
                    $grouped[$key]['jumlah'] += $row['jumlah'];
                }
            }

            foreach ($grouped as $g) {
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1421.01', '1422.00', '1422.01', '1426.00', '1426.01', '1427.00', '1427.01']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isWood = in_array($g['no_akun'], [
                    '115-01', '115-02', 
                    '1411.01', '1411.02', '1411.03', '1411.04', '1411.05', '1411.06', '1411.07', '1411.08',
                    '1413.01', '1413.03', '1413.04', '1413.05', '1413.06', '1413.07', '1413.08', '1413.09', '1413.10', '1413.11', '1413.12', '1413.13', '1414.00'
                ]);

                $rowHarga = 0.0;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    
                    $dbHarga = $this->getHargaVeneerBasahDb($noAkunVal, $namaAkunVal);
                    if ($dbHarga > 0) {
                        $rowHarga = $dbHarga;
                    } else {
                        if ($noAkunVal === '1421.00' || $noAkunVal === '1421.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                            $rowHarga = 2700000.0;
                        } elseif ($noAkunVal === '1422.00' || $noAkunVal === '1422.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                            $rowHarga = 8000000.0;
                        } elseif ($noAkunVal === '1426.00' || $noAkunVal === '1426.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                            $rowHarga = 1700000.0;
                        } elseif ($noAkunVal === '1427.00' || $noAkunVal === '1427.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                            $rowHarga = 2100000.0;
                        } else {
                            if (str_contains($namaAkunVal, 'core')) {
                                $rowHarga = str_contains($namaAkunVal, 'sengon') ? 1700000.0 : 2100000.0;
                            } else {
                                $rowHarga = str_contains($namaAkunVal, 'sengon') ? 2700000.0 : 8000000.0;
                            }
                        }
                    }
                } elseif ($isHutangGaji) {
                    $rowHarga = 150000.0;
                } elseif ($isWood) {
                    $rowHarga = (float)($g['harga'] ?? 0.0);
                } else {
                    $rowHarga = (float)($g['jumlah'] ?? 0.0);
                }

                $rowTotal = 0.0;
                if ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $rowTotal = round((float)$g['volume'], 4) * $rowHarga;
                } elseif ($g['has_qty'] && $g['banyak'] !== null && $g['banyak'] > 0) {
                    $rowTotal = (float)$g['banyak'] * $rowHarga;
                } else {
                    $rowTotal = $rowHarga;
                }

                if ($g['dk'] === 'd') {
                    $totalDebit += $rowTotal;
                } else {
                    $totalKredit += $rowTotal;
                }
            }

            // Selisih → selalu masuk ke 'hpp triplek' (6111.00) sebagai KREDIT
            $selisih = round($totalDebit - $totalKredit, 2);
            $grouped[] = [
                'nama_akun'  => 'hpp triplek',
                'no_akun'    => '6111.00',
                'bagian'     => $machine,
                'keterangan' => '',
                'dk'         => 'k',
                'tipe'       => 'b',
                'banyak'     => null,
                'volume'     => null,
                'harga'      => null,
                'jumlah'     => $selisih > 0 ? abs($selisih) : 0,
                'has_qty'    => false,
                'has_vol'    => false,
            ];

            $machineTables[$machine] = $grouped;
        }

        $dateStr = \Carbon\Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        foreach ($machineTables as $machine => $groupedRows) {
            // Title Row
            $noJurnal = 'ROT/' . $dateStr . '/' . strtoupper(str_replace(' ', '', $machine));
            $rows->push([
                'No. Jurnal: ' . $noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''
            ]);
            $this->titleRows[] = $currentRow;
            $currentRow++;

            // Header Row
            $rows->push([
                'Nama Akun',
                'tgl',
                'jurnal',
                'No Akun',
                'No',
                'mm',
                'Nama',
                'Keterangan',
                'map',
                'hit kbk',
                'Banyak',
                'M3',
                'Harga',
                'Total'
            ]);
            $this->headerRows[] = $currentRow;
            $currentRow++;

            // Data Rows
            $dataStart = $currentRow;
            $tglVal = \Carbon\Carbon::parse($this->tanggal)->format('d-m-Y');
            foreach ($groupedRows as $g) {
                // Whitelist mapped accounts for formatting logic
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1421.01', '1422.00', '1422.01', '1426.00', '1426.01', '1427.00', '1427.01']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isWood = in_array($g['no_akun'], [
                    '115-01', '115-02', 
                    '1411.01', '1411.02', '1411.03', '1411.04', '1411.05', '1411.06', '1411.07', '1411.08',
                    '1413.01', '1413.03', '1413.04', '1413.05', '1413.06', '1413.07', '1413.08', '1413.09', '1413.10', '1413.11', '1413.12', '1413.13', '1414.00'
                ]);

                // Format `Nama` (Col 7 / G)
                if ($isVeneer) {
                    $namaVal = 'kupasan (m - ' . strtolower($g['bagian']) . ')';
                } else {
                    $namaVal = 'kupasan';
                }

                // Format `hit kbk` (Col 10 / J)
                $hitKbkVal = '';
                if ($isVeneer || $isWood) {
                    $hitKbkVal = 'm';
                } elseif ($isHutangGaji) {
                    $hitKbkVal = 'b';
                }

                // Format `Harga` (Col 13 / M)
                $hargaVal = null;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    
                    $dbHarga = $this->getHargaVeneerBasahDb($noAkunVal, $namaAkunVal);
                    if ($dbHarga > 0) {
                        $hargaVal = $dbHarga;
                    } else {
                        if ($noAkunVal === '1421.00' || $noAkunVal === '1421.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                            $hargaVal = 2700000;
                        } elseif ($noAkunVal === '1422.00' || $noAkunVal === '1422.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                            $hargaVal = 8000000;
                        } elseif ($noAkunVal === '1426.00' || $noAkunVal === '1426.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                            $hargaVal = 1700000;
                        } elseif ($noAkunVal === '1427.00' || $noAkunVal === '1427.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                            $hargaVal = 2100000;
                        } else {
                            if (str_contains($namaAkunVal, 'core')) {
                                $hargaVal = str_contains($namaAkunVal, 'sengon') ? 1700000 : 2100000;
                            } else {
                                $hargaVal = str_contains($namaAkunVal, 'sengon') ? 2700000 : 8000000;
                            }
                        }
                    }
                } elseif ($isHutangGaji) {
                    $hargaVal = 150000;
                } elseif ($isWood) {
                    $hargaVal = $g['harga'];
                } else {
                    $hargaVal = $g['jumlah'];
                }

                // Calculate Total as an Excel formula referencing 'hit kbk' (Col J), Harga (Col M), M3 (Col L), and Banyak (Col K)
                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

                $rows->push([
                    $g['nama_akun'],                                    // 1. Nama Akun
                    $tglVal,                                            // 2. tgl
                    '',                                                 // 3. jurnal
                    $g['no_akun'],                                      // 4. No Akun
                    '',                                                 // 5. No
                    '',                                                 // 6. mm
                    $namaVal,                                           // 7. Nama
                    $g['keterangan'],                                   // 8. Keterangan
                    $g['dk'],                                           // 9. map
                    $hitKbkVal,                                         // 10. hit kbk
                    $g['has_qty'] ? $g['banyak'] : null,                // 11. Banyak
                    $g['has_vol'] ? round($g['volume'], 4) : null,      // 12. M3
                    $hargaVal,                                          // 13. Harga
                    $totalVal                                           // 14. Total
                ]);
                $currentRow++;
            }
            $dataEnd = $currentRow - 1;
            $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

            // 2 Blank separating rows
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $currentRow += 2;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style Title Rows
                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFD2E4F0']
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Header Rows
                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE5E8EB']
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Data Rows
                foreach ($this->dataRanges as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    if ($start > $end) continue;

                    $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);

                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("N{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(25); // Nama Akun
                $sheet->getColumnDimension('B')->setWidth(15); // tgl
                $sheet->getColumnDimension('C')->setWidth(12); // jurnal
                $sheet->getColumnDimension('D')->setWidth(15); // No Akun
                $sheet->getColumnDimension('E')->setWidth(10); // No
                $sheet->getColumnDimension('F')->setWidth(10); // mm
                $sheet->getColumnDimension('G')->setWidth(30); // Nama
                $sheet->getColumnDimension('H')->setWidth(40); // Keterangan
                $sheet->getColumnDimension('I')->setWidth(10); // map
                $sheet->getColumnDimension('J')->setWidth(10); // hit kbk
                $sheet->getColumnDimension('N')->setWidth(18); // Total
            }
        ];
    }

    private function getHargaVeneerBasahDb(string $noAkun, string $namaAkun): float
    {
        $noAkunVal = trim($noAkun);
        $namaAkunVal = strtolower(trim($namaAkun));

        $isSengon = str_contains($namaAkunVal, 'sengon');
        $isMeranti = str_contains($namaAkunVal, 'meranti') || (!str_contains($namaAkunVal, 'sengon') && !str_contains($namaAkunVal, 'jabon') && !str_contains($namaAkunVal, 'mahoni'));
        
        $jns = $isSengon ? 'Sengon' : 'Meranti';
        $jenisKayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
        if (!$jenisKayu) {
            return 0.0;
        }

        // Determine if it's faceback or core
        $isCore = str_contains($namaAkunVal, 'core') 
            || $noAkunVal === '1426.00' 
            || $noAkunVal === '1426.01' 
            || $noAkunVal === '1427.00' 
            || $noAkunVal === '1427.01' 
            || ($noAkunVal === '115-08');

        $ukuranOptions = !$isCore
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ['core'];

        $kwOptions = array_map(function($opt) {
            return 'KW 1 - ' . ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $hargaVeneer = \App\Models\ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->where('jenis_barang', 'Veneer Basah')
            ->whereIn('kw', $kwOptions)
            ->first();

        return (float) ($hargaVeneer->harga ?? 0.0);
    }
}