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
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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

    // ================================================================
    // HELPER: Normalisasi jenis kayu → 'sengon' | 'meranti'
    // ================================================================
    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    // ================================================================
    // HELPER: Deteksi apakah request dari perusahaan WHN
    // Dipindah ke method sendiri agar tidak duplikat di banyak tempat
    // ================================================================
    private function isWHN(): bool
    {
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                return true;
            }
        }
        return false;
    }

    // ================================================================
    // HELPER: Format keterangan lengkap dengan dimensi + jenis + KW
    //
    // Output contoh:
    //   "122x244x0,5 Sengon KW3"
    //   "122x244x0,55 Sengon KW3 AF"
    //   "Kehilangan 122x244x0,5 Meranti KW4"
    //
    // Format angka:
    //   - panjang & lebar: bulatkan jika integer (122, bukan 122,0)
    //   - tebal: koma sebagai desimal, hilangkan trailing zero (0,5 bukan 0,50)
    // ================================================================
    private function buildKeterangan(
        float  $panjang,
        float  $lebar,
        float  $tebal,
        string $jenis,
        string $statusKw,   // 'af' atau 'reguler'
        string $kwRaw = '', // nilai kw asli: '1', '2', '3', '4', dst
        string $prefix = '' // opsional: 'Kehilangan' atau 'Kelebihan'
    ): string {
        // Format angka dimensi: hilangkan trailing zero yang tidak perlu
        $fmt = function (float $val): string {
            if ($val == (int) $val) {
                return (string)(int) $val; // 122.0 → "122"
            }
            // 0.5500 → "0,55" | 0.55 → "0,55"
            return str_replace('.', ',', rtrim(number_format($val, 4, '.', ''), '0'));
        };

        $p   = $fmt($panjang);
        $l   = $fmt($lebar);
        $t   = $fmt($tebal);
        $jns = ucfirst(strtolower($this->normalizeJenis($jenis))); // "Sengon" / "Meranti"
        $kw  = $kwRaw !== '' ? " KW{$kwRaw}" : '';
        $af  = $statusKw === 'af' ? ' AF' : '';
        $pfx = $prefix !== '' ? "{$prefix} " : '';

        return "{$pfx}{$p}x{$l}x{$t} {$jns}{$kw}{$af}";
    }

    // ================================================================
    // HELPER: No Akun & Nama Akun berdasarkan jenis, tebal, isAf, kwNorm
    // ================================================================
    private function getNoAkunDanNama(string $jenis, float $tebal, bool $isAf, string $kwNorm): array
    {
        $jnsNorm       = $this->normalizeJenis($jenis);
        $isSengon      = $jnsNorm === 'sengon';
        $jnsLabel      = strtolower($jnsNorm);
        $isWHN         = $this->isWHN();
        $companySuffix = $isWHN ? 'WHN' : 'WJY';
        $accountSuffix = $isWHN ? '.01' : '.00';
        $tipeVeneer    = $tebal < 1 ? '260 face/back' : '130 core';

        if ($isAf) {
            if ($kwNorm === 'jadi') {
                $noAkun   = $isSengon ? ('1472' . $accountSuffix) : ('1471' . $accountSuffix);
                $namaAkun = "Veneer Jadi ppc {$jnsLabel} {$companySuffix}";
            } else {
                $noAkun   = $isSengon ? ('1452' . $accountSuffix) : ('1451' . $accountSuffix);
                $namaAkun = "Veneer Kering ppc {$jnsLabel} {$companySuffix}";
            }
        } elseif ($kwNorm === 'jadi') {
            $noAkun   = $tebal < 1
                ? ($isSengon ? ('1461' . $accountSuffix) : ('1462' . $accountSuffix))
                : ($isSengon ? ('1466' . $accountSuffix) : ('1467' . $accountSuffix));
            $namaAkun = "Veneer Jadi {$tipeVeneer} {$jnsLabel} {$companySuffix}";
        } else {
            $noAkun   = $tebal < 1
                ? ($isSengon ? ('1441' . $accountSuffix) : ('1442' . $accountSuffix))
                : ($isSengon ? ('1446' . $accountSuffix) : ('1447' . $accountSuffix));
            $namaAkun = "Veneer Kering {$tipeVeneer} {$jnsLabel} {$companySuffix}";
        }

        return [$noAkun, $namaAkun];
    }

    // ================================================================
    // HELPER: Harga patok dari DB, fallback ke hardcode
    // ✅ FIX: hapus duplikasi key 'sengon' (kering sekarang benar 3050000)
    // ================================================================
    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false, string $status = 'jadi'): int
    {
        $jns     = $this->normalizeJenis($jenis);
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
            'meranti' => [
                'faceback' => ['basah' => 8000000, 'kering' => 8500000,  'jadi' => 12500000],
                'core'     => ['basah' => 2100000, 'kering' => 2500000,  'jadi' => 2800000],
            ],
        ];
        return $harga[$jns][$kelompok][$status] ?? 0;
    }

    // ================================================================
    // HELPER: Ambil harga dari tabel HargaVeneer di database
    // ================================================================
    private function getHargaVeneerDb(string $jenis, float $tebal, string $tipeKualitas, bool $isAf = false): int
    {
        $jns       = str_contains(strtolower(trim($jenis)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();

        if (!$jenisKayu) return 0;

        $kelompok = $isAf
            ? ($tebal < 1 ? 'ppc_faceback' : 'ppc_core')
            : ($tebal < 1 ? 'faceback'     : 'core');

        $ukuranOptions = match ($kelompok) {
            'faceback'     => $jns === 'Sengon' ? ['faceback'] : ['face', 'back'],
            'ppc_faceback' => ['ppc_faceback'],
            default        => [$kelompok],
        };

        $kwOptions = array_map(function($opt) {
            return 'KW 1 - ' . ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $tipeKualitasMap = [
            'basah' => 'Veneer Basah',
            'kering' => 'Veneer Kering',
            'jadi' => 'Veneer Jadi',
        ];
        $jenisBarang = $tipeKualitasMap[strtolower($tipeKualitas)] ?? 'Veneer Jadi';

        $hargaVeneer = \App\Models\ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->where('jenis_barang', $jenisBarang)
            ->whereIn('kw', $kwOptions)
            ->first();

        if (!$hargaVeneer) return 0;

        return (int) $hargaVeneer->harga;
    }

    // ================================================================
    // HELPER: Buat satu baris array untuk export
    // ================================================================
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
            ($hitKbk !== '' && $hitKbk !== null) ? strtolower($hitKbk) : '',
            ($banyak === '' || $banyak === null) ? '' : (float) $banyak,
            ($m3     === '' || $m3     === null) ? '' : (float) $m3,
            ($harga  === '' || $harga  === null) ? '' : (float) $harga,
            '',
        ];
    }

    // ================================================================
    // HELPER: Inisialisasi entry $selisihPerGroup hanya sekali
    // ✅ FIX Bug #2: tidak menimpa nilai akumulasi yang sudah berjalan
    // Sekarang menyimpan panjang, lebar, kwRaw untuk keterangan selisih
    // ================================================================
    private function ensureSelisihGroup(
        array  &$selisihPerGroup,
        string $groupKey,
        string $jenis,
        float  $panjang,
        float  $lebar,
        float  $tebal,
        string $kelompok,
        bool   $isAf,
        string $kwRaw
    ): void {
        if (!isset($selisihPerGroup[$groupKey])) {
            $selisihPerGroup[$groupKey] = [
                'hasilM3'     => 0.0,
                'modalM3'     => 0.0,
                'hasilBanyak' => 0,
                'modalBanyak' => 0,
                'jenis'       => $jenis,
                'panjang'     => $panjang,
                'lebar'       => $lebar,
                'tebal'       => $tebal,
                'kelompok'    => $kelompok,
                'isAf'        => $isAf,
                'kwRaw'       => $kwRaw,
            ];
        }
    }

    // ================================================================
    // HELPER: Resolve akun & nama bahan penolong untuk WJY
    // Dipindah ke method sendiri agar array() tidak terlalu panjang
    // Catatan: 'stapler' dicek SEBELUM 'staples' agar tidak false match
    // ================================================================
    private function resolveBahanPenolongWJY(string $namaLower, string $namaBahanRaw): array
    {
        if (str_contains($namaLower, 'solasi') || str_contains($namaLower, 'isolasi')) {
            return str_contains($namaLower, 'putih')
                ? ['1507.66', 'isolasi putih WJY']
                : ['1507.67', 'isolasi coklat WJY'];
        }
        if (str_contains($namaLower, 'coolant') || str_contains($namaLower, 'oil'))     return ['1507.58', 'coolant oil WJY'];
        if (str_contains($namaLower, 'hardener') || str_contains($namaLower, 'hadner')) return ['1507.59', 'hadner WJY'];
        if (str_contains($namaLower, 'cutter'))                                          return ['1507.60', 'Isi cutter WJY'];
        if (str_contains($namaLower, 'stapler'))                                         return ['1507.68', 'stapler WJY'];       // ← sebelum 'staples'
        if (str_contains($namaLower, 'staples') || str_contains($namaLower, 'staple'))  return ['1507.61', 'Isi Staples WJY'];
        if (str_contains($namaLower, 'tepung'))                                          return ['1507.62', 'Tepung WJY'];
        if (str_contains($namaLower, 'aruki'))                                           return ['1507.63', 'Lem Aruki WJY'];
        if (str_contains($namaLower, 'dover'))                                           return ['1507.64', 'Lem Dover WJY'];
        if (str_contains($namaLower, 'pai'))                                             return ['1507.65', 'Lem PAI WJY'];

        return ['1507.67', $namaBahanRaw . ' WJY']; // fallback
    }

    // ================================================================
    // MAIN: Bangun seluruh array baris jurnal untuk export Excel
    // ================================================================
    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat         = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit        = 0.0;
            $totalKredit       = 0.0;
            $jurnalBlockDebit  = [];
            $jurnalBlockKredit = [];

            // ============================================================
            // STEP A: Kumpulkan semua data hasil per group dulu
            // Jangan langsung tulis baris — kita perlu tahu modal dulu
            // sebelum bisa menentukan nilai akhir yang ditulis
            // ============================================================
            $hasilPerGroup = [];

            $groupedHasil = collect($produksi->hasilRepairs)->groupBy(function ($hasil) {
                $modal = $hasil->rencanaRepair?->modalRepairs;
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';

                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower(($hasil->rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $tebal    = (float) $modal->ukuran->tebal;
                $panjang  = (float) $modal->ukuran->panjang;
                $lebar    = (float) $modal->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));

                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });

            foreach ($groupedHasil as $key => $items) {
                if ($key === 'invalid_data') continue;

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;
                $isAf    = ($statusKw === 'af');

                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                // Simpan dulu, belum ditulis ke jurnal
                $hasilPerGroup[$key] = [
                    'jnsNorm'    => $jnsNorm,
                    'panjang'    => $panjang,
                    'lebar'      => $lebar,
                    'tebal'      => $tebal,
                    'statusKw'   => $statusKw,
                    'kwRaw'      => $kwRaw,
                    'isAf'       => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3'    => $totalM3,
                ];
            }

            // ============================================================
            // STEP B: Kumpulkan semua data modal per group
            // ============================================================
            $modalPerGroup = [];

            $groupedModal = collect($produksi->modalRepairs)->groupBy(function ($modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) return 'invalid_data';

                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower($modal->kw ?? '');
                $isAf     = str_contains($kwStatus, 'af') ? 'af' : 'reguler';
                $tebal    = (float) $modal->ukuran->tebal;
                $panjang  = (float) $modal->ukuran->panjang;
                $lebar    = (float) $modal->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));

                return "{$jnsNorm}|{$panjang}|{$lebar}|{$tebal}|{$isAf}|{$kwRaw}";
            });

            foreach ($groupedModal as $key => $items) {
                if ($key === 'invalid_data') continue;

                [$jnsNorm, $panjang, $lebar, $tebal, $statusKw, $kwRaw] = explode('|', $key);
                $panjang = (float) $panjang;
                $lebar   = (float) $lebar;
                $tebal   = (float) $tebal;
                $isAf    = ($statusKw === 'af');

                $totalBanyak = $items->sum('jumlah');
                $totalM3     = ($panjang * $lebar * $tebal * $totalBanyak) / 10000000;

                // Simpan dulu, belum ditulis ke jurnal
                $modalPerGroup[$key] = [
                    'jnsNorm'    => $jnsNorm,
                    'panjang'    => $panjang,
                    'lebar'      => $lebar,
                    'tebal'      => $tebal,
                    'statusKw'   => $statusKw,
                    'kwRaw'      => $kwRaw,
                    'isAf'       => $isAf,
                    'totalBanyak' => $totalBanyak,
                    'totalM3'    => $totalM3,
                ];
            }

            // ============================================================
            // STEP C: Gabungkan semua key yang ada di hasil maupun modal
            // Lalu tulis baris jurnal dengan logika baru:
            //
            // KEHILANGAN (hasil < modal):
            //   DEBIT  hasil  → nilai asli hasil
            //   KREDIT modal  → nilai = hasil (bukan modal penuh)
            //   KREDIT info   → baris "Kehilangan X" dengan nilai selisih
            //
            // KELEBIHAN (hasil > modal):
            //   DEBIT  hasil  → nilai = modal (bukan hasil penuh)
            //   DEBIT  info   → baris "Kelebihan X" dengan nilai selisih
            //   KREDIT modal  → nilai asli modal
            // ============================================================
            $allKeys = array_unique(array_merge(
                array_keys($hasilPerGroup),
                array_keys($modalPerGroup)
            ));

            // Tampung baris selisih sementara
            // Nilai sudah dikap, hanya POSISI push-nya yang dipindah
            $selisihDebitRows  = [];
            $selisihKreditRows = [];

            foreach ($allKeys as $key) {
                $hasil = $hasilPerGroup[$key] ?? null;
                $modal = $modalPerGroup[$key] ?? null;

                $meta     = $hasil ?? $modal;
                $jnsNorm  = $meta['jnsNorm'];
                $panjang  = $meta['panjang'];
                $lebar    = $meta['lebar'];
                $tebal    = $meta['tebal'];
                $statusKw = $meta['statusKw'];
                $kwRaw    = $meta['kwRaw'];
                $isAf     = $meta['isAf'];

                $hasilM3     = $hasil['totalM3']     ?? 0.0;
                $hasilBanyak = $hasil['totalBanyak'] ?? 0;
                $modalM3     = $modal['totalM3']     ?? 0.0;
                $modalBanyak = $modal['totalBanyak'] ?? 0;

                $diffM3     = round($hasilM3 - $modalM3, 4);
                $diffBanyak = $hasilBanyak - $modalBanyak;

                [$noAkunJadi,   $namaAkunJadi]   = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, 'jadi');
                [$noAkunKering, $namaAkunKering] = $this->getNoAkunDanNama($jnsNorm, $tebal, $isAf, 'kering');
                $hargaJadi   = $this->getHargaPatok($jnsNorm, $tebal, $isAf, 'jadi');
                $hargaKering = $this->getHargaPatok($jnsNorm, $tebal, $isAf, 'kering');

                $keteranganNormal = $this->buildKeterangan(
                    $panjang,
                    $lebar,
                    $tebal,
                    $jnsNorm,
                    $statusKw,
                    $kwRaw
                );

                if ($diffM3 < 0) {
                    // ── KEHILANGAN ─────────────────────────────────────────
                    // 1. DEBIT hasil (nilai asli)
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganNormal,
                        'd',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($hasilM3 * $hargaJadi);

                    // 2. KREDIT modal dikap ke nilai hasil
                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganNormal,
                        'k',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($hasilM3 * $hargaKering);

                    // 3. Tampung kehilangan dulu, push belakangan
                    $keteranganKehilangan = $this->buildKeterangan(
                        $panjang,
                        $lebar,
                        $tebal,
                        $jnsNorm,
                        $statusKw,
                        $kwRaw,
                        'Kehilangan'
                    );
                    $selisihKreditRows[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganKehilangan,
                        'k',
                        abs($diffBanyak),
                        abs($diffM3),
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += (abs($diffM3) * $hargaKering);
                } elseif ($diffM3 > 0) {
                    // ── KELEBIHAN ──────────────────────────────────────────
                    // 1. DEBIT hasil dikap ke nilai modal
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganNormal,
                        'd',
                        $modalBanyak,
                        $modalM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($modalM3 * $hargaJadi);

                    // 2. Tampung kelebihan dulu, push belakangan
                    $keteranganKelebihan = $this->buildKeterangan(
                        $panjang,
                        $lebar,
                        $tebal,
                        $jnsNorm,
                        $statusKw,
                        $kwRaw,
                        'Kelebihan'
                    );
                    $selisihDebitRows[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganKelebihan,
                        'd',
                        abs($diffBanyak),
                        abs($diffM3),
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += (abs($diffM3) * $hargaJadi);

                    // 3. KREDIT modal (nilai asli)
                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganNormal,
                        'k',
                        $modalBanyak,
                        $modalM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($modalM3 * $hargaKering);
                } else {
                    // ── BALANCE ────────────────────────────────────────────
                    $jurnalBlockDebit[] = $this->makeRow(
                        $namaAkunJadi,
                        $tglFormat,
                        $noAkunJadi,
                        $keteranganNormal,
                        'd',
                        $hasilBanyak,
                        $hasilM3,
                        $hargaJadi,
                        'm'
                    );
                    $totalDebit += ($hasilM3 * $hargaJadi);

                    $jurnalBlockKredit[] = $this->makeRow(
                        $namaAkunKering,
                        $tglFormat,
                        $noAkunKering,
                        $keteranganNormal,
                        'k',
                        $modalBanyak,
                        $modalM3,
                        $hargaKering,
                        'm'
                    );
                    $totalKredit += ($modalM3 * $hargaKering);
                }
            }

            // ── Push selisih setelah semua hasil & modal, sebelum bahan penolong ──
            foreach ($selisihDebitRows  as $row) $jurnalBlockDebit[]  = $row;
            foreach ($selisihKreditRows as $row) $jurnalBlockKredit[] = $row;

            // ── Bagian 4, 5, 6 lanjut seperti biasa di bawah ini ──

            // ============================================================
            // 4. KREDIT: Bahan Penolong (tidak berubah)
            // ============================================================
            if (!empty($produksi->bahanPenolongRepair)) {
                $isWHN = $this->isWHN();

                foreach ($produksi->bahanPenolongRepair as $bahan) {
                    $jumlah = (float) ($bahan->jumlah ?? 0);
                    if ($jumlah <= 0) continue;

                    $namaBahanRaw = $bahan->bahanPenolong->nama_bahan_penolong ?? 'Bahan';
                    $namaLower    = strtolower(trim($namaBahanRaw));

                    if ($isWHN) {
                        $noAkun   = '1507.35';
                        $namaAkun = (str_contains($namaLower, 'solasi') || str_contains($namaLower, 'isolasi'))
                            ? 'isolasi coklat WHN'
                            : $namaBahanRaw . ' WHN';
                    } else {
                        [$noAkun, $namaAkun] = $this->resolveBahanPenolongWJY($namaLower, $namaBahanRaw);
                    }

                    $harga = (float) ($bahan->bahanPenolong->harga ?? 41000);
                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, '', 'k', $jumlah, '', $harga, 'b');
                    $totalKredit += ($jumlah * $harga);
                }
            }

            // ============================================================
            // 5. KREDIT: Gaji Pegawai (tidak berubah)
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, '', 150000, 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // ============================================================
            // 6. PENYEIMBANG: HPP Repair (tidak berubah)
            // ============================================================
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                if ($selisih > 0) {
                    $jurnalBlockKredit[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'k', '', '', abs($selisih), '');
                } else {
                    $jurnalBlockDebit[]  = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', '', '', abs($selisih), '');
                }
            }

            $rows   = array_merge($rows, $jurnalBlockDebit, $jurnalBlockKredit);
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
