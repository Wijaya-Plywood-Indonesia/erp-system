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
            'A' => 45, // Nama Akun
            'B' => 20, // tgl
            'C' => 15, // jurnal
            'D' => 12, // No Akun
            'E' => 8,  // No
            'F' => 18, // mm
            'G' => 20, // Nama Produksi (kedi)
            'H' => 45, // Keterangan
            'I' => 6,  // map
            'J' => 10, // hit kbk
            'K' => 10, // Banyak
            'L' => 15, // M3
            'M' => 15, // Harga
            'N' => 15, // Total
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Border hitam tipis untuk semua sel
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ];
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray($borderStyle);

        // Header: biru gelap, teks putih bold, center
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

        // Kolom No Akun (D): format angka 2 desimal
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('0.00');

        // Kolom M3 (L): format 4 desimal
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');

        // Kolom Harga (M) dan Total (N): format angka ribuan tanpa desimal
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

    private function normalizeJenis(string $jenis): string
    {
        $jns = strtolower(trim($jenis));
        return str_contains($jns, 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, string $tipeKualitas): int
    {
        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $jns      = $this->normalizeJenis($jenis);

        $harga = [
            'sengon' => [
                'faceback' => ['basah' => 2700000, 'kering' => 2800000, 'jadi' => 4000000],
                'core'     => ['basah' => 1700000, 'kering' => 1900000, 'jadi' => 2250000],
            ],
            'meranti' => [
                'faceback' => ['basah' => 8000000, 'kering' => 8500000, 'jadi' => 12500000],
                'core'     => ['basah' => 2100000, 'kering' => 2500000, 'jadi' => 2800000],
            ]
        ];

        return $harga[$jns][$kelompok][$tipeKualitas] ?? 0;
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
                    'basah'  => ['faceback' => '1421',    'core' => '1426'],
                    'kering' => ['faceback' => '1441',    'core' => '1446'],
                    'jadi'   => ['faceback' => '1461',    'core' => '1466'],
                ],
                'meranti' => [
                    'basah'  => ['faceback' => '1421',    'core' => '1426'],
                    'kering' => ['faceback' => '1441.00', 'core' => '1446.00'],
                    'jadi'   => ['faceback' => '1461.00', 'core' => '1466.00'],
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
                $tglProduksi = str_replace('/', '-', $produksi['tanggal_masuk'] ?? '');
            }
            foreach ($produksi['detail_bongkar'] ?? [] as $db) $allBongkars[] = $db;
            foreach ($produksi['detail_masuk'] ?? [] as $dm) $allMasuks[] = $dm;
        }

        $namaProduksi = 'kedi';

        $bongkarsReguler = array_filter($allBongkars, fn($d) => !$this->isKwAf($d['kw'] ?? 0));
        $bongkarsAf      = array_filter($allBongkars, fn($d) =>  $this->isKwAf($d['kw'] ?? 0));

        $makeKey = function($d) {
            $dim = $this->parseDimensi($d['ukuran'] ?? '');
            return trim($d['jenis_kayu'] ?? '') . '_' . $dim['t'];
        };

        $groupedBongkarsReguler = collect($bongkarsReguler)->groupBy($makeKey);
        $groupedBongkarsAf      = collect($bongkarsAf)->groupBy($makeKey);
        $groupedMasuks          = collect($allMasuks)->groupBy($makeKey);

        $totalDebit  = 0;
        $totalKredit = 0;
        $jurnalShift = [];

        // DEBIT: Hasil Bongkar Reguler
        foreach ($groupedBongkarsReguler as $key => $dbs) {
            $sample        = $dbs->first();
            $jenisAsli     = trim($sample['jenis_kayu'] ?? '');
            $dim           = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal         = $dim['t'];
            $ukuranLengkap = $this->formatUkuran($dim);
            $tipeLabel     = ($tebal < 1) ? '260 f/b' : '130 core';
            $ketDesc       = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}";

            $kwJadi = $dbs->filter(fn($d) => in_array((int)$d['kw'], [1, 2]));
            if ($kwJadi->sum('jumlah') > 0) {
                $m3       = round($this->hitungM3($kwJadi), 4);
                $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'jadi');
                $subtotal = round($m3 * $harga, 2);
                $akun     = $this->getAkun('jadi', $jenisAsli, $tebal, false);
                $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $kwJadi->sum('jumlah'), $m3, $harga, $subtotal);
                $totalDebit   += $subtotal;
            }

            $kwKering = $dbs->filter(fn($d) => in_array((int)$d['kw'], [3, 4]));
            if ($kwKering->sum('jumlah') > 0) {
                $m3       = round($this->hitungM3($kwKering), 4);
                $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'kering');
                $subtotal = round($m3 * $harga, 2);
                $akun     = $this->getAkun('kering', $jenisAsli, $tebal, false);
                $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $kwKering->sum('jumlah'), $m3, $harga, $subtotal);
                $totalDebit   += $subtotal;
            }
        }

        // DEBIT: Hasil Bongkar PPC (AF)
        foreach ($groupedBongkarsAf as $key => $dbs) {
            $sample        = $dbs->first();
            $jenisAsli     = trim($sample['jenis_kayu'] ?? '');
            $dim           = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal         = $dim['t'];
            $ukuranLengkap = $this->formatUkuran($dim);
            $tipeLabel     = ($tebal < 1) ? '260 f/b' : '130 core';
            $ketDesc       = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af";

            if ($dbs->sum('jumlah') > 0) {
                $m3       = round($this->hitungM3($dbs), 4);
                $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'kering');
                $subtotal = round($m3 * $harga, 2);
                $akun     = $this->getAkun('kering', $jenisAsli, $tebal, true);
                $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $dbs->sum('jumlah'), $m3, $harga, $subtotal);
                $totalDebit   += $subtotal;
            }
        }

        // KREDIT: Masuk Basah Reguler & Kehilangan
        $allKeys = collect(array_keys($groupedMasuks->toArray()))
            ->merge(array_keys($groupedBongkarsReguler->toArray()))
            ->merge(array_keys($groupedBongkarsAf->toArray()))
            ->unique();

        foreach ($allKeys as $key) {
            $dbsReguler = $groupedBongkarsReguler->get($key, collect());
            $dbsAf      = $groupedBongkarsAf->get($key, collect());
            $dms        = $groupedMasuks->get($key, collect());

            $totalHasilIsi = $dbsReguler->sum('jumlah') + $dbsAf->sum('jumlah');
            $totalHasilM3  = $this->hitungM3($dbsReguler) + $this->hitungM3($dbsAf);

            $sample = $dbsReguler->first() ?? $dbsAf->first() ?? $dms->first();
            if (!$sample) continue;

            $jenisAsli  = trim($sample['jenis_kayu'] ?? '');
            $dim        = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal      = $dim['t'];
            $hargaBasah = $this->getHargaPatok($jenisAsli, $tebal, 'basah');
            $akun       = $this->getAkun('basah', $jenisAsli, $tebal, false);

            if ($dbsReguler->sum('jumlah') > 0) {
                $m3Reguler = round($this->hitungM3($dbsReguler), 4);
                $subtotal  = round($m3Reguler * $hargaBasah, 2);
                $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, '', 'k', 'm', $dbsReguler->sum('jumlah'), $m3Reguler, $hargaBasah, $subtotal);
                $totalKredit  += $subtotal;
            }

            $hilang = $dms->sum('jumlah') - $totalHasilIsi;
            if ($hilang > 0) {
                $m3Hilang       = round($this->hitungM3($dms) - $totalHasilM3, 4);
                $subtotalHilang = round($m3Hilang * $hargaBasah, 2);
                $jurnalShift[]  = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, 'kehilangan ' . $hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                $totalKredit   += $subtotalHilang;
            }
        }

        // KREDIT: Masuk Basah PPC (Untuk Hasil Bongkar AF)
        foreach ($groupedBongkarsAf as $key => $dbs) {
            $sample     = $dbs->first();
            $jenisAsli  = trim($sample['jenis_kayu'] ?? '');
            $dim        = $this->parseDimensi($sample['ukuran'] ?? '');
            $tebal      = $dim['t'];
            $hargaBasah = $this->getHargaPatok($jenisAsli, $tebal, 'basah');
            $akun       = $this->getAkun('basah', $jenisAsli, $tebal, true);

            if ($dbs->sum('jumlah') > 0) {
                $m3Af     = round($this->hitungM3($dbs), 4);
                $subtotal = round($m3Af * $hargaBasah, 2);
                $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, 'af', 'k', 'm', $dbs->sum('jumlah'), $m3Af, $hargaBasah, $subtotal);
                $totalKredit  += $subtotal;
            }
        }

        // KREDIT: Hutang Gaji
        if ($totalPegawai > 0) {
            $jurnalShift[] = $this->makeRow('Hutang Gaji', '2400.01', $tglProduksi, $namaProduksi, '', 'k', 'b', $totalPegawai, '', 150000, ($totalPegawai * 150000));
            $totalKredit  += ($totalPegawai * 150000);
        }

        // HPP (Otomatis menyesuaikan posisi Debet/Kredit agar jurnal Balance)
        $hpp = $totalKredit - $totalDebit;
        if (round($hpp, 2) != 0) {
            $posisiHpp = ($hpp > 0) ? 'd' : 'k';
            $jurnalShift[] = $this->makeRow('hpp', '6111', $tglProduksi, $namaProduksi, '', $posisiHpp, '', '', '', abs(round($hpp, 2)), abs(round($hpp, 2)));
        }

        foreach ($jurnalShift as $r) {
            $rows[] = $r;
        }

        return $rows;
    }
}