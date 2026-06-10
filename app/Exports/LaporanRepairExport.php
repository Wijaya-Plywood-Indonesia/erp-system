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
        $jnsLabel   = strtolower($jnsNorm);

        $isWHN = false;
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                $isWHN = true;
            }
        }

        $companySuffix = $isWHN ? 'WHN' : 'WJY';
        $accountSuffix = $isWHN ? '.01' : '.00';

        $tipeVeneer = $tebal < 1 ? '260 face/back' : '130 core';

        if ($isAf) {
            if ($kwNorm === 'jadi') {
                $noAkun   = $isSengon ? ('1472' . $accountSuffix) : ('1471' . $accountSuffix);
                $namaAkun = "Veneer Jadi ppc {$jnsLabel} {$companySuffix}";
            } else {
                $noAkun   = $isSengon ? ('1452' . $accountSuffix) : ('1451' . $accountSuffix);
                $namaAkun = "Veneer Kering ppc {$jnsLabel} {$companySuffix}";
            }
        } elseif ($kwNorm === 'jadi') {
            if ($tebal < 1) {
                $noAkun = $isSengon ? ('1461' . $accountSuffix) : ('1462' . $accountSuffix);
            } else {
                $noAkun = $isSengon ? ('1466' . $accountSuffix) : ('1467' . $accountSuffix);
            }
            $namaAkun = "Veneer Jadi {$tipeVeneer} {$jnsLabel} {$companySuffix}";
        } else {
            if ($tebal < 1) {
                $noAkun = $isSengon ? ('1441' . $accountSuffix) : ('1442' . $accountSuffix);
            } else {
                $noAkun = $isSengon ? ('1446' . $accountSuffix) : ('1447' . $accountSuffix);
            }
            $namaAkun = "Veneer Kering {$tipeVeneer} {$jnsLabel} {$companySuffix}";
        }

        return [$noAkun, $namaAkun];
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false, string $status = 'jadi'): int
    {
        $jns = $this->normalizeJenis($jenis);

        $dbHarga = $this->getHargaVeneerDb($jenis, $tebal, $status, $isAf);
        if ($dbHarga > 0) {
            return $dbHarga;
        }

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
                'faceback' => ['basah' => 2700000, 'kering' => 3050000, 'jadi' => 4000000],
                'core'     => ['basah' => 1700000, 'kering' => 2000000, 'jadi' => 2250000],
            ],
            'sengon'  => ['faceback' => ['basah' => 2700000, 'kering' => 2800000, 'jadi' => 4000000], 'core' => ['basah' => 1700000, 'kering' => 1900000, 'jadi' => 2250000]],
            'meranti' => ['faceback' => ['basah' => 8000000, 'kering' => 8500000, 'jadi' => 12500000], 'core' => ['basah' => 2100000, 'kering' => 2500000, 'jadi' => 2800000]],
        ];
        return $harga[$jns][$kelompok][$status] ?? 0;
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
            $hitKbk !== '' && $hitKbk !== null ? strtolower($hitKbk) : '',
            $banyak === '' || $banyak === null ? '' : (float) $banyak,
            $m3 === '' || $m3 === null ? '' : (float) $m3,
            $harga === '' || $harga === null ? '' : (float) $harga,
            '', 
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
            $selisihPerGroup   = [];

            // ============================================================
            // 1. DEBIT: Hasil Repair → kwNorm selalu 'jadi'
            // ============================================================
            $groupedHasil = collect($produksi->hasilRepairs)->groupBy(function ($hasil) {
                $modal = $hasil->rencanaRepair?->modalRepairs;
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower(($hasil->rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $kw       = (int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT);
                $kwNorm   = ($kw == 1 || $kw == 2) ? 'jadi' : 'kering';
                return "{$jnsNorm}|{$modal->ukuran->panjang}|{$modal->ukuran->lebar}|{$modal->ukuran->tebal}|{$isAf}|{$kwNorm}";
            });

            foreach ($groupedHasil as $key => $items) {
                if ($key === 'invalid_data') continue;
                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwNorm] = explode('|', $key);
                $isAf = ($statusKw === 'af');
                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ((float)$panjang * (float)$lebar * (float)$tebal * $totalBanyak) / 10000000;
                $kelompok = (float)$tebal < 1 ? 'faceback' : 'core';
                $groupKey = "{$jnsNorm}|{$kelompok}|{$statusKw}";
                if (!isset($selisihPerGroup[$groupKey])) {
                    $selisihPerGroup[$groupKey] = ['hasilM3' => 0, 'modalM3' => 0, 'hasilBanyak' => 0, 'modalBanyak' => 0, 'jenis' => $jnsNorm, 'tebal' => (float)$tebal, 'panjang' => (float)$panjang, 'lebar' => (float)$lebar, 'kelompok' => $kelompok, 'isAf' => $isAf, 'kwNorm' => 'jadi'];
                }
                $selisihPerGroup[$groupKey]['hasilM3'] += $totalM3;
                $selisihPerGroup[$groupKey]['hasilBanyak'] += $totalBanyak;
                [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, (float)$tebal, $isAf, 'jadi');
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float)$tebal, $isAf, 'jadi');

                $tipeVeneer = (float)$tebal < 1 ? '260 Face/Back' : '130 Core';
                $keterangan = "{$tipeVeneer} " . strtolower($jnsNorm) . " uk " . str_replace('.', ',', (float)$tebal);

                $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $totalBanyak, $totalM3, $hargaPatok, 'm');
                $totalDebit += ($totalM3 * $hargaPatok);
            }

            // ============================================================
            // 2. KREDIT: Modal Repair → kwNorm selalu 'kering'
            // ============================================================
            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';
                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower($modal->kw ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $kw       = (int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT);
                $kwNorm   = ($kw == 1 || $kw == 2) ? 'jadi' : 'kering';
                return "{$jnsNorm}|{$modal->ukuran->panjang}|{$modal->ukuran->lebar}|{$modal->ukuran->tebal}|{$isAf}|{$kwNorm}";
            });

            foreach ($groupedModal as $key => $items) {
                if ($key === 'invalid_data') continue;
                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwNorm] = explode('|', $key);
                $isAf = ($statusKw === 'af');
                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ((float)$panjang * (float)$lebar * (float)$tebal * $totalBanyak) / 10000000;
                $kelompok = (float)$tebal < 1 ? 'faceback' : 'core';
                $groupKey = "{$jnsNorm}|{$kelompok}|{$statusKw}";
                if (!isset($selisihPerGroup[$groupKey])) {
                    $selisihPerGroup[$groupKey] = ['hasilM3' => 0, 'modalM3' => 0, 'hasilBanyak' => 0, 'modalBanyak' => 0, 'jenis' => $jnsNorm, 'tebal' => (float)$tebal, 'panjang' => (float)$panjang, 'lebar' => (float)$lebar, 'kelompok' => $kelompok, 'isAf' => $isAf, 'kwNorm' => 'kering'];
                }
                $selisihPerGroup[$groupKey]['modalM3'] += $totalM3;
                $selisihPerGroup[$groupKey]['modalBanyak'] += $totalBanyak;
                [$noAkun, $namaAkun] = $this->getNoAkunDanNama($jnsNorm, (float)$tebal, $isAf, 'kering');
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float)$tebal, $isAf, 'kering');

                $tipeVeneer = (float)$tebal < 1 ? '260 Face/Back' : '130 Core';
                $keterangan = "{$tipeVeneer} " . strtolower($jnsNorm) . " uk " . str_replace('.', ',', (float)$tebal);

                $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $totalBanyak, $totalM3, $hargaPatok, 'm');
                $totalKredit += ($totalM3 * $hargaPatok);
            }

            // ============================================================
            // 3. LOGIKA JURNAL PENYEIMBANG SELISIH — PER KELOMPOK
            // ============================================================
            foreach ($selisihPerGroup as $g) {
                $diffM3 = round($g['hasilM3'] - $g['modalM3'], 4);
                if ($diffM3 == 0) continue;
                $kwTarget = ($diffM3 < 0) ? 'kering' : 'jadi';
                $hargaPatok = $this->getHargaPatok($g['jenis'], $g['tebal'], $g['isAf'], $kwTarget);
                [$noAkun, $namaAkun] = $this->getNoAkunDanNama($g['jenis'], $g['tebal'], $g['isAf'], $kwTarget);

                $tipeVeneer = $g['kelompok'] === 'faceback' ? '260 Face/Back' : '130 Core';
                $keterangan = ($diffM3 < 0 ? 'Kekurangan ' : 'Kelebihan ') . "{$tipeVeneer} " . strtolower($g['jenis']) . " uk " . str_replace('.', ',', (float)$g['tebal']);

                if ($diffM3 < 0) {
                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', 0, abs($diffM3), $hargaPatok, 'm');
                    $totalKredit += (abs($diffM3) * $hargaPatok);
                } else {
                    $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', 0, abs($diffM3), $hargaPatok, 'm');
                    $totalDebit += (abs($diffM3) * $hargaPatok);
                }
            }

            // ============================================================
            // 4. KREDIT: Bahan Pendukung / Penolong
            // ============================================================
            if (!empty($produksi->bahanPenolongRepair)) {
                foreach ($produksi->bahanPenolongRepair as $bahan) {
                    $jumlah = (float)($bahan->jumlah ?? 0);
                    if ($jumlah > 0) {
                        $namaBahanRaw = $bahan->bahanPenolong->nama_bahan_penolong ?? 'Bahan';
                        $namaLower = strtolower(trim($namaBahanRaw));
                        
                        $isWHN = false;
                        if (request()) {
                            $host = request()->getHost();
                            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                                $isWHN = true;
                            }
                        }

                        if ($isWHN) {
                            if (str_contains($namaLower, 'solasi') || str_contains($namaLower, 'isolasi')) {
                                $noAkun = '1507.35';
                                $namaAkun = 'isolasi coklat WHN';
                            } else {
                                $noAkun = '1507.35'; // default/fallback
                                $namaAkun = $namaBahanRaw . ' WHN';
                            }
                        } else {
                            if (str_contains($namaLower, 'solasi') || str_contains($namaLower, 'isolasi')) {
                                if (str_contains($namaLower, 'putih')) {
                                    $noAkun = '1507.66';
                                    $namaAkun = 'isolasi putih WJY';
                                } else {
                                    $noAkun = '1507.67';
                                    $namaAkun = 'isolasi coklat WJY';
                                }
                            } elseif (str_contains($namaLower, 'coolant') || str_contains($namaLower, 'oil')) {
                                $noAkun = '1507.58';
                                $namaAkun = 'coolant oil WJY';
                            } elseif (str_contains($namaLower, 'hardener') || str_contains($namaLower, 'hadner')) {
                                $noAkun = '1507.59';
                                $namaAkun = 'hadner WJY';
                            } elseif (str_contains($namaLower, 'cutter')) {
                                $noAkun = '1507.60';
                                $namaAkun = 'Isi cutter WJY';
                            } elseif (str_contains($namaLower, 'staples') || str_contains($namaLower, 'staple')) {
                                $noAkun = '1507.61';
                                $namaAkun = 'Isi Staples WJY';
                            } elseif (str_contains($namaLower, 'tepung')) {
                                $noAkun = '1507.62';
                                $namaAkun = 'Tepung WJY';
                            } elseif (str_contains($namaLower, 'aruki')) {
                                $noAkun = '1507.63';
                                $namaAkun = 'Lem Aruki WJY';
                            } elseif (str_contains($namaLower, 'dover')) {
                                $noAkun = '1507.64';
                                $namaAkun = 'Lem Dover WJY';
                            } elseif (str_contains($namaLower, 'pai')) {
                                $noAkun = '1507.65';
                                $namaAkun = 'Lem PAI WJY';
                            } elseif (str_contains($namaLower, 'stapler')) {
                                $noAkun = '1507.68';
                                $namaAkun = 'stapler WJY';
                            } else {
                                $noAkun = '1507.67'; // default/fallback
                                $namaAkun = $namaBahanRaw . ' WJY';
                            }
                        }

                        $harga = ($bahan->bahanPenolong->harga ?? 41000);
                        $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, '', 'k', $jumlah, '', $harga, 'b');
                        $totalKredit += ($jumlah * $harga);
                    }
                }
            }

            // ============================================================
            // 5. KREDIT: Gaji Pegawai Repair
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, '', 150000, 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // ============================================================
            // 6. DEBIT/PENYEIMBANG: HPP Repair
            // ============================================================
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                if ($selisih > 0) {
                    $jurnalBlockKredit[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'k', '', '', abs($selisih), '');
                } else {
                    $jurnalBlockDebit[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', '', '', abs($selisih), '');
                }
            }

            $rows = array_merge($rows, $jurnalBlockDebit, $jurnalBlockKredit);
            $rows[] = array_fill(0, 14, '');
        }
        return $rows;
    }
}
