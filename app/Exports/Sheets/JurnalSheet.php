<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles
{
    protected array $dataProduksi;

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = $dataProduksi;
    }

    public function title(): string
    {
        return 'Jurnal';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, // Nama Akun
            'B' => 20, // tgl
            'C' => 15, // jurnal
            'D' => 12, // No Akun
            'E' => 8,  // No
            'F' => 18, // mm (Nama Produksi)
            'G' => 20, // Nama (entitas)
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
        $sheet->getStyle("D2:D{$lastRow}")
              ->getNumberFormat()
              ->setFormatCode('0.00');

        // Kolom M3 (K): format 8 desimal
        $sheet->getStyle("L2:L{$lastRow}")
              ->getNumberFormat()
              ->setFormatCode('0.0000');

        // Kolom Harga (L) dan Total (M): format angka ribuan tanpa desimal
        $sheet->getStyle("M2:N{$lastRow}")
              ->getNumberFormat()
              ->setFormatCode('#,##0');

        // Row height header
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    /**
     * Expand singkatan jenis kayu ke nama lengkap.
     */
    private function expandJenis(string $jenis): string
    {
        $map = [
            's' => 'sengon',
            'j' => 'jabon',
            'm' => 'meranti',
            'p' => 'pinus',
            'k' => 'keruing',
        ];
        $jns = strtolower(trim($jenis));
        return $map[$jns] ?? $jns;
    }

    /**
     * Normalisasi jenis kayu untuk label NAMA AKUN dan HARGA:
     * - sengon → 'sengon'
     * - selain sengon → 'meranti'
     */
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

    /**
     * Hitung M3 dari koleksi detail
     */
    private function hitungM3(\Illuminate\Support\Collection $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $p      = (float)($item['ukuran']['p'] ?? $item['ukuran']['panjang'] ?? 0);
            $l      = (float)($item['ukuran']['l'] ?? $item['ukuran']['lebar']   ?? 0);
            $t      = (float)($item['ukuran']['t'] ?? $item['ukuran']['tebal']   ?? 0);
            $jumlah = (int)($item['isi'] ?? 0);
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
                        $tglProduksi = \Carbon\Carbon::parse($rawTgl)->format('d-m-Y');
                    }
                }
            }

            $hasilsReguler = array_filter($allHasils, fn($d) => !$this->isKwAf($d['kw'] ?? 0));
            $hasilsAf      = array_filter($allHasils, fn($d) =>  $this->isKwAf($d['kw'] ?? 0));

            $makeKey = fn($d) => $this->expandJenis(trim($d['jenis_kayu'] ?? '')) . '_' . (float)($d['ukuran']['t'] ?? 0);

            $groupedHasilsReguler = collect($hasilsReguler)->groupBy($makeKey);
            $groupedHasilsAf      = collect($hasilsAf)->groupBy($makeKey);
            $groupedMasuks        = collect($allMasuks)->groupBy($makeKey);

            $totalDebit  = 0;
            $totalKredit = 0;
            $jurnalShift = [];
            $namaProduksi = 'dryer ' . strtolower($shiftName);

            // DEBIT: Hasil Reguler
            foreach ($groupedHasilsReguler as $key => $dhs) {
                $sample     = $dhs->first();
                $jenisAsli  = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $tebal      = (float)($sample['ukuran']['t'] ?? 0);
                $ukuranLengkap = $this->formatUkuran($sample['ukuran'] ?? []);
                $tipeLabel  = ($tebal < 1) ? '260 f/b' : '130 core';
                $ketDesc    = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap}";

                $kwJadi = $dhs->filter(fn($d) => in_array((int)$d['kw'], [1, 2]));
                if ($kwJadi->sum('isi') > 0) {
                    $m3       = round($this->hitungM3($kwJadi), 4);
                    $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'jadi');
                    $subtotal = round($m3 * $harga, 2);
                    $akun     = $this->getAkun('jadi', $jenisAsli, $tebal, false);
                    $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $kwJadi->sum('isi'), $m3, $harga, $subtotal);
                    $totalDebit   += $subtotal;
                }

                $kwKering = $dhs->filter(fn($d) => in_array((int)$d['kw'], [3, 4]));
                if ($kwKering->sum('isi') > 0) {
                    $m3       = round($this->hitungM3($kwKering), 4);
                    $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'kering');
                    $subtotal = round($m3 * $harga, 2);
                    $akun     = $this->getAkun('kering', $jenisAsli, $tebal, false);
                    $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $kwKering->sum('isi'), $m3, $harga, $subtotal);
                    $totalDebit   += $subtotal;
                }
            }

            // DEBIT: Hasil AF (PPC)
            foreach ($groupedHasilsAf as $key => $dhs) {
                $sample        = $dhs->first();
                $jenisAsli     = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $tebal         = (float)($sample['ukuran']['t'] ?? 0);
                $ukuranLengkap = $this->formatUkuran($sample['ukuran'] ?? []);
                $tipeLabel     = ($tebal < 1) ? '260 f/b' : '130 core';
                $ketDesc       = "{$tipeLabel} {$jenisAsli} uk {$ukuranLengkap} af";

                if ($dhs->sum('isi') > 0) {
                    $m3       = round($this->hitungM3($dhs), 4);
                    $harga    = $this->getHargaPatok($jenisAsli, $tebal, 'kering');
                    $subtotal = round($m3 * $harga, 2);
                    $akun     = $this->getAkun('kering', $jenisAsli, $tebal, true);
                    $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, $ketDesc, 'd', 'm', $dhs->sum('isi'), $m3, $harga, $subtotal);
                    $totalDebit   += $subtotal;
                }
            }

            // KREDIT: Modal Reguler & Kehilangan
            $allKeys = collect(array_keys($groupedMasuks->toArray()))
                ->merge(array_keys($groupedHasilsReguler->toArray()))
                ->merge(array_keys($groupedHasilsAf->toArray()))
                ->unique();

            foreach ($allKeys as $key) {
                $dhsReguler = $groupedHasilsReguler->get($key, collect());
                $dhsAf      = $groupedHasilsAf->get($key, collect());
                $dms        = $groupedMasuks->get($key, collect());

                $totalHasilIsi = $dhsReguler->sum('isi') + $dhsAf->sum('isi');
                $totalHasilM3  = $this->hitungM3($dhsReguler) + $this->hitungM3($dhsAf);

                $sample = $dhsReguler->first() ?? $dhsAf->first() ?? $dms->first();
                if (!$sample) continue;

                $jenisAsli  = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $tebal      = (float)($sample['ukuran']['t'] ?? 0);
                $hargaBasah = $this->getHargaPatok($jenisAsli, $tebal, 'basah');
                $akun       = $this->getAkun('basah', $jenisAsli, $tebal, false);

                if ($dhsReguler->sum('isi') > 0) {
                    $m3Reguler = round($this->hitungM3($dhsReguler), 4);
                    $subtotal  = round($m3Reguler * $hargaBasah, 2);
                    $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, '', 'k', 'm', $dhsReguler->sum('isi'), $m3Reguler, $hargaBasah, $subtotal);
                    $totalKredit  += $subtotal;
                }

                $hilang = $dms->sum('isi') - $totalHasilIsi;
                if ($hilang > 0) {
                    $m3Hilang       = round($this->hitungM3($dms) - $totalHasilM3, 4);
                    $subtotalHilang = round($m3Hilang * $hargaBasah, 2);
                    $jurnalShift[]  = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, 'kehilangan ' . $hilang, 'k', 'm', $hilang, $m3Hilang, $hargaBasah, $subtotalHilang);
                    $totalKredit   += $subtotalHilang;
                }
            }

            // KREDIT: Modal PPC (Untuk Hasil AF)
            foreach ($groupedHasilsAf as $key => $dhs) {
                $sample     = $dhs->first();
                $jenisAsli  = $this->expandJenis(trim($sample['jenis_kayu'] ?? ''));
                $tebal      = (float)($sample['ukuran']['t'] ?? 0);
                $hargaBasah = $this->getHargaPatok($jenisAsli, $tebal, 'basah');
                $akun       = $this->getAkun('basah', $jenisAsli, $tebal, true);

                if ($dhs->sum('isi') > 0) {
                    $m3Af     = round($this->hitungM3($dhs), 4);
                    $subtotal = round($m3Af * $hargaBasah, 2);
                    $jurnalShift[] = $this->makeRow($akun['nama'], $akun['no'], $tglProduksi, $namaProduksi, 'af', 'k', 'm', $dhs->sum('isi'), $m3Af, $hargaBasah, $subtotal);
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

            foreach ($jurnalShift as $r) $rows[] = $r;
            $rows[] = array_fill(0, 14, ''); 
        }

        return $rows;
    }
}