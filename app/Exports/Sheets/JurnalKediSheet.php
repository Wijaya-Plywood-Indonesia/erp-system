<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalKediSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles
{
    protected array $dataKedi;

    public function __construct($dataKedi)
    {
        $this->dataKedi = $dataKedi;
    }

    public function title(): string
    {
        return 'Jurnal Kedi';
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
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79'],
            ],
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

    private function parseDimensi(string $ukuranStr): array
    {
        $dimensi = explode('x', str_replace([' ', 'mm', 'MM'], '', strtolower($ukuranStr)));
        $p = (float) ($dimensi[0] ?? 0);
        $l = (float) ($dimensi[1] ?? 0);
        $t = (float) ($dimensi[2] ?? 0);
        return ['p' => $p, 'l' => $l, 't' => $t];
    }

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

    private function normalizeJenis(string $jenis): string
    {
        $jns = strtolower(trim($jenis));
        return str_contains($jns, 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, string $tipeKualitas, bool $isPpc = false): int
    {
        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $jns      = $this->normalizeJenis($jenis);

        $hargaReguler = [
            'sengon' => [
                'faceback' => ['basah' => 2700000, 'kering' => 2800000, 'jadi' => 4000000],
                'core'     => ['basah' => 1700000, 'kering' => 1900000, 'jadi' => 2250000],
            ],
            'meranti' => [
                'faceback' => ['basah' => 8000000, 'kering' => 8500000, 'jadi' => 12500000],
                'core'     => ['basah' => 2100000, 'kering' => 2500000, 'jadi' => 2800000],
            ],
        ];

        $hargaPpc = [
            'sengon'  => [
                'faceback' => ['basah' => 1700000, 'kering' => 1700000, 'jadi' => 1700000],
                'core'     => ['basah' => 1500000, 'kering' => 1500000, 'jadi' => 1500000],
            ],
            'meranti' => [
                'faceback' => ['basah' => 2000000, 'kering' => 2000000, 'jadi' => 2000000],
                'core'     => ['basah' => 1800000, 'kering' => 1800000, 'jadi' => 1800000],
            ],
        ];

        $tabel = $isPpc ? $hargaPpc : $hargaReguler;
        return $tabel[$jns][$kelompok][$tipeKualitas] ?? 0;
    }

    private function isKwAf(mixed $kw): bool
    {
        return !in_array((int)$kw, [1, 2, 3, 4]);
    }

    private function hitungM3(\Illuminate\Support\Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $dim    = $this->parseDimensi($item['ukuran'] ?? '');
            $jumlah = (int)($item['jumlah'] ?? 0);
            $total += ($dim['p'] * $dim['l'] * $dim['t'] * $jumlah) / 10_000_000;
        }
        return $total;
    }

    private function formatUkuran(array $dim): string
    {
        return "{$dim['p']} x {$dim['l']} x {$dim['t']}";
    }

    private function getAkun(string $tipeVeneer, string $jenis, float $tebal, bool $isPpc): array
    {
        $jnsAkun    = $this->normalizeJenis($jenis);
        $kelompok   = ($tebal < 1) ? 'faceback' : 'core';
        $tipeUkuran = ($tebal < 1) ? '260 face/back' : '130 core';
        $namaVeneer = ucfirst($tipeVeneer);

        if ($isPpc) {
            $noAkun = [
                'sengon'  => ['basah' => '1429.00', 'kering' => '1452.00', 'jadi' => '1472.00'],
                'meranti' => ['basah' => '1428.00', 'kering' => '1451.00', 'jadi' => '1471.00'],
            ];
            $no   = $noAkun[$jnsAkun][$tipeVeneer];
            $nama = "Veneer {$namaVeneer} ppc {$jnsAkun} WJY";
        } else {
            $noAkun = [
                'sengon'  => [
                    'basah'  => ['faceback' => '1421', 'core' => '1426'],
                    'kering' => ['faceback' => '1441', 'core' => '1446'],
                    'jadi'   => ['faceback' => '1461', 'core' => '1466'],
                ],
                'meranti' => [
                    'basah'  => ['faceback' => '1422.00', 'core' => '1427.00'],
                    'kering' => ['faceback' => '1442.00', 'core' => '1447.00'],
                    'jadi'   => ['faceback' => '1462.00', 'core' => '1467.00'],
                ],
            ];
            $no   = $noAkun[$jnsAkun][$tipeVeneer][$kelompok];
            $nama = "Veneer {$namaVeneer} {$tipeUkuran} {$jnsAkun} WJY";
        }

        return ['nama' => $nama, 'no' => $no];
    }

    private function makeRow(string $namaAkun, string $noAkun, string $tgl, string $namaProduksi, string $keterangan, string $map, string $hitKbk, $banyak, $m3, $harga, $total): array
    {
        return [
            $namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $keterangan, $map, $hitKbk, $banyak, $m3, $harga, $total
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        if (empty($this->dataKedi)) return $rows;

        $totalPegawai = 0;
        $allBongkars  = [];
        $allMasuks    = [];
        $tglProduksi  = '';

        foreach ($this->dataKedi as $produksi) {
            $totalPegawai += $produksi['total_pekerja'] ?? 0;
            if (empty($tglProduksi)) {
                $rawTgl = str_replace('/', '-', $produksi['tanggal_masuk'] ?? '');
                try {
                    $tglProduksi = \Carbon\Carbon::parse($rawTgl)->format('d-m-Y');
                } catch (\Exception $e) {
                    $tglProduksi = $rawTgl;
                }
            }
            foreach ($produksi['detail_bongkar'] ?? [] as $db) $allBongkars[] = $db;
            foreach ($produksi['detail_masuk'] ?? [] as $dm) $allMasuks[] = $dm;
        }

        $namaProduksi = 'kedi';

        $bongkarsReguler = array_filter($allBongkars, fn($d) => !$this->isKwAf($d['kw'] ?? 0));
        $bongkarsAf      = array_filter($allBongkars, fn($d) =>  $this->isKwAf($d['kw'] ?? 0));

        $makeKey = function($d) {
            $dim = $this->parseDimensi($d['ukuran'] ?? '');
            $jenisAsli = trim($d['jenis_kayu'] ?? '');
            return $this->expandJenis($jenisAsli) . '_' . $dim['t'];
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

            $hargaBasah    = $this->getHargaPatok($jenisAsli, $tebal, 'basah', false);
            $hargaBasahPpc = $this->getHargaPatok($jenisAsli, $tebal, 'basah', true);

            $kwJadiItems   = $dbsReguler->filter(fn($d) => in_array((int)$d['kw'], [1, 2]));
            $kwKeringItems = $dbsReguler->filter(fn($d) => in_array((int)$d['kw'], [3, 4]));
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

            $m3Jadi   = $m3JadiTotal;
            $m3Kering = $m3KeringTotal;
            $m3Af     = $m3AfTotal;

            $kelebihanDebitRow = null;

            if ($hilang < 0) {
                $kelebihan = abs($hilang);

                if ($keringOutputIsi >= $jadiOutputIsi && $keringOutputIsi >= $afOutputIsi) {
                    $regKeringIsi   = max(0, $keringOutputIsi - $kelebihan);
                    $m3Kering       = $keringOutputIsi > 0 ? ($regKeringIsi / $keringOutputIsi) * $m3KeringTotal : 0;
                    $m3Kelebihan    = $keringOutputIsi > 0 ? ($kelebihan / $keringOutputIsi) * $m3KeringTotal : 0;
                    $akunKelebihan  = $this->getAkun('kering', $jenisAsli, $tebal, false);
                    $hargaKelebihan = $this->getHargaPatok($jenisAsli, $tebal, 'kering', false);
                    $ketKelebihan   = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} (kelebihan {$kelebihan})";
                    $kelebihanDebitRow = $this->makeRow($akunKelebihan['nama'], $akunKelebihan['no'], $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, round($m3Kelebihan, 4), $hargaKelebihan, round($m3Kelebihan * $hargaKelebihan, 2));

                } elseif ($jadiOutputIsi >= $keringOutputIsi && $jadiOutputIsi >= $afOutputIsi) {
                    $regJadiIsi     = max(0, $jadiOutputIsi - $kelebihan);
                    $m3Jadi         = $jadiOutputIsi > 0 ? ($regJadiIsi / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $m3Kelebihan    = $jadiOutputIsi > 0 ? ($kelebihan / $jadiOutputIsi) * $m3JadiTotal : 0;
                    $akunKelebihan  = $this->getAkun('jadi', $jenisAsli, $tebal, false);
                    $hargaKelebihan = $this->getHargaPatok($jenisAsli, $tebal, 'jadi', false);
                    $ketKelebihan   = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} (kelebihan {$kelebihan})";
                    $kelebihanDebitRow = $this->makeRow($akunKelebihan['nama'], $akunKelebihan['no'], $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, round($m3Kelebihan, 4), $hargaKelebihan, round($m3Kelebihan * $hargaKelebihan, 2));

                } else {
                    $regAfIsi       = max(0, $afOutputIsi - $kelebihan);
                    $m3Af           = $afOutputIsi > 0 ? ($regAfIsi / $afOutputIsi) * $m3AfTotal : 0;
                    $m3Kelebihan    = $afOutputIsi > 0 ? ($kelebihan / $afOutputIsi) * $m3AfTotal : 0;
                    $akunKelebihan  = $this->getAkun('kering', $jenisAsli, $tebal, true);
                    $hargaKelebihan = $this->getHargaPatok($jenisAsli, $tebal, 'kering', true);
                    $ketKelebihan   = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af (kelebihan {$kelebihan})";
                    $kelebihanDebitRow = $this->makeRow($akunKelebihan['nama'], $akunKelebihan['no'], $tglProduksi, $namaProduksi, $ketKelebihan, 'd', 'm', $kelebihan, round($m3Kelebihan, 4), $hargaKelebihan, round($m3Kelebihan * $hargaKelebihan, 2));
                }
            }

            // --- DEBIT ---
            if ($regJadiIsi > 0) {
                $harga       = $this->getHargaPatok($jenisAsli, $tebal, 'jadi', false);
                $subtotal    = round($m3Jadi * $harga, 2);
                $akun        = $this->getAkun('jadi', $jenisAsli, $tebal, false);
                $ketDesc     = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}";
                $debitRows[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $regJadiIsi, round($m3Jadi, 4), $harga, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($regKeringIsi > 0) {
                $harga       = $this->getHargaPatok($jenisAsli, $tebal, 'kering', false);
                $subtotal    = round($m3Kering * $harga, 2);
                $akun        = $this->getAkun('kering', $jenisAsli, $tebal, false);
                $ketDesc     = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}";
                $debitRows[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $regKeringIsi, round($m3Kering, 4), $harga, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($regAfIsi > 0) {
                $harga       = $this->getHargaPatok($jenisAsli, $tebal, 'kering', true);
                $subtotal    = round($m3Af * $harga, 2);
                $akun        = $this->getAkun('kering', $jenisAsli, $tebal, true);
                $ketDesc     = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af";
                $debitRows[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $regAfIsi, round($m3Af, 4), $harga, $subtotal);
                $totalDebit += $subtotal;
            }

            if ($kelebihanDebitRow) {
                $debitRows[] = $kelebihanDebitRow;
                $totalDebit += $kelebihanDebitRow[13];
            }

            // --- KREDIT ---
            if ($hilang >= 0) {
                if ($jadiOutputIsi > 0 || $keringOutputIsi > 0) {
                    $akun         = $this->getAkun('basah', $jenisAsli, $tebal, false);
                    $m3Reguler    = round($m3JadiTotal + $m3KeringTotal, 4);
                    $subtotal     = round($m3Reguler * $hargaBasah, 2);
                    $creditRows[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, '', 'k', 'm', ($jadiOutputIsi + $keringOutputIsi), $m3Reguler, $hargaBasah, $subtotal);
                    $totalKredit += $subtotal;
                }

                if ($afOutputIsi > 0) {
                    $akunAf       = $this->getAkun('basah', $jenisAsli, $tebal, true);
                    $m3AfRound    = round($m3AfTotal, 4);
                    $subtotal     = round($m3AfRound * $hargaBasahPpc, 2);
                    $creditRows[] = $this->makeRow($akunAf['nama'], $akunAf['no'], $tglProduksi, $namaProduksi, 'af', 'k', 'm', $afOutputIsi, $m3AfRound, $hargaBasahPpc, $subtotal);
                    $totalKredit += $subtotal;
                }

                if ($hilang > 0) {
                    $akun           = $this->getAkun('basah', $jenisAsli, $tebal, false);
                    $m3Hilang       = round($totalMasukM3 - ($m3JadiTotal + $m3KeringTotal + $m3AfTotal), 4);
                    if ($m3Hilang < 0) $m3Hilang = 0;
                    $subtotalHilang = round($m3Hilang * $hargaBasah, 2);
                    $creditRows[]   = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, 'kehilangan ' . $hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                    $totalKredit   += $subtotalHilang;
                }
            } else {
                if ($totalMasukIsi > 0) {
                    $akun         = $this->getAkun('basah', $jenisAsli, $tebal, false);
                    $subtotal     = round($totalMasukM3 * $hargaBasah, 2);
                    $creditRows[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, '', 'k', 'm', $totalMasukIsi, round($totalMasukM3, 4), $hargaBasah, $subtotal);
                    $totalKredit += $subtotal;
                }
            }
        } 

        foreach ($debitRows as $r) $rows[] = $r;
        foreach ($creditRows as $r) $rows[] = $r;

        if ($totalPegawai > 0) {
            // UPDATE: Menggunakan 2231.00 untuk Hutang Gaji
            $rows[] = $this->makeRow('Hutang Gaji', '2231.00', $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
            $totalKredit += ($totalPegawai * 150000);
        }

        $hpp = abs($totalKredit - $totalDebit);
        if (round($hpp, 2) != 0) {
            $rows[] = $this->makeRow('hpp', '6111', $tglProduksi, $namaProduksi, '', 'k', '', '', '', round($hpp, 2), round($hpp, 2));
        }

        foreach ($creditRows as $r) {
           // Tidak melakukan push ke $rows lagi karena sudah ditambahkan di atas.
        }

        return $rows;
    }
}