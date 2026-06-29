<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithMapping
{
    protected array $dataProduksi;
    protected int $rowIndex = 0;

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = $dataProduksi;
    }

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45,
            'B' => 20,
            'C' => 15,
            'D' => 12,
            'E' => 8,
            'F' => 18,
            'G' => 20,
            'H' => 45,
            'I' => 6,
            'J' => 10,
            'K' => 10,
            'L' => 15,
            'M' => 15,
            'N' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ];
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray($borderStyle);

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
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
     * Fetch referensi berdasarkan:
     * - $jenisKayu  : nama jenis kayu (sengon, meranti, dll)
     * - $tebal      : tebal veneer untuk mencocokkan ukuran
     * - $jenisBarang: 'veneer jadi' | 'veneer kering' | 'veneer basah' | 'veneer afalan'
     * - $kw         : 'face/back' (tebal < 1) | 'core' (tebal >= 1)
     *
     * Mapping logika produksi:
     *   kw hasil 1,2 → veneer jadi   + kw face/back atau core
     *   kw hasil 3,4 → veneer kering + kw face/back atau core
     *   kw hasil af  → veneer afalan + kw face/back atau core
     *   modal masuk  → veneer basah  + kw face/back atau core
     */
    private function fetchReferensi(string $jenisKayu, float $tebal, string $jenisBarang, string $kw): ?\App\Models\ReferensiHargaProduksi
{
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
    if ($all->isEmpty()) return null;

    // ── 1. Filter KW ──────────────────────────────────────────────────────────
    if (strtolower($jenisBarang) === 'veneer jadi') {
        // Tabel referensi pakai kw = 'jadi' untuk semua veneer jadi,
        // jadi filter dulu pakai 'jadi', lalu bedakan 260/130 dari nama akun
        $poolJadi = $all->filter(
            fn($item) => strtolower(trim($item->kw ?? '')) === 'jadi'
        );
        $pool = $poolJadi->isNotEmpty() ? $poolJadi : $all;

        // Bedakan 260 face/back vs 130 core dari nama sub akun
        $keyword  = ($tebal < 1) ? '260' : '130';
        $filtered = $pool->filter(
            fn($item) => str_contains(
                strtolower($item->subAnakAkun?->nama_sub_anak_akun ?? ''),
                $keyword
            )
        );
        // Fallback ke semua pool jika keyword tidak ketemu
        return $filtered->first() ?? $pool->first();
    }

    // Untuk veneer kering, basah, afalan: filter pakai kw face/back atau core
    $filteredKw = $all->filter(
        fn($item) => strtolower(trim($item->kw ?? '')) === strtolower($kw)
    );
    $pool = $filteredKw->isNotEmpty()
        ? $filteredKw
        : $all->filter(fn($i) => empty(trim($i->kw ?? '')));
    if ($pool->isEmpty()) $pool = $all;

    // ── 2. Filter ukuran tebal ────────────────────────────────────────────────
    $filteredUkuran = $pool->filter(
        fn($item) => $item->ukuran && (float) $item->ukuran->tebal == $tebal
    );
    if ($filteredUkuran->isNotEmpty()) {
        return $filteredUkuran->first();
    }

    $tanpaUkuran = $pool->filter(fn($i) => is_null($i->id_ukuran));
    return $tanpaUkuran->first() ?? $pool->first();
}

    private function extractAkun(?\App\Models\ReferensiHargaProduksi $ref): array
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
        $namaAkun = trim($sub->nama_sub_anak_akun ?? '');
        $noAkun   = trim($sub->kode_sub_anak_akun ?? '');

        if ($namaAkun === '') $namaAkun = 'UNKNOWN';
        if ($noAkun   === '') $noAkun   = 'UNKNOWN';

        return [$namaAkun, $noAkun, (float) $ref->harga];
    }

    // =========================================================================
    // FORMATTING HELPERS
    // =========================================================================

    private function expandJenis(string $jenis): string
    {
        $map = [
            's'  => 'sengon',
            'j'  => 'jabon',
            'm'  => 'meranti',
            'p'  => 'pinus',
            'k'  => 'keruing',
            'mh' => 'mahoni',
            'wr' => 'waru',
        ];
        $jns = strtolower(trim($jenis));
        return $map[$jns] ?? $jns;
    }

    private function isKwAf(mixed $kw): bool
    {
        return !in_array((int) $kw, [1, 2, 3, 4]);
    }

    private function hitungM3(\Illuminate\Support\Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $p      = (float) ($item['ukuran']['p'] ?? $item['ukuran']['panjang'] ?? 0);
            $l      = (float) ($item['ukuran']['l'] ?? $item['ukuran']['lebar']   ?? 0);
            $t      = (float) ($item['ukuran']['t'] ?? $item['ukuran']['tebal']   ?? 0);
            $jumlah = (int)   ($item['isi'] ?? 0);
            $total += ($p * $l * $t * $jumlah) / 10_000_000;
        }
        return $total;
    }

    private function formatUkuran(array $ukuran): string
    {
        $p = $ukuran['p'] ?? ($ukuran['panjang'] ?? '');
        $l = $ukuran['l'] ?? ($ukuran['lebar']   ?? '');
        $t = $ukuran['t'] ?? ($ukuran['tebal']   ?? '');
        return "{$p} x {$l} x {$t}";
    }

    private function makeRow(string $namaAkun, string $noAkun, string $tgl, string $namaProduksi, string $keterangan, string $map, string $hitKbk, $banyak, $m3, $harga, $total): array
    {
        return [$namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $keterangan, $map, $hitKbk, $banyak, $m3, $harga, $total];
    }

    // =========================================================================
    // MAIN ARRAY
    // =========================================================================

    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        $groupedByShift = collect($this->dataProduksi)->groupBy(function ($item) {
            return strtoupper($item['shift'] ?? 'PAGI');
        });

        foreach (['PAGI', 'MALAM'] as $shiftName) {
            $shiftData = $groupedByShift->get($shiftName, collect());
            if ($shiftData->isEmpty()) continue;

            $totalPegawai = 0;
            $allHasils    = [];
            $allMasuks    = [];
            $tglProduksi  = '';

            foreach ($shiftData as $produksi) {
                $totalPegawai += $produksi['jumlah_pekerja'] ?? 0;
                foreach ($produksi['detail_hasils'] ?? [] as $dh) $allHasils[] = $dh;
                foreach ($produksi['detail_masuks'] ?? [] as $dm) $allMasuks[] = $dm;

                if (empty($tglProduksi)) {
                    $rawTgl = $produksi['tanggal_produksi'] ?? $produksi['tanggal'] ?? $produksi['tgl_produksi'] ?? $produksi['date'] ?? '';
                    if (!empty($rawTgl)) {
                        $rawTgl = str_replace('/', '-', $rawTgl);
                        try {
                            $tglProduksi = \Carbon\Carbon::parse($rawTgl)->format('d-m-Y');
                        } catch (\Exception $e) {
                            $tglProduksi = $rawTgl;
                        }
                    }
                }
            }

            $hasilsReguler = array_filter($allHasils, fn($d) => !$this->isKwAf($d['kw'] ?? 0));
            $hasilsAf      = array_filter($allHasils, fn($d) =>  $this->isKwAf($d['kw'] ?? 0));

            $makeKey = fn($d) => $this->expandJenis(trim($d['jenis_kayu'] ?? ''))
                . '_' . (float) ($d['ukuran']['t'] ?? $d['ukuran']['tebal'] ?? 0);

            $groupedHasilsReguler = collect($hasilsReguler)->groupBy($makeKey);
            $groupedHasilsAf      = collect($hasilsAf)->groupBy($makeKey);
            $groupedMasuks        = collect($allMasuks)->groupBy($makeKey);

            $totalDebit   = 0;
            $totalKredit  = 0;
            $debitRows    = [];
            $creditRows   = [];
            $namaProduksi = 'dryer ' . strtolower($shiftName);

            $allKeys = collect(array_keys($groupedMasuks->toArray()))
                ->merge(array_keys($groupedHasilsReguler->toArray()))
                ->merge(array_keys($groupedHasilsAf->toArray()))
                ->unique();

            foreach ($allKeys as $key) {
                $dhsReguler = $groupedHasilsReguler->get($key, collect());
                $dhsAf      = $groupedHasilsAf->get($key, collect());
                $dms        = $groupedMasuks->get($key, collect());

                $sample = $dhsReguler->first() ?? $dhsAf->first() ?? $dms->first();
                if (!$sample) continue;

                $jenisAsli     = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $tebal         = (float) ($sample['ukuran']['t'] ?? 0);
                $ukuranLengkap = $this->formatUkuran($sample['ukuran'] ?? []);
                $tipeLabel     = ($tebal < 1) ? '260 f/b' : '130 core';

                // KW di master: tebal < 1 → face/back, tebal >= 1 → core
                $kwReguler = ($tebal < 1) ? 'face/back' : 'core';

                // --- PENCARIAN REFERENSI DATABASE ---
                $refJadi     = $this->fetchReferensi($jenisAsli, $tebal, 'veneer jadi',   $kwReguler);
                $refKering   = $this->fetchReferensi($jenisAsli, $tebal, 'veneer kering', $kwReguler);
                $refAf       = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', $kwReguler);
                $refBasah    = $this->fetchReferensi($jenisAsli, $tebal, 'veneer basah',  $kwReguler);
                $refBasahPpc = $this->fetchReferensi($jenisAsli, $tebal, 'veneer afalan', $kwReguler);

                [$akunJadiNama,     $akunJadiNo,     $hargaJadi]     = $this->extractAkun($refJadi);
                [$akunKeringNama,   $akunKeringNo,   $hargaKering]   = $this->extractAkun($refKering);
                [$akunAfNama,       $akunAfNo,       $hargaAf]       = $this->extractAkun($refAf);
                [$akunBasahNama,    $akunBasahNo,    $hargaBasah]    = $this->extractAkun($refBasah);
                [$akunBasahPpcNama, $akunBasahPpcNo, $hargaBasahPpc] = $this->extractAkun($refBasahPpc);

                // Keterangan debit
                $ketJadi   = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}" . (!$refJadi ? ' [UNKNOWN]' : '');
                $ketKering = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}" . (!$refKering ? ' [UNKNOWN]' : '');
                $ketAf     = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af" . (!$refAf ? ' [UNKNOWN]' : '');

                // Keterangan kredit
                $ketBasah    = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} basah" . (!$refBasah ? ' [UNKNOWN]' : '');
                $ketBasahPpc = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} basah af" . (!$refBasahPpc ? ' [UNKNOWN]' : '');

                $kwJadiItems   = $dhsReguler->filter(fn($d) => in_array((int) $d['kw'], [1, 2]));
                $kwKeringItems = $dhsReguler->filter(fn($d) => in_array((int) $d['kw'], [3, 4]));
                $kwAfItems     = collect($dhsAf);

                $jadiOutputIsi   = $kwJadiItems->sum('isi');
                $keringOutputIsi = $kwKeringItems->sum('isi');
                $afOutputIsi     = $kwAfItems->sum('isi');

                $totalHasilIsi = $jadiOutputIsi + $keringOutputIsi + $afOutputIsi;
                $totalMasukIsi = $dms->sum('isi');

                $m3JadiTotal   = $this->hitungM3($kwJadiItems);
                $m3KeringTotal = $this->hitungM3($kwKeringItems);
                $m3AfTotal     = $this->hitungM3($kwAfItems);
                $totalMasukM3  = $this->hitungM3($dms);

                $hilang = $totalMasukIsi - $totalHasilIsi;

                $regJadiIsi   = $jadiOutputIsi;
                $regKeringIsi = $keringOutputIsi;
                $regAfIsi     = $afOutputIsi;

                $m3Jadi   = $m3JadiTotal;
                $m3Kering = $m3KeringTotal;
                $m3Af     = $m3AfTotal;

                $kelebihanDebitRow = null;

                if ($hilang < 0) {
                    $kelebihan = abs($hilang);

                    if ($keringOutputIsi >= $jadiOutputIsi && $keringOutputIsi >= $afOutputIsi) {
                        $regKeringIsi      = max(0, $keringOutputIsi - $kelebihan);
                        $m3Kering          = $keringOutputIsi > 0 ? ($regKeringIsi / $keringOutputIsi) * $m3KeringTotal : 0;
                        $m3Kelebihan       = $keringOutputIsi > 0 ? ($kelebihan / $keringOutputIsi) * $m3KeringTotal : 0;
                        $akunKelebihanNama = $akunKeringNama;
                        $akunKelebihanNo   = $akunKeringNo;
                        $hargaKelebihan    = $hargaKering;
                        $ketKelebihan      = $ketKering . " (kelebihan {$kelebihan})";

                        $m3KelebihanRnd    = round($m3Kelebihan, 4);
                        $subtotalKel       = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);
                    } elseif ($jadiOutputIsi >= $keringOutputIsi && $jadiOutputIsi >= $afOutputIsi) {
                        $regJadiIsi        = max(0, $jadiOutputIsi - $kelebihan);
                        $m3Jadi            = $jadiOutputIsi > 0 ? ($regJadiIsi / $jadiOutputIsi) * $m3JadiTotal : 0;
                        $m3Kelebihan       = $jadiOutputIsi > 0 ? ($kelebihan / $jadiOutputIsi) * $m3JadiTotal : 0;
                        $akunKelebihanNama = $akunJadiNama;
                        $akunKelebihanNo   = $akunJadiNo;
                        $hargaKelebihan    = $hargaJadi;
                        $ketKelebihan      = $ketJadi . " (kelebihan {$kelebihan})";

                        $m3KelebihanRnd    = round($m3Kelebihan, 4);
                        $subtotalKel       = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);
                    } else {
                        $regAfIsi          = max(0, $afOutputIsi - $kelebihan);
                        $m3Af              = $afOutputIsi > 0 ? ($regAfIsi / $afOutputIsi) * $m3AfTotal : 0;
                        $m3Kelebihan       = $afOutputIsi > 0 ? ($kelebihan / $afOutputIsi) * $m3AfTotal : 0;
                        $akunKelebihanNama = $akunAfNama;
                        $akunKelebihanNo   = $akunAfNo;
                        $hargaKelebihan    = $hargaAf;
                        $ketKelebihan      = $ketAf . " (kelebihan {$kelebihan})";

                        $m3KelebihanRnd    = round($m3Kelebihan, 4);
                        $subtotalKel       = round($m3KelebihanRnd * $hargaKelebihan, 0);
                        $kelebihanDebitRow = $this->makeRow($akunKelebihanNama, $akunKelebihanNo, $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, $m3KelebihanRnd, $hargaKelebihan, $subtotalKel);
                    }
                }

                // --- DEBIT ---
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

                // --- KREDIT ---
                if ($hilang >= 0) {
                    if ($jadiOutputIsi > 0 || $keringOutputIsi > 0) {
                        $m3Reguler    = round($m3JadiTotal + $m3KeringTotal, 4);
                        $subtotal     = round($m3Reguler * $hargaBasah, 0);
                        $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah, 'k', 'm', ($jadiOutputIsi + $keringOutputIsi), $m3Reguler, $hargaBasah, $subtotal);
                        $totalKredit += $subtotal;
                    }

                    if ($afOutputIsi > 0) {
                        $m3AfRound    = round($m3AfTotal, 4);
                        $subtotal     = round($m3AfRound * $hargaBasahPpc, 0);
                        $creditRows[] = $this->makeRow($akunBasahPpcNama, $akunBasahPpcNo, $tglProduksi, $namaProduksi, $ketBasahPpc, 'k', 'm', $afOutputIsi, $m3AfRound, $hargaBasahPpc, $subtotal);
                        $totalKredit += $subtotal;
                    }

                    if ($hilang > 0) {
                        $m3Hilang       = round($totalMasukM3 - ($m3JadiTotal + $m3KeringTotal + $m3AfTotal), 4);
                        if ($m3Hilang < 0) $m3Hilang = 0;
                        $subtotalHilang = round($m3Hilang * $hargaBasah, 0);
                        $creditRows[]   = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah . ' kehilangan ' . $hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                        $totalKredit   += $subtotalHilang;
                    }
                } else {
                    if ($totalMasukIsi > 0) {
                        $m3MasukRnd   = round($totalMasukM3, 4);
                        $subtotal     = round($m3MasukRnd * $hargaBasah, 0);
                        $creditRows[] = $this->makeRow($akunBasahNama, $akunBasahNo, $tglProduksi, $namaProduksi, $ketBasah, 'k', 'm', $totalMasukIsi, $m3MasukRnd, $hargaBasah, $subtotal);
                        $totalKredit += $subtotal;
                    }
                }
            }

            foreach ($debitRows as $r) $rows[] = $r;
            foreach ($creditRows as $r) $rows[] = $r;

            if ($totalPegawai > 0) {
                $rows[] = $this->makeRow('Hutang Gaji', '2231.00', $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
                $totalKredit += ($totalPegawai * 150000);
            }

            // =========================================================
            // HPP PENYEIMBANG OTOMATIS BULAT
            // =========================================================
            $nilaiHpp = $totalKredit - $totalDebit;

            if (round(abs($nilaiHpp), 0) != 0) {
                $mapHpp     = $nilaiHpp > 0 ? 'd' : 'k';
                $nominalHpp = round(abs($nilaiHpp), 0);

                $rows[] = $this->makeRow('hpp', '6111.00', $tglProduksi, $namaProduksi, '', $mapHpp, '', '', '', $nominalHpp, $nominalHpp);
            }

            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }

    public function map($row): array
    {
        $this->rowIndex++;

        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r = $this->rowIndex;
        $row[13] = "=ROUND(IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r})), 0)";

        return $row;
    }
}
