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
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk Sheet 2 & 3)
    ) {}

    public function sheets(): array
    {
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA
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

            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil',
                'Keterangan Kerja',
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
                    $p['keterangan'] ?? '-',
                    $p['keterangan_hasil'] ?? '—',
                    $p['keterangan_kerja'] ?? '—',
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
                '',
                '',
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
// SHEET 2: SUMMARY PRODUKSI
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

            foreach ($produksi->detailHasilRepairs as $detail) {
                $p = (float) ($detail->ukuran->panjang ?? 0);
                $l = (float) ($detail->ukuran->lebar   ?? 0);
                $t = (float) ($detail->ukuran->tebal   ?? 0);

                $jenisModel = $detail->modalRepair?->jenisKayu ?? $detail->jenisKayu;
                $jenis = strtoupper($jenisModel->kode_kayu ?? substr($jenisModel->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim((string) $detail->kw));

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

                $jumlahHasil = (int) $detail->jumlah;

                if ($jumlahHasil > 0) {
                    foreach ($detail->rencanaPegawais as $rp) {
                        if ($rp->pegawai) {
                            $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                        }
                    }

                    if ($kwData !== '' && in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $jumlahHasil;
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
// SHEET 3: JURNAL PRODUKSI
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}

    private array $kayuCache     = [];
    private array $kategoriCache = [];
    private array $bahanRefCache = [];

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

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function buildKeterangan(
        float  $panjang,
        float  $lebar,
        float  $tebal,
        string $jenis,
        string $statusKw,
        string $kwRaw = '',
        string $prefix = ''
    ): string {
        $fmt = function (float $val): string {
            if ($val == (int) $val) {
                return (string)(int) $val;
            }
            return str_replace('.', ',', rtrim(number_format($val, 4, '.', ''), '0'));
        };

        $p   = $fmt($panjang);
        $l   = $fmt($lebar);
        $t   = $fmt($tebal);
        $jns = ucfirst(strtolower($this->normalizeJenis($jenis)));
        $kw  = $kwRaw !== '' ? " KW{$kwRaw}" : '';
        $af  = $statusKw === 'af' ? ' AF' : '';
        $pfx = $prefix !== '' ? "{$prefix} " : '';

        return "{$pfx}{$p}x{$l}x{$t} {$jns}{$kw}{$af}";
    }

    private function getIdKayuByNama(string $jenis): ?int
    {
        $jns = $this->normalizeJenis($jenis) === 'sengon' ? 'Sengon' : 'Meranti';
        $key = strtolower($jns);
        if (!array_key_exists($key, $this->kayuCache)) {
            $kayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
            $this->kayuCache[$key] = $kayu?->id;
        }
        return $this->kayuCache[$key];
    }

    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (!array_key_exists($key, $this->kategoriCache)) {
            try {
                $kategori = \App\Models\KategoriBarang::whereRaw("LOWER(nama_kategori) LIKE ?", ["%{$key}%"])->first();
                $this->kategoriCache[$key] = $kategori?->id;
            } catch (\Throwable $e) {
                $this->kategoriCache[$key] = null;
            }
        }
        return $this->kategoriCache[$key];
    }

    private function fetchReferensiVeneer(string $jenis, float $tebal, bool $isAf, string $status): ?\App\Models\ReferensiHargaProduksi
    {
        $idJenisKayu = $this->getIdKayuByNama($jenis);

        if ($isAf) {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer afalan');
            $kw = null;
        } elseif ($status === 'jadi') {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer jadi');
            $kw = 1;
        } else {
            $idKategoriBarang = $this->getIdKategoriBarang('veneer kering');
            $kw = 3;
        }

        if (!$idJenisKayu || !$idKategoriBarang) {
            return null;
        }

        return \App\Models\ReferensiHargaProduksi::findReferensi(
            idJenisKayu: $idJenisKayu,
            idKategoriBarang: $idKategoriBarang,
            kw: $kw,
            tebal: $tebal,
        );
    }

    private function extractAkunVeneer(?\App\Models\ReferensiHargaProduksi $ref): array
    {
        if (!$ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }
        if (!$ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }
        $sub = $ref->subAnakAkun;
        if (!$sub) {
            return ['UNKNOWN', 'UNKNOWN', (float) $ref->harga];
        }
        $nama   = trim($sub->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noAkun = trim($sub->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';

        return [$nama, $noAkun, (float) $ref->harga];
    }

    private function fetchReferensiBahan(string $namaBahan): ?\App\Models\ReferensiHargaProduksi
    {
        $key = strtolower(trim($namaBahan));
        if (array_key_exists($key, $this->bahanRefCache)) {
            return $this->bahanRefCache[$key];
        }

        $idKategoriBarang = $this->getIdKategoriBarang('barang');
        if (!$idKategoriBarang) {
            return $this->bahanRefCache[$key] = null;
        }

        $ref = \App\Models\ReferensiHargaProduksi::with('subAnakAkun')
            ->where('id_kategori_barang', $idKategoriBarang)
            ->where(function ($q) use ($key) {
                $q->whereRaw('LOWER(nama) LIKE ?', ["%{$key}%"])
                    ->orWhereHas('subAnakAkun', function ($q2) use ($key) {
                        $q2->whereRaw('LOWER(nama_sub_anak_akun) LIKE ?', ["%{$key}%"]);
                    });
            })
            ->first();

        return $this->bahanRefCache[$key] = $ref;
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
            ($hitKbk !== '' && $hitKbk !== null) ? strtolower($hitKbk) : '',
            ($banyak === '' || $banyak === null) ? '' : (float) $banyak,
            ($m3     === '' || $m3     === null) ? '' : (float) $m3,
            ($harga  === '' || $harga  === null) ? '' : (float) $harga,
            '',
        ];
    }

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
            // STEP 1: MODAL REPAIR (HANYA DIPROSES JIKA DI-LINK KE HASIL)
            // ============================================================
            $hasilDariModal = collect($produksi->detailHasilRepairs)->filter(function ($h) {
                return $h->modalRepair && $h->modalRepair->ukuran && $h->modalRepair->jenisKayu;
            });

            $groupedHasilModal = $hasilDariModal->groupBy('id_modal_repair');

            foreach ($produksi->modalRepairs as $modal) {
                if (!$modal->ukuran || !$modal->jenisKayu) continue;

                $relatedHasil = $groupedHasilModal->get($modal->id, collect());
                $hasilBanyak  = (int) $relatedHasil->sum('jumlah');

                // *** PERBAIKAN KUNCI ***
                // Jika modal ini TIDAK MEMILIKI HASIL TERHUBUNG (berarti pengerjaannya Manual),
                // SKIP modal ini agar tidak mencetak baris Kehilangan palsu.
                if ($hasilBanyak <= 0) {
                    continue;
                }

                $jnsNorm  = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $kwStatus = strtolower((string) $modal->kw);
                $isAf     = str_contains($kwStatus, 'af');
                $tebal    = (float) $modal->ukuran->tebal;
                $panjang  = (float) $modal->ukuran->panjang;
                $lebar    = (float) $modal->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));

                $modalBanyak = (int) $modal->jumlah;

                $refJadi   = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'jadi');
                $refKering = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'kering');

                [$namaAkunJadi,   $noAkunJadi,   $hargaJadi]   = $this->extractAkunVeneer($refJadi);
                [$namaAkunKering, $noAkunKering, $hargaKering] = $this->extractAkunVeneer($refKering);

                $keteranganNormal = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $kwStatus, $kwRaw);
                $keteranganJadi   = $keteranganNormal . (!$refJadi   ? ' [UNKNOWN]' : '');
                $keteranganKering = $keteranganNormal . (!$refKering ? ' [UNKNOWN]' : '');

                $diffBanyak = $modalBanyak - $hasilBanyak;

                if ($diffBanyak > 0) {
                    // KONDISI KEHILANGAN (Hanya jika ada hasil terhubung tetapi jumlahnya kurang dari modal)
                    $kehilanganBanyak = $diffBanyak;
                    $modalSebanding   = $hasilBanyak;

                    $m3Hasil      = ($panjang * $lebar * $tebal * $hasilBanyak) / 10000000;
                    $m3Modal      = ($panjang * $lebar * $tebal * $modalSebanding) / 10000000;
                    $m3Kehilangan = ($panjang * $lebar * $tebal * $kehilanganBanyak) / 10000000;

                    if ($hasilBanyak > 0) {
                        $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilBanyak, $m3Hasil, $hargaJadi, 'm');
                        $totalDebit += ($m3Hasil * $hargaJadi);
                    }

                    if ($modalSebanding > 0) {
                        $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalSebanding, $m3Modal, $hargaKering, 'm');
                        $totalKredit += ($m3Modal * $hargaKering);
                    }

                    $keteranganKehilangan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $kwStatus, $kwRaw, 'Kehilangan') . (!$refKering ? ' [UNKNOWN]' : '');
                    $jurnalBlockKredit[]  = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKehilangan, 'k', $kehilanganBanyak, $m3Kehilangan, $hargaKering, 'm');
                    $totalKredit += ($m3Kehilangan * $hargaKering);
                } elseif ($diffBanyak < 0) {
                    // KONDISI KELEBIHAN (Hanya jika hasil terhubung melebihi modal)
                    $kelebihanBanyak = abs($diffBanyak);
                    $hasilSebanding  = $modalBanyak;

                    $m3HasilUtama = ($panjang * $lebar * $tebal * $hasilSebanding) / 10000000;
                    $m3Kelebihan  = ($panjang * $lebar * $tebal * $kelebihanBanyak) / 10000000;
                    $m3ModalUtama = ($panjang * $lebar * $tebal * $modalBanyak) / 10000000;

                    $jurnalBlockDebit[] = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilSebanding, $m3HasilUtama, $hargaJadi, 'm');
                    $totalDebit += ($m3HasilUtama * $hargaJadi);

                    $keteranganKelebihan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $kwStatus, $kwRaw, 'Kelebihan') . (!$refJadi ? ' [UNKNOWN]' : '');
                    $jurnalBlockDebit[]  = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganKelebihan, 'd', $kelebihanBanyak, $m3Kelebihan, $hargaJadi, 'm');
                    $totalDebit += ($m3Kelebihan * $hargaJadi);

                    $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalBanyak, $m3ModalUtama, $hargaKering, 'm');
                    $totalKredit += ($m3ModalUtama * $hargaKering);
                } else {
                    // KONDISI NORMAL / BALANCE
                    $m3Hasil = ($panjang * $lebar * $tebal * $hasilBanyak) / 10000000;
                    $m3Modal = ($panjang * $lebar * $tebal * $modalBanyak) / 10000000;

                    $jurnalBlockDebit[]  = $this->makeRow($namaAkunJadi, $tglFormat, $noAkunJadi, $keteranganJadi, 'd', $hasilBanyak, $m3Hasil, $hargaJadi, 'm');
                    $totalDebit += ($m3Hasil * $hargaJadi);

                    $jurnalBlockKredit[] = $this->makeRow($namaAkunKering, $tglFormat, $noAkunKering, $keteranganKering, 'k', $modalBanyak, $m3Modal, $hargaKering, 'm');
                    $totalKredit += ($m3Modal * $hargaKering);
                }
            }

            // ============================================================
            // STEP 2: UKURAN MANUAL (CATAT BERSIH TANPA LABEL KEHILANGAN)
            // ============================================================
            $hasilManual = collect($produksi->detailHasilRepairs)->filter(function ($h) {
                return !$h->modalRepair || !$h->modalRepair->ukuran || !$h->modalRepair->jenisKayu;
            });

            foreach ($hasilManual as $hasil) {
                if (!$hasil->ukuran) continue;

                $jenisModel = $hasil->jenisKayu;
                $jnsNorm  = $this->normalizeJenis($jenisModel?->nama_kayu ?? 'meranti');
                $kwStatus = strtolower((string) $hasil->kw);
                $isAf     = str_contains($kwStatus, 'af');
                $tebal    = (float) $hasil->ukuran->tebal;
                $panjang  = (float) $hasil->ukuran->panjang;
                $lebar    = (float) $hasil->ukuran->lebar;
                $kwRaw    = (string)((int) filter_var($kwStatus, FILTER_SANITIZE_NUMBER_INT));

                $banyak = (int) $hasil->jumlah;
                if ($banyak <= 0) continue;

                $m3 = ($panjang * $lebar * $tebal * $banyak) / 10000000;

                $refJadi = $this->fetchReferensiVeneer($jnsNorm, $tebal, $isAf, 'jadi');
                [$namaAkun, $noAkun, $harga] = $this->extractAkunVeneer($refJadi);

                $keterangan = $this->buildKeterangan($panjang, $lebar, $tebal, $jnsNorm, $kwStatus, $kwRaw) . (!$refJadi ? ' [UNKNOWN]' : '');

                // Push murni ke Debit (Veneer Jadi)
                $jurnalBlockDebit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $banyak, $m3, $harga, 'm');
                $totalDebit += ($m3 * $harga);
            }

            // ============================================================
            // STEP 3: KREDIT BAHAN PENOLONG
            // ============================================================
            if (!empty($produksi->bahanPenolongRepair)) {
                foreach ($produksi->bahanPenolongRepair as $bahan) {
                    $jumlah = (float) ($bahan->jumlah ?? 0);
                    if ($jumlah <= 0) continue;

                    $namaBahanRaw = $bahan->bahanPenolong->nama_bahan_penolong ?? 'Bahan';
                    $refBahan = $this->fetchReferensiBahan($namaBahanRaw);
                    [$namaAkun, $noAkun, $harga] = $this->extractAkunVeneer($refBahan);

                    $keteranganBahan = !$refBahan ? "{$namaBahanRaw} [UNKNOWN]" : '';
                    $jurnalBlockKredit[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keteranganBahan, 'k', $jumlah, '', $harga, 'b');
                    $totalKredit += ($jumlah * $harga);
                }
            }

            // ============================================================
            // STEP 4: KREDIT GAJI PEGAWAI
            // ============================================================
            $jmlPekerja = (int) $produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $jurnalBlockKredit[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, '', 150000, 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // ============================================================
            // STEP 5: PENYEIMBANG OTOMATIS (HPP TRIPLEK)
            // ============================================================
            $hppRow = [];
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                if ($selisih > 0) {
                    $hppRow[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'k', '', '', abs($selisih), '');
                } else {
                    $hppRow[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', '', '', abs($selisih), '');
                }
            }

            $rows   = array_merge($rows, $jurnalBlockDebit, $jurnalBlockKredit, $hppRow);
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
