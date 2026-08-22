<?php

namespace App\Exports;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Exports\Sheets\JurnalKediSheet;
use App\Models\Target;
use App\Services\Target\TargetResolverFactory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanProduksiKediExport implements FromCollection, ShouldAutoSize, WithEvents, WithMultipleSheets, WithStyles, WithTitle
{
    protected Collection $data;

    protected Collection $produksiKediCollection;

    protected array $mergeRanges = []; // Menyimpan koordinat untuk di-merge

    protected int $mainTableEndRow = 0;

    protected int $downtimeStartRow = 0;

    protected int $downtimeSubHeaderRow = 0;

    protected int $downtimeEndRow = 0;

    protected bool $hasDowntime = false;

    /**
     * @param  array  $data  Data array hasil LaporanKedi::loadAllData() (untuk sheet 2 & 3)
     * @param  Collection|array|null  $produksiKediCollection  Eloquent collection ProduksiKedi
     *                                                         dengan relasi detailBongkarKedi.jenisKayu, detailMasukKedi.jenisKayu, detailPegawaiKedi.pegawai
     *                                                         (dipakai untuk sheet 1 - Potongan)
     */
    public function __construct(array $data, $produksiKediCollection = null)
    {
        $this->data = collect($data);
        $this->produksiKediCollection = $produksiKediCollection instanceof Collection
            ? $produksiKediCollection
            : collect($produksiKediCollection ?? []);
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
            'ONGKOS PER LB',
        ];
        $rows->push($subHeader);

        $totals = [
            'm_kw1' => 0, 'm_kw2' => 0, 'm_kw3' => 0, 'm_kw4' => 0, 'm_kwaf' => 0,
            'm_byk' => 0, 'm_m3' => 0, 'm_pkj' => 0,
            'b_kw1' => 0, 'b_kw2' => 0, 'b_kw3' => 0, 'b_kw4' => 0, 'b_kwaf' => 0,
            'b_byk' => 0, 'b_m3' => 0, 'b_pkj' => 0,
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
                    $p = (float) str_replace('mm', '', $d[0] ?? 0);
                    $l = (float) str_replace('mm', '', $d[1] ?? 0);
                    $t = (float) str_replace('mm', '', $d[2] ?? 0);
                    $m3 = ($p * $l * $t * (int) $dm['jumlah']) / 10000000;

                    $row[0] = $produksi['tanggal_masuk'];
                    $row[1] = $dm['mesin'];
                    $row[2] = $p;
                    $row[3] = $l;
                    $row[4] = $t;
                    $row[5] = $this->getJenisKayuShort($dm['jenis_kayu']);

                    $kwVal = (int) ($dm['kw'] ?? 0);
                    $isKw1 = ($kwVal === 1);
                    $isKw2 = ($kwVal === 2);
                    $isKw3 = ($kwVal === 3);
                    $isKw4 = ($kwVal === 4);
                    $isKwAf = (! $isKw1 && ! $isKw2 && ! $isKw3 && ! $isKw4);

                    $row[6] = $isKw1 ? $dm['jumlah'] : '';
                    $row[7] = $isKw2 ? $dm['jumlah'] : '';
                    $row[8] = $isKw3 ? $dm['jumlah'] : '';
                    $row[9] = $isKw4 ? $dm['jumlah'] : '';
                    $row[10] = $isKwAf ? $dm['jumlah'] : '';
                    $row[11] = $dm['jumlah'];
                    $row[12] = round($m3, 4);
                    $row[13] = $produksi['total_pekerja'];

                    if ($isKw1) {
                        $totals['m_kw1'] += $dm['jumlah'];
                    }
                    if ($isKw2) {
                        $totals['m_kw2'] += $dm['jumlah'];
                    }
                    if ($isKw3) {
                        $totals['m_kw3'] += $dm['jumlah'];
                    }
                    if ($isKw4) {
                        $totals['m_kw4'] += $dm['jumlah'];
                    }
                    if ($isKwAf) {
                        $totals['m_kwaf'] += $dm['jumlah'];
                    }

                    $totals['m_byk'] += $dm['jumlah'];
                    $totals['m_m3'] += $m3;
                }

                if (isset($produksi['detail_bongkar'][$i])) {
                    $db = $produksi['detail_bongkar'][$i];
                    $d = explode(' x ', $db['ukuran']);
                    $p = (float) str_replace('mm', '', $d[0] ?? 0);
                    $l = (float) str_replace('mm', '', $d[1] ?? 0);
                    $t = (float) str_replace('mm', '', $d[2] ?? 0);
                    $m3 = ($p * $l * $t * (int) $db['jumlah']) / 10000000;

                    $row[21] = $produksi['tanggal_keluar'];
                    $row[22] = $db['mesin'];
                    $row[23] = $p;
                    $row[24] = $l;
                    $row[25] = $t;
                    $row[26] = $this->getJenisKayuShort($db['jenis_kayu']);

                    $kwVal = (int) ($db['kw'] ?? 0);
                    $isKw1 = ($kwVal === 1);
                    $isKw2 = ($kwVal === 2);
                    $isKw3 = ($kwVal === 3);
                    $isKw4 = ($kwVal === 4);
                    $isKwAf = (! $isKw1 && ! $isKw2 && ! $isKw3 && ! $isKw4);

                    $row[27] = $isKw1 ? $db['jumlah'] : '';
                    $row[28] = $isKw2 ? $db['jumlah'] : '';
                    $row[29] = $isKw3 ? $db['jumlah'] : '';
                    $row[30] = $isKw4 ? $db['jumlah'] : '';
                    $row[31] = $isKwAf ? $db['jumlah'] : '';
                    $row[32] = $db['jumlah'];
                    $row[33] = round($m3, 4);
                    $row[34] = $produksi['total_pekerja'];

                    if ($isKw1) {
                        $totals['b_kw1'] += $db['jumlah'];
                    }
                    if ($isKw2) {
                        $totals['b_kw2'] += $db['jumlah'];
                    }
                    if ($isKw3) {
                        $totals['b_kw3'] += $db['jumlah'];
                    }
                    if ($isKw4) {
                        $totals['b_kw4'] += $db['jumlah'];
                    }
                    if ($isKwAf) {
                        $totals['b_kwaf'] += $db['jumlah'];
                    }

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
            if (! empty($produksi['kendala_kedis'])) {
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
                $downtimeRow[5] = $k['durasi_menit'] ? $k['durasi_menit'].' menit' : '-';
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
                    foreach (['V', 'W', 'AI'] as $col) {
                        $sheet->mergeCells("{$col}{$r['start']}:{$col}{$r['end']}");
                        $sheet->getStyle("{$col}{$r['start']}:{$col}{$r['end']}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    }
                }
            },
        ];
    }

    private function getJenisKayuShort($name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'sengon')) {
            return 's';
        }
        if (str_contains($n, 'meranti')) {
            return 'm';
        }
        if (str_contains($n, 'mahoni')) {
            return 'mh';
        }
        if (str_contains($n, 'jabon')) {
            return 'j';
        }
        if (str_contains($n, 'waru')) {
            return 'wr';
        }

        return $name;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->setCellValue('A1', 'MASUK')->mergeCells('A1:T1');
        $sheet->setCellValue('V1', 'BONGKAR')->mergeCells('V1:AO1');

        $sheet->getStyle('A1:T2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('V1:AO2')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A3:AO3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'font' => ['bold' => true],
        ]);

        $sheet->getStyle('A1:T'.$this->mainTableEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('V1:AO'.$this->mainTableEndRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        if ($this->hasDowntime) {
            $start = $this->downtimeStartRow;
            $subHeader = $this->downtimeSubHeaderRow;
            $end = $this->downtimeEndRow;

            // Merge header judul
            $sheet->mergeCells("A{$start}:G{$start}");
            $sheet->setCellValue("A{$start}", 'DAFTAR DOWNTIME & KENDALA MESIN');

            // Style judul
            $sheet->getStyle("A{$start}:G{$start}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Style sub-header
            $sheet->getStyle("A{$subHeader}:G{$subHeader}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Border untuk tabel downtime
            $sheet->getStyle("A{$subHeader}:G{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Alignment data downtime
            $sheet->getStyle('A'.($subHeader + 1).":A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B'.($subHeader + 1).":B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D'.($subHeader + 1).":E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F'.($subHeader + 1).":F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function title(): string
    {
        return 'Laporan Produksi Kedi';
    }

    // INI FUNGSI UNTUK MENAMPILKAN MULTI-SHEET
    public function sheets(): array
    {
        return [
            new LaporanKediPotonganSheet($this->produksiKediCollection), // Sheet 1: Potongan (BARU)
            $this,                                                        // Sheet 2: Laporan Kedi Asli
            new JurnalKediSheet($this->data->toArray()),                  // Sheet 3: Jurnal Kedi
        ];
    }
}

class LaporanKediPotonganSheet implements FromCollection, WithEvents, WithTitle
{
    protected Collection $produksiKediCollection;

    protected array $tableRanges = [];

    public function __construct(Collection $produksiKediCollection)
    {
        $this->produksiKediCollection = $produksiKediCollection;
    }

    public function collection(): Collection
    {
        $rows = collect();
        $this->tableRanges = [];

        if ($this->produksiKediCollection->isEmpty()) {
            $rows->push(['Tidak ada data potongan untuk tanggal ini.']);

            return $rows;
        }

        $built = $this->buildAggregatedPotongan($this->produksiKediCollection);
        $aggregated = collect($built['rows']);
        $summaries = $built['summary']; // label => ['hasil' => float, 'target' => float|null, 'selisih' => float|null, 'satuan' => string]

        if ($aggregated->isEmpty()) {
            $rows->push(['Tidak ada data potongan untuk tanggal ini.']);

            return $rows;
        }

        $grouped = $aggregated->groupBy('hasil');
        $currentRow = 0;

        foreach ($grouped as $label => $items) {
            // Judul grup
            $rows->push([strtoupper($label)]);
            $currentRow++;

            // Baris ringkasan: Hasil Aktual vs Target vs Selisih
            $sum = $summaries[$label] ?? null;
            $infoRow = $currentRow + 1;
            if ($sum) {
                $satuan = $sum['satuan'] ?? '';
                $targetText = $sum['target'] !== null ? number_format($sum['target'], 0, ',', '.') : '-';
                $selisihText = $sum['selisih'] !== null
                    ? ($sum['selisih'] >= 0 ? '+' : '').number_format($sum['selisih'], 0, ',', '.')
                    : '-';
                $rows->push([
                    'Hasil Aktual: '.number_format($sum['hasil'], 0, ',', '.').' '.$satuan
                        .'   |   Target: '.$targetText.' '.$satuan
                        .'   |   Selisih: '.$selisihText.' '.$satuan,
                ]);
                $currentRow++;

                // Baris kedua: info jam
                $jamNormalText = ($sum['jam_normal'] ?? null) !== null
                    ? number_format($sum['jam_normal'], 1, ',', '.').' jam'
                    : '-';
                $jamAktualTotalText = number_format($sum['jam_aktual_total'] ?? 0, 1, ',', '.').' jam';
                $jamAktualRataText = number_format($sum['jam_aktual_rata'] ?? 0, 1, ',', '.').' jam/orang';
                $rows->push([
                    'Jam Target (normal): '.$jamNormalText
                        .'   |   Jam Aktual Total: '.$jamAktualTotalText
                        .'   |   Rata-rata: '.$jamAktualRataText,
                ]);
                $jamInfoRow = $currentRow + 1;
                $currentRow++;
            } else {
                $rows->push(['']);
                $currentRow++;
                $jamInfoRow = null;
            }

            // Header
            $headerRow = $currentRow + 1;
            $rows->push(['No', 'Kode Pegawai', 'Nama', 'Jam Masuk', 'Jam Pulang', 'Ijin', 'Keterangan', 'Potongan Gaji (Rp)']);
            $currentRow++;

            // Data
            $startRow = $currentRow + 1;
            $no = 1;
            foreach ($items as $item) {
                $rows->push([
                    $no++,
                    $item['kodep'],
                    $item['nama'],
                    $item['masuk'],
                    $item['pulang'],
                    $item['ijin'],
                    $item['keterangan'],
                    (int) $item['potongan_targ'],
                ]);
                $currentRow++;
            }
            $endRow = $currentRow;

            // Total
            $totalRow = $currentRow + 1;
            $rows->push([
                'TOTAL',
                '',
                $items->count().' pekerja',
                '',
                '',
                '',
                '',
                "=SUM(H{$startRow}:H{$endRow})",
            ]);
            $currentRow++;

            $this->tableRanges[] = [
                'info' => $infoRow,
                'jam_info' => $jamInfoRow,
                'header' => $headerRow,
                'start' => $startRow,
                'end' => $endRow,
                'total' => $totalRow,
            ];

            // Baris kosong pemisah antar grup
            $rows->push(array_fill(0, 8, ''));
            $currentRow++;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Potongan';
    }

    /**
     * Gabungkan hasil produksi & jam kerja dari semua sesi ProduksiKedi
     * dalam satu hari untuk (status + jenis kayu) yang sama, lalu hitung
     * target/potongan SEKALI menggunakan total gabungan tersebut.
     *
     * Ini penting karena rumus target/potongan bersifat non-linear
     * (max(0, targetAdjusted - hasilAktual)) — menghitung per sesi lalu
     * menjumlahkan potongannya akan memberi hasil yang berbeda (biasanya
     * lebih besar) dibanding menghitung sekali dari total gabungan.
     */
    /**
     * @return array{rows: array, summary: array<string, array{hasil: float, target: float|null, selisih: float|null, satuan: string}>}
     */
    private function buildAggregatedPotongan(Collection $produksiCollection): array
    {
        // Group HANYA per status (bongkar/masuk) — semua sesi bongkar hari itu,
        // apapun mesin/jenis kayunya, digabung jadi satu perhitungan target/potongan.
        // Jenis kayu tidak lagi jadi pemisah kalkulasi, hanya info label tampilan.
        $groups = $produksiCollection->groupBy(fn ($produksi) => $produksi->status);

        $results = [];
        $summaries = [];

        foreach ($groups as $status => $groupProduksi) {
            // --- A. Gabungkan hasil produksi dari SEMUA sesi (semua mesin/jenis kayu) ---
            $totalHasil = 0;
            $daftarKayu = [];

            foreach ($groupProduksi as $produksi) {
                if ($status === 'bongkar' && $produksi->detailBongkarKedi) {
                    $totalHasil += $produksi->detailBongkarKedi->count();
                    $kayu = $produksi->detailBongkarKedi->first()->jenisKayu->nama_kayu ?? null;
                    if ($kayu) {
                        $daftarKayu[$kayu] = true;
                    }
                } elseif ($status === 'masuk' && $produksi->detailMasukKedi) {
                    $totalHasil += $produksi->detailMasukKedi->sum('jumlah');
                    $kayu = $produksi->detailMasukKedi->first()->jenisKayu->nama_kayu ?? null;
                    if ($kayu) {
                        $daftarKayu[$kayu] = true;
                    }
                }
            }

            $labelDivisi = $status === 'bongkar' ? 'KEDI (BONGKAR)' : 'KEDI (MASUK)';
            if (! empty($daftarKayu)) {
                $labelDivisi .= ' - '.implode(', ', array_keys($daftarKayu));
            }

            // --- B. Gabungkan semua baris pegawai dari semua sesi, LALU DEDUPE per pegawai ---
            // Penting: satu pegawai bisa muncul di lebih dari satu record ProduksiKedi
            // pada tanggal yang sama (misal 2 batch bongkar dalam 1 hari kerja), tapi dia
            // cuma kerja SATU shift hari itu. Kalau tidak di-dedupe, jam kerja & jumlah
            // pekerja akan terhitung dobel, sehingga target ikut naik dobel dan potongan
            // jadi lebih besar dari seharusnya.
            $uniquePegawai = []; // key: kodep => ['pegawai' => ..., 'masuk' => Carbon|null, 'pulang' => Carbon|null, 'ijin' => [], 'ket' => []]

            foreach ($groupProduksi as $produksi) {
                if (! $produksi->detailPegawaiKedi) {
                    continue;
                }

                $tanggalStr = Carbon::parse($produksi->tanggal_actual_bongkar ?? $produksi->tanggal ?? now())->format('Y-m-d');

                foreach ($produksi->detailPegawaiKedi as $dp) {
                    if (! $dp->pegawai) {
                        continue;
                    }

                    $kodep = $dp->pegawai->kode_pegawai ?? '-';

                    $masukAt = null;
                    $pulangAt = null;
                    if (! empty($dp->masuk) && ! empty($dp->pulang)) {
                        $masukAt = Carbon::parse($tanggalStr.' '.$dp->masuk);
                        $pulangAt = Carbon::parse($tanggalStr.' '.$dp->pulang);
                        if ($pulangAt->lessThan($masukAt)) {
                            $pulangAt->addDay();
                        }
                    }

                    if (! isset($uniquePegawai[$kodep])) {
                        $uniquePegawai[$kodep] = [
                            'pegawai' => $dp->pegawai,
                            'masuk' => $masukAt,
                            'pulang' => $pulangAt,
                            'ijin' => [],
                            'ket' => [],
                            'potongan_manual' => null, // jika ada dp->potongan manual
                        ];
                    } else {
                        // Sudah ada dari sesi lain hari yang sama: ambil rentang jam terluas
                        // (masuk paling awal, pulang paling akhir), bukan dijumlah.
                        if ($masukAt && (! $uniquePegawai[$kodep]['masuk'] || $masukAt->lessThan($uniquePegawai[$kodep]['masuk']))) {
                            $uniquePegawai[$kodep]['masuk'] = $masukAt;
                        }
                        if ($pulangAt && (! $uniquePegawai[$kodep]['pulang'] || $pulangAt->greaterThan($uniquePegawai[$kodep]['pulang']))) {
                            $uniquePegawai[$kodep]['pulang'] = $pulangAt;
                        }
                    }

                    if ($dp->ijin) {
                        $uniquePegawai[$kodep]['ijin'][] = $dp->ijin;
                    }
                    if ($dp->ket) {
                        $uniquePegawai[$kodep]['ket'][] = $dp->ket;
                    }
                    if ($dp->potongan !== null) {
                        $uniquePegawai[$kodep]['potongan_manual'] = $dp->potongan;
                    }
                }
            }

            $jumlahPekerja = count($uniquePegawai);

            // --- C. Hitung target/potongan via arsitektur resmi (Action + Strategi) ---
            $potonganPerPegawai = []; // kodep => nilai potongan (Rp)

            if ($status === 'bongkar') {
                $mesinEnum = Mesin::Bongkar;
                $strategi = $mesinEnum->strategiPembagian(); // Kolektif (default) untuk Bongkar

                // Susun input pekerja: satu entri per pegawai UNIK (sudah di-dedupe di step B),
                // menitKerja dihitung dari rentang jam terluas hari itu.
                $pekerjaInput = [];
                foreach ($uniquePegawai as $kodep => $p) {
                    $menitKerja = 0;
                    if ($p['masuk'] && $p['pulang']) {
                        $menitKerja = max(0, $p['masuk']->diffInMinutes($p['pulang']));
                    }
                    $pekerjaInput[] = new PekerjaKerjaInput(
                        idPegawai: $kodep,
                        menitKerja: (float) $menitKerja,
                    );
                }

                $action = new HitungPotonganProduksiAction;

                // NOTE: dipanggil dengan positional argument (bukan named argument)
                // untuk menghindari error "Unknown named parameter" apabila nama
                // parameter di Action/DTO berbeda dari yang diharapkan (mis. karena
                // cache class lama / opcache belum di-refresh setelah rename).
                // Urutan wajib sama persis dengan signature:
                // execute(Mesin $mesin, StrategiPembagian $strategi, array $pekerja, float $hasilAktual, ?int $idUkuran = null, ?int $idJenisKayu = null)
                $hitung = $action->execute(
                    $mesinEnum,
                    $strategi,
                    $pekerjaInput,
                    (float) $totalHasil,
                );

                $potonganPerPegawai = $hitung?->potonganPerPegawai ?? [];

                // Ambil target dari hasil hitung (nama properti bisa bervariasi antar versi,
                // jadi dicoba beberapa kemungkinan supaya tetap tampil kalau salah satu ada).
                $targetDisplay = null;
                if ($hitung) {
                    foreach (['targetAdjusted', 'targetNormal', 'target'] as $prop) {
                        if (isset($hitung->{$prop})) {
                            $targetDisplay = (float) $hitung->{$prop};
                            break;
                        }
                    }
                }

                // Ambil jam normal (target) langsung dari row master Target milik mesin ini.
                $targetModelBongkar = TargetResolverFactory::make($mesinEnum)->resolve($mesinEnum->value, null, null);
                $jamNormal = $targetModelBongkar->jam ?? null;

                // Total jam kerja aktual = jumlah menit kerja semua pegawai unik / 60.
                $totalMenitAktual = 0;
                foreach ($pekerjaInput as $pi) {
                    $totalMenitAktual += $pi->menitKerja;
                }
                $totalJamAktual = $totalMenitAktual / 60;
                $rataJamPerOrang = $jumlahPekerja > 0 ? ($totalJamAktual / $jumlahPekerja) : 0;

                $summaries[$labelDivisi] = [
                    'hasil' => (float) $totalHasil,
                    'target' => $targetDisplay,
                    'selisih' => $targetDisplay !== null ? ((float) $totalHasil - $targetDisplay) : null,
                    'satuan' => 'pcs',
                    'jam_normal' => $jamNormal !== null ? (float) $jamNormal : null,
                    'jam_aktual_total' => $totalJamAktual,
                    'jam_aktual_rata' => $rataJamPerOrang,
                ];
            } else {
                // Jalur lama: MASUK (belum dimigrasikan ke strategi resmi)
                $kodeTargetDicari = 'MASUK';
                $targetRef = Target::where('kode_ukuran', $kodeTargetDicari)->first();

                $stdTarget = (int) ($targetRef->target ?? 0);
                $stdPotHarga = (int) ($targetRef->potongan ?? 0);

                $selisih = $totalHasil - $stdTarget;
                $potonganPerOrangLegacy = 0;

                if ($stdTarget > 0 && $selisih < 0 && $stdPotHarga > 0 && $jumlahPekerja > 0) {
                    $kekurangan = abs($selisih);
                    $totalPot = $kekurangan * $stdPotHarga;
                    $potonganRaw = $totalPot / $jumlahPekerja;

                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrangLegacy = $ribuan * 1000;
                    } elseif ($ratusan >= 300 && $ratusan < 800) {
                        $potonganPerOrangLegacy = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrangLegacy = ($ribuan + 1) * 1000;
                    }
                }

                foreach ($uniquePegawai as $kodep => $p) {
                    $potonganPerPegawai[$kodep] = $potonganPerOrangLegacy;
                }

                // Jam normal dari master Target MASUK, jam aktual dari rentang masuk-pulang tiap pegawai unik.
                $jamNormalMasuk = $targetRef->jam ?? null;
                $totalMenitAktualMasuk = 0;
                foreach ($uniquePegawai as $kodep => $p) {
                    if ($p['masuk'] && $p['pulang']) {
                        $totalMenitAktualMasuk += max(0, $p['masuk']->diffInMinutes($p['pulang']));
                    }
                }
                $totalJamAktualMasuk = $totalMenitAktualMasuk / 60;
                $rataJamPerOrangMasuk = $jumlahPekerja > 0 ? ($totalJamAktualMasuk / $jumlahPekerja) : 0;

                $summaries[$labelDivisi] = [
                    'hasil' => (float) $totalHasil,
                    'target' => $stdTarget > 0 ? (float) $stdTarget : null,
                    'selisih' => $stdTarget > 0 ? ((float) $totalHasil - $stdTarget) : null,
                    'satuan' => 'pcs',
                    'jam_normal' => $jamNormalMasuk !== null ? (float) $jamNormalMasuk : null,
                    'jam_aktual_total' => $totalJamAktualMasuk,
                    'jam_aktual_rata' => $rataJamPerOrangMasuk,
                ];
            }

            // --- D. Bangun baris output, satu baris per pegawai unik ---
            foreach ($uniquePegawai as $kodep => $p) {
                $potonganFinal = $p['potongan_manual'] ?? ($potonganPerPegawai[$kodep] ?? 0);

                $results[] = [
                    'hasil' => $labelDivisi,
                    'kodep' => $kodep,
                    'nama' => $p['pegawai']->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $p['masuk'] ? $p['masuk']->format('H:i:s') : '',
                    'pulang' => $p['pulang'] ? $p['pulang']->format('H:i:s') : '',
                    'ijin' => implode(', ', array_unique($p['ijin'])),
                    'keterangan' => implode(', ', array_unique($p['ket'])),
                    'potongan_targ' => (int) $potonganFinal,
                ];
            }
        }

        return [
            'rows' => $results,
            'summary' => $summaries,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(28);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(30);
                $sheet->getColumnDimension('H')->setWidth(18);

                foreach ($this->tableRanges as $range) {
                    $infoRow = $range['info'] ?? null;
                    $jamInfoRow = $range['jam_info'] ?? null;
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    if ($infoRow) {
                        $sheet->mergeCells("A{$infoRow}:H{$infoRow}");
                        $sheet->getStyle("A{$infoRow}:H{$infoRow}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF334155']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFF7ED'],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($infoRow)->setRowHeight(18);
                    }

                    if ($jamInfoRow) {
                        $sheet->mergeCells("A{$jamInfoRow}:H{$jamInfoRow}");
                        $sheet->getStyle("A{$jamInfoRow}:H{$jamInfoRow}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF334155']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFEFF6FF'],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($jamInfoRow)->setRowHeight(18);
                    }

                    // Border seluruh tabel
                    $sheet->getStyle("A{$headerRow}:H{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ],
                        ],
                    ]);

                    // Header style
                    $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // Total row style
                    $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9'],
                        ],
                    ]);

                    // Alignment data
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("D{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }

                    // Judul grup (3 baris di atas header: judul, info hasil/target, info jam) — merge & bold
                    $titleRow = $headerRow - 3;
                    $sheet->mergeCells("A{$titleRow}:H{$titleRow}");
                    $sheet->getStyle("A{$titleRow}:H{$titleRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF2F5597'],
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($titleRow)->setRowHeight(22);
                }
            },
        ];
    }
}
