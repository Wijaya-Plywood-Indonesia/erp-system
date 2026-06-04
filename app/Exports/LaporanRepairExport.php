<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanRepairExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // Array hasil RepairDataMap (untuk Sheet 1)
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query langsung ke DB, tidak lewat transformer!
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (UPDATE: TAMBAH KOLOM KETERANGAN)
// ============================================================
class LaporanRepairDetailSheet implements FromCollection, WithHeadings, WithTitle
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
            $first        = $items->first();
            $targetPerJam = $first['jam_kerja'] > 0
                ? round($first['target'] / $first['jam_kerja'], 2)
                : 0;
            $pekerja      = $first['pekerja'] ?? [];

            $rows->push(['MEJA',        $first['nomor_meja']]);
            $rows->push(['UKURAN',      $first['ukuran']]);
            $rows->push(['JENIS KAYU',  $first['jenis_kayu']]);
            $rows->push(['KW',          $first['kw']]);
            $rows->push(['TANGGAL',     $first['tanggal']]);
            $rows->push([]);

            // 🚀 UPDATE HEADER TABEL: Menambahkan Keterangan Hasil & Kerja di samping Keterangan Absen lama
            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil', // 👈 Kolom Baru
                'Keterangan Kerja', // 👈 Kolom Baru
                '',
                'Target Harian',
                'Jam Kerja',
                'Target / Jam',
                'Hasil',
                'Selisih'
            ]);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',       // Ini Keterangan Absen bawaan array Anda
                    $p['keterangan_hasil'] ?? '—', // 👈 Diambil langsung dari mapping data hasil pekerja
                    $p['keterangan_kerja'] ?? '—', // 👈 Diambil langsung dari mapping data rencana kerja pekerja
                    '',
                    $first['target'],
                    $first['jam_kerja'],
                    $targetPerJam,
                    $first['hasil'],
                    $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                '',
                '',
                '',
                '',
                $totalPotongan,
                '',
                '', // Kosongkan kolom baru untuk baris TOTAL
                '', // Kosongkan kolom baru untuk baris TOTAL
                '',
                $first['target'],
                $first['jam_kerja'],
                $targetPerJam,
                $first['hasil'],
                $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
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
// SHEET 2: SUMMARY — Bersih Seperti Semula
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    private const MASTER_KW = ['1', '2', '3', '4', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d M');

            foreach ($produksi->modalRepairs as $modal) {
                $p = (float) ($modal->ukuran->panjang ?? 0);
                $l = (float) ($modal->ukuran->lebar   ?? 0);
                $t = (float) ($modal->ukuran->tebal   ?? 0);
                $jenis = strtoupper($modal->jenisKayu->kode_kayu ?? substr($modal->jenisKayu->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim($modal->kw ?? ''));

                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$kwData}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'current_kw'  => $kwData,
                        'pekerja_ids' => [],
                    ];

                    foreach (self::MASTER_KW as $mKw) {
                        $this->summary[$key]['kw_' . $mKw] = 0;
                    }
                }

                $hasilModal = 0;
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu > 0) {
                        $hasilModal += $hasilIndividu;
                        $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                    }
                }

                if ($kwData !== '' && $hasilModal > 0) {
                    if (in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $hasilModal;
                    }
                }
            }
        }

        ksort($this->summary);
    }

    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalMasterKw = count(self::MASTER_KW);
        $lastRow = $dataStart + count($this->summary) - 1;

        // Row 2: Grand Total
        $grandRow = ['', '', '', '', ''];
        for ($i = 0; $i < $totalMasterKw; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalMasterKw);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";

        $rows->push($grandRow);

        // Row 3+: Data Rows
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            foreach (self::MASTER_KW as $mKw) {
                $val = $s['kw_' . $mKw] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_KW as $mKw) {
            $heads[] = 'KW ' . strtoupper($mKw);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Header & Grand Total
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => $color]],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }
}

// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
// ============================================================
// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'Jurnal';
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
            'D' => '0.00',
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // =========================================================================
            // RUMUS TOTAL (KOLOM N) YANG BARU & FLEKSIBEL
            // =========================================================================
            // Jika J="m", maka Total = Harga * M3 (M*L)
            // Jika J="b", maka Total = Harga * Banyak (M*K)
            // Jika tidak keduanya, maka Total = Harga (M)
            // =========================================================================
            for ($row = 2; $row <= $lastRow; $row++) {
                $namaAkunVal = $sheet->getCell("A{$row}")->getValue();
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

    /**
     * Mengembalikan [noAkun, namaAkun] berdasarkan jenis, tebal, isAf, dan kwNorm.
     */
    private function getNoAkunDanNama(string $jenis, float $tebal, bool $isAf, string $kwNorm): array
    {
        $jnsNorm    = $this->normalizeJenis($jenis);
        $isSengon   = $jnsNorm === 'sengon';
        $tipeVeneer = $tebal < 1 ? '260 face/back' : '130 core';
        $jnsLabel   = strtolower($jnsNorm);

        if ($isAf) {
            if ($kwNorm === 'jadi') {
                $noAkun   = $isSengon ? '1472.00' : '1471.00';
                $namaAkun = "Veneer Jadi ppc {$jnsLabel} WJY";
            } else {
                $noAkun   = $isSengon ? '1452.00' : '1451.00';
                $namaAkun = "Veneer Kering ppc {$jnsLabel} WJY";
            }
        } elseif ($kwNorm === 'jadi') {
            if ($tebal < 1) {
                $noAkun = $isSengon ? '1461.00' : '1462.00';
            } else {
                $noAkun = $isSengon ? '1466.00' : '1467.00';
            }
            $namaAkun = "Veneer Jadi {$tipeVeneer} {$jnsLabel} WJY";
        } else {
            if ($tebal < 1) {
                $noAkun = $isSengon ? '1441.00' : '1442.00';
            } else {
                $noAkun = $isSengon ? '1446.00' : '1447.00';
            }
            $namaAkun = "Veneer Kering {$tipeVeneer} {$jnsLabel} WJY";
        }

        return [$noAkun, $namaAkun];
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false, string $status = 'jadi'): int
    {
        $jns = $this->normalizeJenis($jenis);

        if ($isAf) {
            $kelompok = $tebal < 1 ? 'ppc_faceback' : 'ppc_core';
            $harga    = [
                'sengon'  => [
                    'ppc_faceback' => ['basah' => 1700000, 'kering' => 1700000, 'jadi' => 1700000],
                    'ppc_core'     => ['basah' => 1500000, 'kering' => 1500000, 'jadi' => 1500000],
                ],
                'meranti' => [
                    'ppc_faceback' => ['basah' => 2000000, 'kering' => 2000000, 'jadi' => 2000000],
                    'ppc_core'     => ['basah' => 1800000, 'kering' => 1800000, 'jadi' => 1800000],
                ],
            ];
            return $harga[$jns][$kelompok][$status] ?? 0;
        }

        $kelompok = $tebal < 1 ? 'faceback' : 'core';
        $harga    = [
            'sengon'  => [
                'faceback' => ['basah' => 2700000, 'kering' => 2800000, 'jadi' => 4000000],
                'core'     => ['basah' => 1700000, 'kering' => 1900000, 'jadi' => 2250000],
            ],
            'meranti' => [
                'faceback' => ['basah' => 8000000, 'kering' => 8500000, 'jadi' => 12500000],
                'core'     => ['basah' => 2100000, 'kering' => 2500000, 'jadi' => 2800000],
            ],
        ];
        return $harga[$jns][$kelompok][$status] ?? 0;
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,
            (string) $tgl,
            '',
            (string) $noAkun,
            '',
            '',
            'tembel',
            $keterangan,
            strtolower($map),
            strtolower($hitKbk),
            (float) $banyak,
            (float) $m3,
            (float) $harga,
            0,
        ];
    }

    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat         = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit        = 0;
            $totalKredit       = 0;
            $jurnalBlockDebit  = [];
            $jurnalBlockKredit = [];

            // Grouping data Hasil berdasarkan ukuran spesifik
            $groupedHasil = collect($produksi->hasilRepairs)->groupBy(function ($hasil) {
                $modal = $hasil->rencanaRepair?->modalRepairs;
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) {
                    return 'invalid_data';
                }
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower(($hasil->rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $panjang  = (float)($modal->ukuran->panjang ?? 0);
                $lebar    = (float)($modal->ukuran->lebar ?? 0);
                $tebal    = (float)($modal->ukuran->tebal ?? 0);
                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}";
            });

            // Grouping data Modal berdasarkan ukuran spesifik
            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) {
                    return 'invalid_data';
                }
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower($modal->kw ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $panjang  = (float)($modal->ukuran->panjang ?? 0);
                $lebar    = (float)($modal->ukuran->lebar ?? 0);
                $tebal    = (float)($modal->ukuran->tebal ?? 0);
                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}";
            });

            // Satukan seluruh key ukuran spesifik dari Hasil & Modal
            $allKeys = collect(array_keys($groupedHasil->toArray()))
                ->merge(array_keys($groupedModal->toArray()))
                ->reject(fn($k) => $k === 'invalid_data')
                ->unique();

            foreach ($allKeys as $key) {
                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw] = explode('|', $key);
                $isAf    = ($statusKw === 'af');
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;

                $hasilItems = $groupedHasil->get($key, collect());
                $modalItems = $groupedModal->get($key, collect());

                $sample = $hasilItems->first() ?? $modalItems->first();
                if (!$sample) continue;

                // Mengambil nama asli kayu
                $namaKayuAsli = '';
                if ($hasilItems->first()) {
                    $namaKayuAsli = $hasilItems->first()->rencanaRepair?->modalRepairs?->jenisKayu->nama_kayu ?? '';
                } else {
                    $namaKayuAsli = $modalItems->first()->jenisKayu->nama_kayu ?? '';
                }
                if (empty($namaKayuAsli)) {
                    $namaKayuAsli = $jnsNorm;
                }

                $totalHasilBanyak = $hasilItems->sum('jumlah');
                $totalHasilM3     = ($panjang * $lebar * $tebal * $totalHasilBanyak) / 10000000;

                $totalModalBanyak = $modalItems->sum('jumlah');
                $totalModalM3     = ($panjang * $lebar * $tebal * $totalModalBanyak) / 10000000;

                // Selisih per ukuran (Modal - Hasil)
                $hilang = $totalModalBanyak - $totalHasilBanyak;

                // ============================================================
                // LOGIKA SINKRONISASI FLUID: MEMECAH ROW PENUH KELEBIHAN / KEHILANGAN
                // ============================================================
                if ($hilang > 0) {
                    // Ada KEKURANGAN/KEHILANGAN (Modal > Hasil)
                    $regHasilBanyak = $totalHasilBanyak;
                    $regHasilM3     = $totalHasilM3;

                    // Kuantitas modal reguler diperkecil sesuai jumlah hasil
                    $regModalBanyak = $totalHasilBanyak;
                    $regModalM3     = ($panjang * $lebar * $tebal * $regModalBanyak) / 10000000;

                    // Sisa selisih dibuatkan row baru Kehilangan (Veneer Kering)
                    $hilangBanyak   = $hilang;
                    $hilangM3       = ($panjang * $lebar * $tebal * $hilangBanyak) / 10000000;

                    $kelebihanBanyak = 0;
                    $kelebihanM3     = 0;
                } elseif ($hilang < 0) {
                    // Ada KELEBIHAN (Hasil > Modal)
                    $kelebihanBanyak = abs($hilang);
                    $kelebihanM3     = ($panjang * $lebar * $tebal * $kelebihanBanyak) / 10000000;

                    // Kuantitas hasil reguler diperkecil sesuai jumlah modal
                    $regHasilBanyak = $totalModalBanyak;
                    $regHasilM3     = ($panjang * $lebar * $tebal * $regHasilBanyak) / 10000000;

                    $regModalBanyak = $totalModalBanyak;
                    $regModalM3     = $totalModalM3;

                    $hilangBanyak   = 0;
                    $hilangM3       = 0;
                } else {
                    // Modal & Hasil Sempurna (Tidak ada selisih)
                    $regHasilBanyak = $totalHasilBanyak;
                    $regHasilM3     = $totalHasilM3;

                    $regModalBanyak = $totalModalBanyak;
                    $regModalM3     = $totalModalM3;

                    $hilangBanyak   = 0;
                    $hilangM3       = 0;
                    $kelebihanBanyak = 0;
                    $kelebihanM3     = 0;
                }

                $tipeVeneer = $tebal < 1 ? '260 face/back' : '130 core';

                // ------------------------------------------------------------
                // OUTPUT DEBIT: JURNAL HASIL (Veneer Jadi)
                // ------------------------------------------------------------
                if ($regHasilBanyak > 0) {
                    $kwNorm = 'jadi';
                    [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, $kwNorm);
                    $hargaPatok          = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'jadi' : $kwNorm);
                    $totalValue          = $regHasilM3 * $hargaPatok;

                    $keterangan = $isAf
                        ? "af " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}"
                        : "{$tipeVeneer} " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}";

                    $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $regHasilBanyak, $regHasilM3, $hargaPatok, 'm');
                    $totalDebit        += $totalValue;
                }

                // Row Baru Kelebihan (Veneer Jadi)
                if ($kelebihanBanyak > 0) {
                    $kwNorm = 'jadi';
                    [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, $kwNorm);
                    $hargaPatok          = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'jadi' : $kwNorm);
                    $totalValue          = $kelebihanM3 * $hargaPatok;

                    $keterangan = $isAf
                        ? "Kelebihan af " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}"
                        : "Kelebihan {$tipeVeneer} " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}";

                    $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $kelebihanBanyak, $kelebihanM3, $hargaPatok, 'm');
                    $totalDebit        += $totalValue;
                }

                // ------------------------------------------------------------
                // OUTPUT KREDIT: JURNAL MODAL (Veneer Kering)
                // ------------------------------------------------------------
                if ($regModalBanyak > 0) {
                    $kwNorm = 'kering';
                    [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, $kwNorm);
                    $hargaPatok          = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'kering' : $kwNorm);
                    $totalValue          = $regModalM3 * $hargaPatok;

                    $keterangan = ''; // Modal regular kosong agar rapi

                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $regModalBanyak, $regModalM3, $hargaPatok, 'm');
                    $totalKredit        += $totalValue;
                }

                // Row Baru Kehilangan (Veneer Kering)
                if ($hilangBanyak > 0) {
                    $kwNorm = 'kering';
                    [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, $kwNorm);
                    $hargaPatok          = $this->getHargaPatok($jnsNorm, $tebal, $isAf, $isAf ? 'kering' : $kwNorm);
                    $totalValue          = $hilangM3 * $hargaPatok;

                    $keterangan = $isAf
                        ? "Kehilangan af " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}"
                        : "Kehilangan {$tipeVeneer} " . strtolower($namaKayuAsli) . " uk {$panjang} x {$lebar} x {$tebal}";

                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $hilangBanyak, $hilangM3, $hargaPatok, 'm');
                    $totalKredit        += $totalValue;
                }
            }

            // ============================================================
            // 4. KREDIT: Gaji Pegawai Repair
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $totalGaji           = $jmlPekerja * 150000;
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, 0, 150000, 'b');
                $totalKredit        += $totalGaji;
            }

            // ============================================================
            // 5. PENYEIMBANG: HPP Repair
            // ============================================================
            $selisih     = $totalDebit - $totalKredit;
            $jurnalBlock = array_merge($jurnalBlockDebit, $jurnalBlockKredit);

            if (round($selisih, 2) != 0) {
                $mapSelisih = $selisih > 0 ? 'k' : 'd';
                $jurnalBlock[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', $mapSelisih, 0, 0, abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
