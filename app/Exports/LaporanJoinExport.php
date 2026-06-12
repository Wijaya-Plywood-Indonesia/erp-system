<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;
use Illuminate\Support\Facades\Log;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanJoinExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // flat array dari JoinDataMap (Sheet 1)
        protected string $tanggal     // format 'Y-m-d' (untuk query Sheet 2 & 3)
    ) {}

    public function sheets(): array
    {
        $rawCollection = LoadLaporanJoin::run($this->tanggal);

        return [
            new LaporanJoinDetailSheet($this->detailData),
            new LaporanJoinSummarySheet($rawCollection),
            new JurnalSheet($rawCollection), // Sheet 3: Jurnal Akuntansi dengan Hardcode Bahan Penolong
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (Logika bawaan Anda)
// ============================================================
class LaporanJoinDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $detailData)
    {
        $this->data = collect($detailData)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $groupKey => $items) {
            $first   = $items->first();
            $pekerja = $first['pekerja'] ?? [];
            $target  = (int) $first['target'];
            $hasil   = (int) $first['hasil'];
            $selisih = (int) $first['selisih'];

            $rows->push(['MEJA / AREA',       $first['nomor_meja']]);
            $rows->push(['UKURAN',             $first['ukuran']]);
            $rows->push(['JENIS BARANG',       $first['jenis_kayu'] ?? '-']);
            $rows->push(['GRADE / KW',         $first['kw']]);
            $rows->push(['TANGGAL PRODUKSI',   $first['tanggal']]);
            $rows->push([]);

            $rows->push([
                'ID PEGAWAI',
                'Nama Lengkap',
                'Jam Masuk',
                'Jam Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan',
                '',
                'Target Harian',
                'Hasil Produksi',
                'Selisih',
            ]);

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

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                count($pekerja) . ' Orang',
                '',
                '',
                '',
                $totalPotongan > 0 ? $totalPotongan : '-',
                '',
                '',
                $target,
                $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }
    public function title(): string
    {
        return 'Detail Per Meja';
    }
}

// ============================================================
// SHEET 2: SUMMARY (Logika bawaan Anda)
// ============================================================
class LaporanJoinSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $totalRows       = [];
    private array $firstRowOfGroup = [];

    public function __construct(protected $rawCollection) {}

    public function collection()
    {
        $rows = collect();
        $blocks = [];

        $grandTotalTotal = 0;
        $grandTotalByk = 0;

        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');

            // 1. Build bahan list
            $bahanRows = [];
            try {
                foreach ($produksi->bahanProduksi ?? collect() as $bahan) {
                    $hargaSatuan = (float) (
                        $bahan->harga
                        ?? $bahan->bahanPenolong?->harga
                        ?? $bahan->bahan?->harga
                        ?? 0
                    );
                    $jumlah = (float) ($bahan->jumlah ?? 0);
                    $namaBahanTerbaca = $bahan->nama_bahan_penolong ?? $bahan->nama_bahan ?? '-';
                    $bahanRows[] = [
                        'nama'   => strtoupper($namaBahanTerbaca),
                        'jumlah' => $jumlah > 0 ? $jumlah : '-',
                        'harga'  => $hargaSatuan,
                        'total'  => $jumlah * $hargaSatuan,
                    ];
                }
            } catch (\Exception $e) {
            }

            $jumlahPekerja = (int) $produksi->pegawaiJoint->count();
            $bahanRows[] = [
                'nama'   => 'PEKERJA',
                'jumlah' => $jumlahPekerja > 0 ? $jumlahPekerja : '-',
                'harga'  => 0,
                'total'  => 0,
            ];

            // 2. Build hasil list
            $hasilGroups = $produksi->hasilJoint
                ->groupBy(fn($h) => $h->id_ukuran . '|' . $h->kw);

            $hasilRows = [];
            foreach ($hasilGroups as $groupKey => $hasilItems) {
                $firstHasil  = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran;
                $byk = (int) $hasilItems->sum('jumlah');
                $hasilRows[] = [
                    'p'   => $ukuranModel->panjang ?? '',
                    'l'   => $ukuranModel->lebar   ?? '',
                    't'   => $ukuranModel->tebal   ?? '',
                    'byk' => $byk,
                    'kw'  => $firstHasil->kw ?? '-',
                ];
            }

            // 3. Align side-by-side
            $maxRows = max(count($bahanRows), count($hasilRows));
            $blockRows = [];
            $totalBahanForBlock = 0;
            $totalHasilForBlock = 0;

            for ($i = 0; $i < $maxRows; $i++) {
                $row = [
                    'tanggal' => ($i === 0) ? $tanggal : '',
                    'bahan_nama' => '',
                    'bahan_jumlah' => '',
                    'bahan_harga' => '',
                    'bahan_total' => '',
                    'p' => '',
                    'l' => '',
                    't' => '',
                    'byk' => '',
                    'kw' => '',
                ];

                if ($i < count($bahanRows)) {
                    $b = $bahanRows[$i];
                    $row['bahan_nama'] = $b['nama'];
                    $row['bahan_jumlah'] = $b['jumlah'];
                    $row['bahan_harga'] = $b['harga'] > 0 ? number_format($b['harga'], 3, '.', '') : '-';
                    $row['bahan_total'] = $b['total'] > 0 ? number_format($b['total'], 3, '.', '') : 0;
                    $totalBahanForBlock += $b['total'];
                }

                if ($i < count($hasilRows)) {
                    $h = $hasilRows[$i];
                    $row['p'] = $h['p'];
                    $row['l'] = $h['l'];
                    $row['t'] = $h['t'];
                    $row['byk'] = $h['byk'];
                    $row['kw'] = $h['kw'];
                    $totalHasilForBlock += $h['byk'];
                }

                $blockRows[] = $row;
            }

            $blocks[] = [
                'rows' => $blockRows,
                'totalBahan' => $totalBahanForBlock,
                'totalHasil' => $totalHasilForBlock,
            ];

            $grandTotalTotal += $totalBahanForBlock;
            $grandTotalByk += $totalHasilForBlock;
        }

        // Push grand total row at the top (Row 2)
        $rows->push([
            '',
            '',
            '',
            '',
            $grandTotalTotal > 0 ? number_format($grandTotalTotal, 3, '.', '') : 0,
            '',
            '',
            '',
            $grandTotalByk > 0 ? $grandTotalByk : 0,
            '',
        ]);

        $currentExcelRow = 3;
        foreach ($blocks as $block) {
            $this->firstRowOfGroup[] = $currentExcelRow;

            foreach ($block['rows'] as $row) {
                $rows->push([
                    $row['tanggal'],
                    $row['bahan_nama'],
                    $row['bahan_jumlah'],
                    $row['bahan_harga'],
                    $row['bahan_total'],
                    $row['p'],
                    $row['l'],
                    $row['t'],
                    $row['byk'],
                    $row['kw'],
                ]);
                $currentExcelRow++;
            }

            // Total row for this block
            $this->totalRows[] = $currentExcelRow;
            $rows->push([
                '',
                'TOTAL :',
                '',
                '',
                $block['totalBahan'] > 0 ? number_format($block['totalBahan'], 3, '.', '') : 0,
                '',
                '',
                '',
                $block['totalHasil'],
                '',
            ]);
            $currentExcelRow++;
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Tgl', 'BAHAN', 'BANYAK', 'HARGA', 'TOTAL', 'p', 'l', 't', 'byk', 'kw'];
    }
    public function title(): string
    {
        return 'Summary Join';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:J2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:J{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach ($this->totalRows as $rowNum) {
                    $sheet->getStyle("A{$rowNum}:J{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFF2CC']],
                        'font' => ['bold' => true],
                    ]);
                }

                $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("B3:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}

// ============================================================
// SHEET 3: JURNAL — KODIFIKASI KOMA KUSTOM & LOCK DESIMAL
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 8,
            'F' => 8,
            'G' => 15,
            'H' => 45,
            'I' => 8,
            'J' => 8,
            'K' => 14,
            'L' => 16,
            'M' => 16,
            'N' => 22,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '0.00',           // No Akun sebagai Teks agar .00 tidak hilang
            'K' => '#,##0',          // Banyak
            'L' => '#,##0.0000',     // M3: 4 desimal
            'M' => '#,##0.00',       // Harga
            'N' => '#,##0',          // Total
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // =========================================================================
            // PENYESUAIAN RUMUS TOTAL (KOLOM N) SECARA DINAMIS
            // =========================================================================
            // Jika J="m", maka Total = Harga * M3 (M*L)
            // Jika J="b", maka Total = Harga * Banyak (M*K)
            // Jika tidak keduanya, maka langsung mengambil Harga (M)
            // =========================================================================
            for ($row = 2; $row <= $lastRow; $row++) {
                $namaAkunVal = $sheet->getCell("A{$row}")->getValue();
                // Formula hanya diisi pada baris yang memiliki data Akun (bukan baris kosong)
                if ($namaAkunVal !== '' && $namaAkunVal !== null) {
                    $sheet->getCell("N{$row}")->setValue(
                        "=IF(J{$row}=\"m\",M{$row}*L{$row},IF(J{$row}=\"b\",M{$row}*K{$row},M{$row}))"
                    );
                }
            }
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false): int
    {
        $jns = $this->normalizeJenis($jenis);

        $dbHarga = $this->getHargaVeneerDb($jenis, $tebal, 'jadi', $isAf);
        if ($dbHarga > 0) {
            return $dbHarga;
        }

        // Jika PCC (AF), gunakan harga khusus
        if ($isAf) {
            return ($jns === 'sengon') ? 1500000 : 1800000;
        }

        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $harga = [
            'sengon' => ['faceback' => 4000000, 'core' => 2250000],
            'meranti' => ['faceback' => 12500000, 'core' => 2800000],
        ];
        return $harga[$jns][$kelompok] ?? 0;
    }

    private function getHargaVeneerDb(string $jenis, float $tebal, string $tipeKualitas, bool $isAf = false): int
    {
        $jns = str_contains(strtolower(trim($jenis)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
        if (!$jenisKayu) {
            return 0;
        }

        if ($isAf) {
            $kelompok = ($tebal < 1) ? 'ppc_faceback' : 'ppc_core';
        } else {
            $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        }

        $ukuranOptions = $kelompok === 'faceback'
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ($kelompok === 'ppc_faceback' ? ['ppc_faceback'] : [$kelompok]);

        $hargaVeneer = \App\Models\HargaVeneer::where('id_jenis_kayu', $jenisKayu->id)
            ->whereIn('ukuran', $ukuranOptions)
            ->first();

        if (!$hargaVeneer) {
            return 0;
        }

        if ($tipeKualitas === 'basah') {
            return (int) $hargaVeneer->harga_basah;
        } elseif ($tipeKualitas === 'kering') {
            return (int) $hargaVeneer->harga_kering;
        } else {
            return (int) $hargaVeneer->harga_jadi;
        }
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,
            (string)$tgl,
            '',
            (string)$noAkun,
            '',
            '',
            'nyambung',
            $keterangan,
            strtolower($map),
            strtolower($hitKbk),
            (float) $banyak,
            (float) $m3,
            (float) $harga,
            (float) $total
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');
            $totalDebit = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            // 1. DEBIT: Hasil
            foreach ($produksi->hasilJoint as $hasil) {
                $ukuran = $hasil->ukuran;
                $jnsNorm = $this->normalizeJenis($hasil->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($hasil->kw ?? ''), 'af');

                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1466.00' : '1467.00');
                $namaAkun = $isAf ? "Veneer Jadi ppc " . strtolower(ucfirst($jnsNorm)) . " WJY" : "Veneer Jadi 130 core " . strtolower(ucfirst($jnsNorm)) . " WJY";
                $keterangan = $isAf ? "af " . strtolower($hasil->jenisKayu->nama_kayu ?? '') . " " . $ukuran->panjang . " x " . $ukuran->lebar . " x " . $ukuran->tebal
                    : "130 core " . strtolower($hasil->jenisKayu->nama_kayu ?? '') . " uk " . $ukuran->panjang . " x " . $ukuran->lebar . " x " . $ukuran->tebal;
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $hasil->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float)$ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalDebit += $totalValue;
            }

            // 2. KREDIT: Modal
            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');
                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1466.00' : '1467.00');
                $namaAkun = $isAf ? "Veneer Jadi ppc " . strtolower(ucfirst($jnsNorm)) . " WJY" : "Veneer Jadi 130 core " . strtolower(ucfirst($jnsNorm)) . " WJY";
                $keterangan = $isAf ? "af " . strtolower($modal->jenisKayu->nama_kayu ?? '') . " " . $ukuran->panjang . " x " . $ukuran->lebar . " x " . $ukuran->tebal
                    : "130 core " . strtolower($modal->jenisKayu->nama_kayu ?? '') . " uk " . $ukuran->panjang . " x " . $ukuran->lebar . " x " . $ukuran->tebal;
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float)$ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalKredit += $totalValue;
            }

            // 3. KREDIT: Bahan (Hit KBK: b)
            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlah = (float)($bahan->jumlah ?? 0);
                if ($jumlah > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';
                    $nama = strtolower(trim($namaBahanRaw));

                    // 1. Inisialisasi default
                    $hargaH = 15000;
                    $akun = '1481.00';
                    $prefix = ''; // Default tanpa prefix

                    // 2. Logika Penentuan Prefix dan Harga
                    if (str_contains($nama, 'aruki')) {
                        $hargaH = 6900;
                        $akun = '1507.63';
                        $prefix = 'Lem '; // Diberi prefix
                    } elseif (str_contains($nama, 'dover')) {
                        $hargaH = 6950;
                        $akun = '1507.64';
                        $prefix = 'Lem '; // Diberi prefix
                    } elseif (str_contains($nama, 'tepung')) {
                        $hargaH = 4500;
                        $akun = '1507.62';
                        $prefix = ''; // Tetap tanpa prefix
                    }

                    $total = $hargaH * $jumlah;

                    // 3. Gabungkan prefix dengan nama bahan saat pembuatan baris
                    $namaAkun = $prefix . ucfirst($nama) . ' WJY';

                    $jurnalBlock[] = $this->makeRow(
                        $namaAkun,
                        $tglFormat,
                        $akun,
                        '',
                        'k',
                        $jumlah,
                        0,
                        $hargaH,
                        $total,
                        'b'
                    );
                    $totalKredit += $total;
                }
            }

            // 4. KREDIT: Gaji
            $jmlPekerja = (int)$produksi->pegawaiJoint->count();
            if ($jmlPekerja > 0) {
                $jurnalBlock[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, 0, 150000, ($jmlPekerja * 150000), 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // 5. HPP
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                // PENYESUAIAN: hitKbk diubah dari 'm' menjadi '' agar rumus excel membaca harga di Kolom M secara utuh tanpa dikalikan 0
                $jurnalBlock[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', 0, 0, abs($selisih), abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) $rows[] = $row;
            $rows[] = array_fill(0, 14, '');
        }
        return $rows;
    }
}
