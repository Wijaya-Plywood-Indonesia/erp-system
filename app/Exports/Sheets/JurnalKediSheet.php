<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalKediSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithMapping
{
    protected array $dataKedi;
    protected int $rowIndex = 0;

    // Cache agar tidak query DB berulang untuk key yang sama
    private array $refCache = [];

    public function __construct($dataKedi)
    {
        $this->dataKedi = $dataKedi;
    }

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, 'B' => 20, 'C' => 15, 'D' => 12, 'E' => 8,
            'F' => 18, 'G' => 20, 'H' => 45, 'I' => 6,  'J' => 10,
            'K' => 10, 'L' => 15, 'M' => 15, 'N' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    // =========================================================================
    // DATABASE REFERENCE HELPERS
    // =========================================================================

    private function getIdKayuByNama(string $namaLike): ?int
    {
        try {
            $kayu = \App\Models\JenisKayu::whereRaw("LOWER(nama_kayu) LIKE ?", ["%{$namaLike}%"])->first();
            return $kayu?->id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ambil referensi harga dari DB.
     *
     * Mapping jenis_barang:
     *   'veneer jadi'   → debit kw 1,2
     *   'veneer kering' → debit kw 3,4
     *   'veneer afalan' → debit kw af  (juga dipakai untuk kredit af)
     *   'veneer basah'  → kredit reguler
     *
     * KW di tabel referensi:
     *   veneer jadi   → kolom kw = 'jadi'     (dibedakan 260/130 via nama akun)
     *   veneer kering → kolom kw = 'Face/Back' atau 'Core'
     *   veneer basah  → kolom kw = 'Face/Back' atau 'Core'
     *   veneer afalan → kolom kw = 'Face/Back' atau 'Core'
     *
     * Jenis kayu selain sengon & meranti → fallback ke referensi meranti.
     * id_ukuran di tabel dikosongkan semua → pembeda 260/130 dari nama sub akun.
     */
    private function fetchReferensi(string $jenisKayu, float $tebal, string $jenisBarang, string $kw): ?\App\Models\ReferensiHargaProduksi
    {
        $cacheKey = strtolower("{$jenisKayu}_{$tebal}_{$jenisBarang}_{$kw}");
        if (array_key_exists($cacheKey, $this->refCache)) {
            return $this->refCache[$cacheKey];
        }

        // Hanya sengon & meranti pakai ref sendiri, selain itu fallback ke meranti
        $jenisUntukRef = in_array(strtolower(trim($jenisKayu)), ['sengon', 'meranti'])
            ? $jenisKayu
            : 'meranti';

        $idJenisKayu = $this->getIdKayuByNama($jenisUntukRef);

        $q = \App\Models\ReferensiHargaProduksi::with(['subAnakAkun', 'ukuran'])
            ->whereRaw("LOWER(jenis_barang) = ?", [strtolower($jenisBarang)]);

        if ($idJenisKayu) {
            $q->where('id_jenis_kayu', $idJenisKayu);
        }

        $all = $q->get();
        if ($all->isEmpty()) {
            return $this->refCache[$cacheKey] = null;
        }

        // ── Filter KW ────────────────────────────────────────────────────────
        if (strtolower($jenisBarang) === 'veneer jadi') {
            // Tabel pakai kw = 'jadi', bedakan 260/130 dari nama akun berdasarkan tebal
            $poolJadi = $all->filter(
                fn($item) => strtolower(trim($item->kw ?? '')) === 'jadi'
            );
            $pool = $poolJadi->isNotEmpty() ? $poolJadi : $all;

            $keyword  = ($tebal < 1) ? '260' : '130';
            $filtered = $pool->filter(
                fn($item) => str_contains(
                    strtolower($item->subAnakAkun?->nama_sub_anak_akun ?? ''),
                    $keyword
                )
            );

            return $this->refCache[$cacheKey] = $filtered->first() ?? $pool->first();
        }

        // Untuk veneer kering, basah, afalan: filter via kolom kw (Face/Back atau Core)
        $filteredKw = $all->filter(
            fn($item) => strtolower(trim($item->kw ?? '')) === strtolower($kw)
        );
        $pool = $filteredKw->isNotEmpty()
            ? $filteredKw
            : $all->filter(fn($i) => empty(trim($i->kw ?? '')));
        if ($pool->isEmpty()) {
            $pool = $all;
        }

        // ── Filter ukuran tebal (prioritas spesifik, fallback tanpa ukuran) ──
        $filteredUkuran = $pool->filter(
            fn($item) => $item->ukuran && (float) $item->ukuran->tebal == $tebal
        );
        if ($filteredUkuran->isNotEmpty()) {
            return $this->refCache[$cacheKey] = $filteredUkuran->first();
        }

        $tanpaUkuran = $pool->filter(fn($i) => is_null($i->id_ukuran));
        return $this->refCache[$cacheKey] = $tanpaUkuran->first() ?? $pool->first();
    }

    /**
     * Ekstrak nama akun, no akun, dan harga dari hasil fetchReferensi.
     * Jika ref null → kembalikan 'UNKNOWN' agar mudah dideteksi di Excel.
     */
    private function extractAkun(?\App\Models\ReferensiHargaProduksi $ref): array
    {
        if (!$ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }

        if (!$ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }

        $sub    = $ref->subAnakAkun;
        $nama   = trim($sub?->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noAkun = trim($sub?->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $harga  = (float) $ref->harga;

        return [$nama, $noAkun, $harga];
    }

    // =========================================================================
    // FORMATTING HELPERS
    // =========================================================================

    private function parseDimensi(string $ukuranStr): array
    {
        $dimensi = explode('x', str_replace([' ', 'mm', 'MM'], '', strtolower($ukuranStr)));
        return [
            'p' => (float) ($dimensi[0] ?? 0),
            'l' => (float) ($dimensi[1] ?? 0),
            't' => (float) ($dimensi[2] ?? 0),
        ];
    }

    private function expandJenis(string $jenis): string
    {
        $map = [
            's'  => 'sengon', 'j'  => 'jabon',  'm'  => 'meranti',
            'p'  => 'pinus',  'k'  => 'keruing', 'mh' => 'mahoni', 'wr' => 'waru',
        ];
        return $map[strtolower(trim($jenis))] ?? strtolower(trim($jenis));
    }

    private function isKwAf(mixed $kw): bool
    {
        return !in_array((int) $kw, [1, 2, 3, 4]);
    }

    private function hitungM3(\Illuminate\Support\Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $dim    = $this->parseDimensi($item['ukuran'] ?? '');
            $jumlah = (int) ($item['jumlah'] ?? 0);
            $total += ($dim['p'] * $dim['l'] * $dim['t'] * $jumlah) / 10_000_000;
        }
        return $total;
    }

    private function formatUkuran(array $dim): string
    {
        return "{$dim['p']} x {$dim['l']} x {$dim['t']}";
    }

    private function makeRow(
        string $namaAkun, string $noAkun, string $tgl, string $namaProduksi,
        string $keterangan, string $map, string $hitKbk,
        $banyak, $m3, $harga, $total
    ): array {
        return [$namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $keterangan, $map, $hitKbk, $banyak, $m3, $harga, $total];
    }

    // =========================================================================
    // MAIN ARRAY
    // =========================================================================

    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        if (empty($this->dataKedi)) return $rows;

        $totalPegawai = 0;
        $allBongkars  = [];
        $allMasuks    = [];
        $tglProduksi  = '';

        foreach ($this->dataKedi as $produksi) {
            $totalPegawai += $produksi['total_pekerja'] ?? 0;

            if (empty($tglProduksi)) {
                $rawTgl = str_replace('/', '-',
                    $produksi['tanggal_actual_bongkar']
                        ?? $produksi['tanggal_keluar']
                        ?? $produksi['tanggal_masuk']
                        ?? ''
                );
                try {
                    $tglProduksi = \Carbon\Carbon::createFromFormat('d/m/Y', $rawTgl)->format('d-m-Y');
                } catch (\Exception $e) {
                    $tglProduksi = $rawTgl;
                }
            }

            foreach ($produksi['detail_bongkar'] ?? [] as $db) $allBongkars[] = $db;
            foreach ($produksi['detail_masuk']   ?? [] as $dm) $allMasuks[]   = $dm;
        }

        $namaProduksi = 'bongkar';

        $bongkarsReguler = array_filter($allBongkars, fn($d) => !$this->isKwAf($d['kw'] ?? 0));
        $bongkarsAf      = array_filter($allBongkars, fn($d) =>  $this->isKwAf($d['kw'] ?? 0));

        $makeKey = function ($d) {
            $dim = $this->parseDimensi($d['ukuran'] ?? '');
            return $this->expandJenis(trim($d['jenis_kayu'] ?? ''))
                . '_' . $dim['p']
                . '_' . $dim['l']
                . '_' . $dim['t'];
        };

        $groupedBongkarsReguler = collect($bongkarsReguler)->groupBy($makeKey);
        $groupedBongkarsAf      = collect($bongkarsAf)->groupBy($makeKey);
        $groupedMasuks          = collect($allMasuks)->groupBy($makeKey);

        $totalDebit  = 0;
        $totalKredit = 0;
        $debitRows   = [];
        $creditRows  = [];

        $allKeys = collect(array_keys($groupedMasuks->toArray()))
            ->merge(array_keys($groupedBongkarsReguler->toArray()))
            ->merge(array_keys($groupedBongkarsAf->toArray()))
            ->unique();

        foreach ($allKeys as $key) {
            $dbsReguler = $groupedBongkarsReguler->get($key, collect());
            $dbsAf      = $groupedBongkarsAf->get($key, collect());
            $dms        = $groupedMasuks->get($key, collect());

            $sample = $dbsReguler->first() ?? $dbsAf->first() ?? $dms->first();
            if (!$sample) continue;

            $jenisAsli     = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
            $dim           = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal         = $dim['t'];
            $ukuranLengkap = $this->formatUkuran($dim);
            $tipeLabel     = ($tebal < 1) ? '260 f/b' : '130 core';

            // KW untuk filter kolom kw di tabel referensi
            $kwReguler = ($tebal < 1) ? 'face/back' : 'core';

            // ── Ambil semua referensi dari DB ─────────────────────────────────
            $refJadi    = $this->fetchReferensi($jenisAsli, $tebal, 'veneer jadi',   $kwReguler);
            $refKering  = $this->fetchReferensi($jenisAsli, $tebal, 'veneer kering', $kwReguler);
            $refAf      = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', $kwReguler);
            $refBasah   = $this->fetchReferensi($jenisAsli, $tebal, 'veneer basah',  $kwReguler);
            $refBasahAf = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', $kwReguler);

            [$akunJadiNama,    $akunJadiNo,    $hargaJadi]    = $this->extractAkun($refJadi);
            [$akunKeringNama,  $akunKeringNo,  $hargaKering]  = $this->extractAkun($refKering);
            [$akunAfNama,      $akunAfNo,      $hargaAf]      = $this->extractAkun($refAf);
            [$akunBasahNama,   $akunBasahNo,   $hargaBasah]   = $this->extractAkun($refBasah);
            [$akunBasahAfNama, $akunBasahAfNo, $hargaBasahAf] = $this->extractAkun($refBasahAf);

            // Keterangan debit
            $ketJadi   = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}" . (!$refJadi   ? ' [UNKNOWN]' : '');
            $ketKering = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}" . (!$refKering ? ' [UNKNOWN]' : '');
            $ketAf     = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af" . (!$refAf  ? ' [UNKNOWN]' : '');

            // ── Kelompokkan item per kw ───────────────────────────────────────
            $kwJadiItems   = $dbsReguler->filter(fn($d) => in_array((int) $d['kw'], [1, 2]));
            $kwKeringItems = $dbsReguler->filter(fn($d) => in_array((int) $d['kw'], [3, 4]));
            $kwAfItems     = collect($dbsAf);

            $jadiOutputIsi   = $kwJadiItems->sum('jumlah');
            $keringOutputIsi = $kwKeringItems->sum('jumlah');
            $afOutputIsi     = $kwAfItems->sum('jumlah');

            $totalHasilIsi = $jadiOutputIsi + $keringOutputIsi + $afOutputIsi;
            $totalMasukIsi = $dms->sum('jumlah');

            $m3JadiTotal   = $this->hitungM3($kwJadiItems);
            $m3KeringTotal = $this->hitungM3($kwKeringItems);
            $m3AfTotal     = $this->hitungM3($kwAfItems);
            $totalMasukM3  = $this->hitungM3($dms);

            $hilang = $totalMasukIsi - $totalHasilIsi;

            $regJadiIsi   = $jadiOutputIsi;
            $regKeringIsi = $keringOutputIsi;
            $regAfIsi     = $afOutputIsi;
            $m3Jadi       = $m3JadiTotal;
            $m3Kering     = $m3KeringTotal;
            $m3Af         = $m3AfTotal;

            $kelebihanDebitRow = null;

            // ── Penanganan kelebihan output ───────────────────────────────────
            if ($hilang < 0) {
                $kelebihan = abs($hilang);

                if ($keringOutputIsi >= $jadiOutputIsi && $keringOutputIsi >= $afOutputIsi) {
                    $regKeringIsi   = max(0, $keringOutputIsi - $kelebihan);
                    $m3Kering       = $keringOutputIsi > 0 ? ($regKeringIsi / $keringOutputIsi) * $m3KeringTotal : 0;
                    $m3Kelebihan    = $keringOutputIsi > 0 ? ($kelebihan   / $keringOutputIsi) * $m3KeringTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel    = round($m3KelebihanRnd * $hargaKering, 0);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunKeringNama, $akunKeringNo, $tglProduksi, $namaProduksi,
                        $ketKering . " (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaKering, $subtotalKel
                    );

                } elseif ($jadiOutputIsi >= $keringOutputIsi && $jadiOutputIsi >= $afOutputIsi) {
                    $regJadiIsi     = max(0, $jadiOutputIsi - $kelebihan);
                    $m3Jadi         = $jadiOutputIsi > 0 ? ($regJadiIsi / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $m3Kelebihan    = $jadiOutputIsi > 0 ? ($kelebihan  / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel    = round($m3KelebihanRnd * $hargaJadi, 0);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunJadiNama, $akunJadiNo, $tglProduksi, $namaProduksi,
                        $ketJadi . " (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaJadi, $subtotalKel
                    );

                } else {
                    $regAfIsi       = max(0, $afOutputIsi - $kelebihan);
                    $m3Af           = $afOutputIsi > 0 ? ($regAfIsi  / $afOutputIsi) * $m3AfTotal : 0;
                    $m3Kelebihan    = $afOutputIsi > 0 ? ($kelebihan / $afOutputIsi) * $m3AfTotal : 0;
                    $m3KelebihanRnd = round($m3Kelebihan, 4);
                    $subtotalKel    = round($m3KelebihanRnd * $hargaAf, 0);
                    $kelebihanDebitRow = $this->makeRow(
                        $akunAfNama, $akunAfNo, $tglProduksi, $namaProduksi,
                        $ketAf . " (kelebihan {$kelebihan})", 'd', 'm',
                        $kelebihan, $m3KelebihanRnd, $hargaAf, $subtotalKel
                    );
                }
            }

            // ── DEBIT ─────────────────────────────────────────────────────────
            if ($regJadiIsi > 0) {
                $m3JadiRnd   = round($m3Jadi, 4);
                $subtotal    = round($m3JadiRnd * $hargaJadi, 0);
                $debitRows[] = $this->makeRow($akunJadiNama, $akunJadiNo, $tglProduksi, $namaProduksi, $ketJadi, 'd', 'm', $regJadiIsi, $m3JadiRnd, $hargaJadi, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($regKeringIsi > 0) {
                $m3KeringRnd = round($m3Kering, 4);
                $subtotal    = round($m3KeringRnd * $hargaKering, 0);
                $debitRows[] = $this->makeRow($akunKeringNama, $akunKeringNo, $tglProduksi, $namaProduksi, $ketKering, 'd', 'm', $regKeringIsi, $m3KeringRnd, $hargaKering, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($regAfIsi > 0) {
                $m3AfRnd     = round($m3Af, 4);
                $subtotal    = round($m3AfRnd * $hargaAf, 0);
                $debitRows[] = $this->makeRow($akunAfNama, $akunAfNo, $tglProduksi, $namaProduksi, $ketAf, 'd', 'm', $regAfIsi, $m3AfRnd, $hargaAf, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($kelebihanDebitRow) {
                $debitRows[] = $kelebihanDebitRow;
                $totalDebit += $kelebihanDebitRow[13];
            }

            // ── KREDIT ────────────────────────────────────────────────────────
            if ($hilang >= 0) {
                if ($jadiOutputIsi > 0 || $keringOutputIsi > 0) {
                    $m3Reguler    = round($m3JadiTotal + $m3KeringTotal, 4);
                    $subtotal     = round($m3Reguler * $hargaBasah, 0);
                    $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, '', 'k', 'm', ($jadiOutputIsi + $keringOutputIsi), $m3Reguler, $hargaBasah, $subtotal);
                    $totalKredit += $subtotal;
                }

                if ($afOutputIsi > 0) {
                    $m3AfRound    = round($m3AfTotal, 4);
                    $subtotal     = round($m3AfRound * $hargaBasahAf, 0);
                    $creditRows[] = $this->makeRow($akunBasahAfNama, $akunBasahAfNo, $tglProduksi, $namaProduksi, 'af', 'k', 'm', $afOutputIsi, $m3AfRound, $hargaBasahAf, $subtotal);
                    $totalKredit += $subtotal;
                }

                if ($hilang > 0) {
                    $m3Hilang       = round($totalMasukM3 - ($m3JadiTotal + $m3KeringTotal + $m3AfTotal), 4);
                    if ($m3Hilang < 0) $m3Hilang = 0;
                    $subtotalHilang = round($m3Hilang * $hargaBasah, 0);
                    $creditRows[]   = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, 'kehilangan ' . $hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                    $totalKredit   += $subtotalHilang;
                }
            } else {
                if ($totalMasukIsi > 0) {
                    $m3MasukRnd   = round($totalMasukM3, 4);
                    $subtotal     = round($m3MasukRnd * $hargaBasah, 0);
                    $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, '', 'k', 'm', $totalMasukIsi, $m3MasukRnd, $hargaBasah, $subtotal);
                    $totalKredit += $subtotal;
                }
            }
        }

        foreach ($debitRows  as $r) $rows[] = $r;
        foreach ($creditRows as $r) $rows[] = $r;

        if ($totalPegawai > 0) {
            $rows[]       = $this->makeRow('Hutang Gaji', '2231.00', $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
            $totalKredit += ($totalPegawai * 150000);
        }

        // ── HPP penyeimbang otomatis ──────────────────────────────────────────
        $nilaiHpp = $totalKredit - $totalDebit;
        if (round(abs($nilaiHpp), 0) != 0) {
            $mapHpp     = $nilaiHpp > 0 ? 'd' : 'k';
            $nominalHpp = round(abs($nilaiHpp), 0);
            $rows[]     = $this->makeRow('hpp', '6111.00', $tglProduksi, $namaProduksi, '', $mapHpp, '', '', '', $nominalHpp, $nominalHpp);
        }

        return $rows;
    }

    public function map($row): array
    {
        $this->rowIndex++;

        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r       = $this->rowIndex;
        $row[13] = "=ROUND(IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r})), 0)";

        return $row;
    }
}