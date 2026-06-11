<?php

namespace App\Exports\Sheets;

use App\Models\ProduksiHp;
use App\Models\BahanPenolongProduksi;
use App\Models\HargaPegawai;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class LaporanProduksiHotPressJurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithMapping
{
    protected string $tanggal;
    protected string $domain;
    protected int $rowIndex = 0;

    public function __construct(string $tanggal, string $domain)
    {
        $this->tanggal = $tanggal;
        $this->domain  = $domain;
    }

    public function title(): string { return 'jurnal produksi'; }

    public function columnWidths(): array
    {
        return [
            'A' => 42, 'B' => 15, 'C' => 10, 'D' => 12, 'E' => 10,
            'F' => 10, 'G' => 18, 'H' => 42, 'I' => 6,  'J' => 10,
            'K' => 10, 'L' => 15, 'M' => 15, 'N' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function isWhn(): bool
    {
        return str_contains(strtolower($this->domain), 'wahana');
    }

    private function getNamaMesinSingkat(string $namaMesin, string $shift): string
    {
        $namaLower = strtolower($namaMesin);
        $shiftStr  = strtolower(trim($shift));

        if (str_contains($namaLower, '1')) return "hp 1 {$shiftStr}";
        if (str_contains($namaLower, '2')) return "hp 2 {$shiftStr}";
        if (str_contains($namaLower, '3')) return "hp 3 {$shiftStr}";

        return "hp 1 {$shiftStr}";
    }

    // =========================================================================
    // MAPPING AKUN PRODUK
    // =========================================================================
    private function getAkunProduk(string $tipe, float $tebal, string $jenisKayu, string $grade): array
    {
        $tipeStr    = strtolower(trim($tipe));
        // Normalisasi grade: underscore → spasi, lowercase, trim
        // Agar "uty_lokal", "Uty Lokal", "uty lokal" semua jadi "uty lokal"
        $gradeStr   = strtolower(str_replace('_', ' ', trim($grade)));
        $kayuSingkat = str_contains(strtolower($jenisKayu), 'sengon') ? 's' : 'm';
        $tebalBulat  = (int) $tebal;

        if ($tipeStr === 'platform') {
            $namaAkun = "platform {$tebalBulat} {$gradeStr} MTH";
        } else {
            $namaAkun = "{$tebalBulat}{$kayuSingkat} {$gradeStr} MTH";
        }

        $daftarAkun = [
            '12m better mth'     => '1506.05',
            '15s aj mth'         => '1506.14',
            '15s uty lokal mth'  => '1506.13',
            '18s aj mth'         => '1506.19',
            '3m uty lokal mth'   => '1506.21',
            '5s uty lokal mth'   => '1506.26',
            'platform 18 aj mth' => '1506.51',
        ];

        $key = strtolower($namaAkun);

        // Exact match
        if (isset($daftarAkun[$key])) {
            return ['nama' => $namaAkun, 'no' => $daftarAkun[$key]];
        }

        // Fuzzy match — semua kata dari pattern harus ada di key
        foreach ($daftarAkun as $pattern => $no) {
            $patternWords = explode(' ', $pattern);
            $allMatch     = true;
            foreach ($patternWords as $word) {
                if (!str_contains($key, $word)) { $allMatch = false; break; }
            }
            if ($allMatch) return ['nama' => $namaAkun, 'no' => $no];
        }

        return ['nama' => $namaAkun, 'no' => '1506.99'];
    }

    // =========================================================================
    // MAPPING AKUN VENEER
    // =========================================================================
    private function getAkunVeneer(float $tebal, string $jenisKayu, string $grade = ''): array
    {
        $kayu = str_contains(strtolower($jenisKayu), 'sengon') ? 'sengon' : 'meranti';
        $sf   = $this->isWhn() ? 'WHN' : 'WJY';
        $ext  = $this->isWhn() ? '01'  : '00';

        // Cek PPC: 260 F/B (tebal < 1) sengon dengan grade bukan 1,2,3,4 (alias AF/afkir)
        $gradeStr = strtolower(trim($grade));
        $isPpc    = ($tebal < 1)
            && ($kayu === 'sengon')
            && !in_array($gradeStr, ['1', '2', '3', '4', 'grade 1', 'grade 2', 'grade 3', 'grade 4']);

        if ($isPpc) {
            return [
                'nama' => "Veneer Jadi ppc {$kayu} {$sf}",
                'no'   => "1472.{$ext}",
            ];
        }

        $kelompok = ($tebal < 1) ? '260 face/back' : '130 core';
        $namaAkun = "Veneer Jadi {$kelompok} {$kayu} {$sf}";

        if ($this->isWhn()) {
            if ($kayu === 'meranti') {
                $noAkun = ($tebal < 1) ? '1462.01' : '1467.01';
            } else {
                $noAkun = ($tebal < 1) ? '1461.01' : '1466.01';
            }
        } else {
            if ($kayu === 'meranti') {
                $noAkun = ($tebal < 1) ? "1442.{$ext}" : "1447.{$ext}";
            } else {
                $noAkun = ($tebal < 1) ? "1441.{$ext}" : "1446.{$ext}";
            }
        }

        return ['nama' => $namaAkun, 'no' => $noAkun];
    }

    // =========================================================================
    // MAPPING AKUN BAHAN PENOLONG
    // FIX: Tambah variasi underscore (lem_aruki, dll) untuk handle nama DB
    // =========================================================================
    private function getAkunPenolong(string $namaBahan): array
    {
        $namaLower = strtolower(trim($namaBahan));

        $daftarPenolong = [
            // Lem Aruki — dengan dan tanpa underscore
            'lem_aruki'      => ['nama' => 'Lem Aruki',          'no' => '1507.20'],
            'lem aruki'      => ['nama' => 'Lem Aruki',          'no' => '1507.20'],
            // Lem HQ
            'lem_hq'         => ['nama' => 'Lem HQ',             'no' => '1507.57'],
            'lem hq'         => ['nama' => 'Lem HQ',             'no' => '1507.57'],
            // Tepung WJY harus dicek duluan (lebih spesifik)
            'tepung_wjy'     => ['nama' => 'Tepung',             'no' => '1507.62'],
            'tepung wjy'     => ['nama' => 'Tepung',             'no' => '1507.62'],
            // Tepung biasa
            'tepung'         => ['nama' => 'Tepung',             'no' => '1507.16'],
            // Hadner / Hardener
            'hadner'         => ['nama' => 'Hadner',             'no' => '1507.11'],
            'hardener'       => ['nama' => 'Hadner',             'no' => '1507.11'],
            'hdr'            => ['nama' => 'Hadner',             'no' => '1507.11'],
            // Pewarna
            'pewarna'        => ['nama' => 'Pewarna',            'no' => '1507.49'],
            // Isolasi coklat (cek duluan sebelum isolasi putih)
            'isolasi_coklat' => ['nama' => 'isolasi coklat WHN', 'no' => '1507.35'],
            'isolasi coklat' => ['nama' => 'isolasi coklat WHN', 'no' => '1507.35'],
            // Isolasi putih
            'isolasi_putih'  => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'isolasi putih'  => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'solasi putih'   => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'solasi'         => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            // Isi Staples / Steples
            'isi_staples'    => ['nama' => 'Isi Staples',        'no' => '1507.13'],
            'isi_steples'    => ['nama' => 'Isi Staples',        'no' => '1507.13'],
            'isi staples'    => ['nama' => 'Isi Staples',        'no' => '1507.13'],
            'isi steples'    => ['nama' => 'Isi Staples',        'no' => '1507.13'],
            'staples'        => ['nama' => 'Isi Staples',        'no' => '1507.13'],
            'steples'        => ['nama' => 'Isi Staples',        'no' => '1507.13'],
        ];

        foreach ($daftarPenolong as $keyword => $data) {
            if (str_contains($namaLower, $keyword)) {
                return $data;
            }
        }

        return ['nama' => $namaBahan, 'no' => '1507.99'];
    }

    // =========================================================================
    // MAPPING AKUN HPP
    // =========================================================================
    private function getAkunHpp(string $tipe): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        if (strtolower(trim($tipe)) === 'platform') {
            return ['nama' => 'hpp platform', 'no' => "6111.{$ext}"];
        }
        return ['nama' => 'hpp triplek', 'no' => "6111.{$ext}"];
    }

    // =========================================================================
    // MAPPING AKUN GAJI
    // =========================================================================
    private function getAkunGaji(): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        return [
            'biaya'  => ['nama' => 'Biaya Gaji Plywood', 'no' => "5114.{$ext}"],
            'hutang' => ['nama' => 'Hutang Gaji',        'no' => '2231.00'],
        ];
    }

    // =========================================================================
    // HARGA HPP PRODUK
    // =========================================================================
    private function getHargaHpp(string $tipe, float $tebal, string $jenisKayu, string $grade): float
    {
        if (strtolower(trim($tipe)) === 'platform') return 2000000;

        $kayu = strtolower($jenisKayu);
        $gr   = strtolower($grade);
        $tbl  = (string) round($tebal, 1);

        if (str_contains($kayu, 'sengon')) {
            if (str_contains($gr, 'better')) {
                $p = [
                    '4.7'  => 65400,
                    '7.7'  => 96800,
                    '9.1'  => 100800,
                    '12.1' => 97400,
                    '12.4' => 103300,
                    '15.1' => 132300,
                    '15.5' => 153800,
                    '18.5' => 173800,
                ];
                return $p[$tbl] ?? 96800;
            } elseif (str_contains($gr, 'fm')) {
                $p = ['9' => 156843, '12' => 207903, '15' => 258963, '18' => 291708];
                return $p[(string) round($tebal)] ?? 207903;
            } else {
                // HPP S UTY
                $p = [
                    '4.7'  => 47000,
                    '7.7'  => 66400,
                    '9.1'  => 47000,
                    '12.1' => 107100,
                    '12.4' => 106400,
                    '15.1' => 122900,
                    '15.5' => 125400,
                    '18.5' => 135900,
                ];
                return $p[$tbl] ?? 47000;
            }
        } else {
            // Meranti
            if (str_contains($gr, 'better')) {
                $p = ['5.1' => 70300, '8.2' => 78300, '8.7' => 94300];
                return $p[$tbl] ?? 78300;
            } elseif (str_contains($gr, 'lokal')) {
                $p = [
                    '5.1'  => 70300,
                    '8.2'  => 90800,
                    '11.9' => 95800,
                    '14.8' => 126300,
                    '17.8' => 156800,
                    '16.1' => 126300,
                    '19.1' => 177300,
                ];
                return $p[$tbl] ?? 90800;
            } else {
                // HPP M UTY
                $p = [
                    '2.8'  => 40000,
                    '5.1'  => 65500,
                    '8.2'  => 79500,
                    '8.7'  => 76500,
                    '11.9' => 103500,
                    '14.8' => 125500,
                    '17.8' => 150300,
                    '16.1' => 125500,
                    '19.1' => 150300,
                ];
                return $p[$tbl] ?? 79500;
            }
        }
    }

    // =========================================================================
    // HARGA VENEER
    // =========================================================================
    private function getHargaVeneer(float $tebal, string $jenisKayu, bool $isPpc = false): float
    {
        if ($isPpc) return 1700000;

        $kayu = str_contains(strtolower($jenisKayu), 'sengon') ? 'sengon' : 'meranti';

        if ($tebal >= 1) {
            // 130 core
            return ($kayu === 'sengon') ? 2250000 : 2800000;
        } else {
            // 260 F/B
            return ($kayu === 'sengon') ? 4000000 : 10000000;
        }
    }

    // =========================================================================
    // HELPER ROW BUILDER
    // =========================================================================
    private function makeRow(
        $namaAkun, $noAkun, $tgl, $namaProduksi,
        $ket, $map, $hitKbk, $banyak, $m3, $harga
    ): array {
        return [$namaAkun, $tgl, '', $noAkun, '', '', $namaProduksi, $ket, $map, $hitKbk, $banyak, $m3, $harga, null];
    }

    // =========================================================================
    // MAIN: array()
    // =========================================================================
    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        $produksis = ProduksiHp::with([
            'triplekHasilHp.mesin',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            'bahanHotpress.barangSetengahJadi.jenisBarang',
            'bahanHotpress.barangSetengahJadi.ukuran',
            'bahanHotpress.barangSetengahJadi.grade',
            'bahanPenolongHp',
            'detailPegawaiHp',
        ])->whereDate('tanggal_produksi', $this->tanggal)->get();

        if ($produksis->isEmpty()) return $rows;

        $tglStr             = Carbon::parse($this->tanggal)->format('d-m-Y');
        $hargaPegawaiMaster = HargaPegawai::first()->harga ?? 150000;

        foreach ($produksis as $prod) {
            $shiftStr    = $prod->shift ?? 'pagi';
            $hasilByMesin = [];

            foreach ($prod->triplekHasilHp as $t) {
                $hasilByMesin[$t->id_mesin][] = ['tipe' => 'triplek',  'data' => $t];
            }
            foreach ($prod->platformHasilHp as $p) {
                $hasilByMesin[$p->id_mesin][] = ['tipe' => 'platform', 'data' => $p];
            }

            // Tentukan HP3
            $hp3Id = null;
            foreach ($hasilByMesin as $mId => $items) {
                $namaMesin = strtolower($items[0]['data']->mesin->nama_mesin ?? '');
                if (str_contains($namaMesin, '3')) { $hp3Id = $mId; break; }
            }
            if (!$hp3Id && count($hasilByMesin) > 0) {
                $hp3Id = array_key_last($hasilByMesin);
            }

            $jumlahPekerja = $prod->detailPegawaiHp->count();

            foreach ($hasilByMesin as $mId => $items) {
                $namaMesinAsli    = $items[0]['data']->mesin->nama_mesin ?? 'HOTPRESS 1';
                $namaMesinSingkat = $this->getNamaMesinSingkat($namaMesinAsli, $shiftStr);

                $totalHargaProdukHp3   = 0;
                $totalHargaBahanGlobal = 0;

                // =============================================================
                // 1. JURNAL HASIL PRODUKSI (Debit produk, Kredit HPP non-HP3)
                // =============================================================
                foreach ($items as $item) {
                    $tipe   = $item['tipe'];
                    $data   = $item['data'];

                    $u      = $data->barangSetengahJadi->ukuran ?? null;
                    $jk     = $data->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                    $gr     = $data->barangSetengahJadi->grade->nama_grade ?? 'uty lokal';
                    $tebal  = $u->tebal ?? 0;
                    $banyak = $data->isi ?? 0;
                    $m3     = ($u->panjang * $u->lebar * $tebal * $banyak) / 10_000_000;

                    $akunProduk = $this->getAkunProduk($tipe, $tebal, $jk, $gr);
                    $hargaHpp   = $this->getHargaHpp($tipe, $tebal, $jk, $gr);
                    $m3Round    = round($m3, 4);

                    // DEBIT — Hasil Produksi
                    $rows[] = $this->makeRow(
                        $akunProduk['nama'], $akunProduk['no'],
                        $tglStr, $namaMesinSingkat,
                        $akunProduk['nama'], 'd', 'b',
                        $banyak, $m3Round, $hargaHpp
                    );

                    $totalProdValue = $banyak * $hargaHpp;

                    if ($mId != $hp3Id) {
                        // KREDIT — HPP (HP1 & HP2)
                        $akunHpp = $this->getAkunHpp($tipe);
                        $rows[]  = $this->makeRow(
                            $akunHpp['nama'], $akunHpp['no'],
                            $tglStr, $namaMesinSingkat,
                            '', 'k', '', '', '', $totalProdValue
                        );
                    } else {
                        $totalHargaProdukHp3 += $totalProdValue;
                    }
                }

                // =============================================================
                // 2. BIAYA & GAJI — hanya di HP3
                // =============================================================
                if ($mId == $hp3Id) {

                    // ---------------------------------------------------------
                    // FIX: Kumpulkan & GABUNG bahan veneer berdasarkan no akun
                    // Solusi untuk masalah C: 260 F/B sengon terpecah 2 baris
                    // ---------------------------------------------------------
                    $veneerMap = [];

                    foreach ($prod->bahanHotpress as $bahan) {
                        $u      = $bahan->barangSetengahJadi->ukuran ?? null;
                        $jkAsli = $bahan->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                        $grAsli = $bahan->barangSetengahJadi->grade->nama_grade ?? '';
                        $tebal  = $u->tebal ?? 0;
                        $banyak = $bahan->isi ?? 0;
                        $m3     = ($u->panjang * $u->lebar * $tebal * $banyak) / 10_000_000;

                        $akunVeneer  = $this->getAkunVeneer($tebal, $jkAsli, $grAsli);
                        $isPpc       = str_contains($akunVeneer['nama'], 'ppc');
                        $hargaVeneer = $this->getHargaVeneer($tebal, $jkAsli, $isPpc);

                        $tipeVeneer = ($tebal < 1) ? '260 F/B' : '130 Core';
                        $ketVeneer  = "{$tipeVeneer} {$jkAsli} uk {$tebal}";

                        // Gabung berdasarkan no akun + tebal (pisah per ukuran agar
                        // misal meranti 0.5 dan 0.3 tidak ikut tergabung jadi 1 baris)
                        $groupKey = $akunVeneer['no'] . '_' . $tebal;

                        if (!isset($veneerMap[$groupKey])) {
                            $veneerMap[$groupKey] = [
                                'akun'   => $akunVeneer,
                                'ket'    => $ketVeneer,
                                'banyak' => 0,
                                'm3'     => 0.0,
                                'harga'  => $hargaVeneer,
                            ];
                        }
                        $veneerMap[$groupKey]['banyak'] += $banyak;
                        $veneerMap[$groupKey]['m3']     += $m3;
                    }

                    // Tulis baris veneer yang sudah digabung
                    foreach ($veneerMap as $v) {
                        $m3Round = round($v['m3'], 4);
                        $totalHargaBahanGlobal += ($m3Round * $v['harga']);

                        $rows[] = $this->makeRow(
                            $v['akun']['nama'], $v['akun']['no'],
                            $tglStr, $namaMesinSingkat,
                            $v['ket'], 'k', 'm',
                            $v['banyak'], $m3Round, $v['harga']
                        );
                    }

                    // ---------------------------------------------------------
                    // Bahan Penolong (Kredit)
                    // ---------------------------------------------------------
                    foreach ($prod->bahanPenolongHp as $penolong) {
                        $akunPenolong = $this->getAkunPenolong($penolong->nama_bahan);
                        $banyak       = $penolong->jumlah;

                        $masterPenolong = BahanPenolongProduksi::where('nama_bahan_penolong', $penolong->nama_bahan)->first();
                        $hargaPenolong  = $masterPenolong ? $masterPenolong->harga : 50000;

                        $totalHargaBahanGlobal += ($banyak * $hargaPenolong);

                        $rows[] = $this->makeRow(
                            $akunPenolong['nama'], $akunPenolong['no'],
                            $tglStr, $namaMesinSingkat,
                            '', 'k', 'b', $banyak, '', $hargaPenolong
                        );
                    }

                    // ---------------------------------------------------------
                    // FIX: Hutang Gaji (Kredit) + masukkan ke totalHargaBahanGlobal
                    // Solusi untuk masalah D: selisih hpp ~4,500,000
                    // Jurnal hanya 1 baris Hutang Gaji (debit Biaya Gaji di modul penggajian)
                    // ---------------------------------------------------------
                    if ($jumlahPekerja > 0) {
                        $akunGaji  = $this->getAkunGaji();
                        $totalGaji = $jumlahPekerja * $hargaPegawaiMaster;

                        // Gaji masuk ke total bahan agar hpp penyeimbang akurat
                        $totalHargaBahanGlobal += $totalGaji;

                        $rows[] = $this->makeRow(
                            $akunGaji['hutang']['nama'], $akunGaji['hutang']['no'],
                            $tglStr, $namaMesinSingkat,
                            '', 'k', 'b', $jumlahPekerja, '', $hargaPegawaiMaster
                        );
                    }

                    // ---------------------------------------------------------
                    // HPP Penyeimbang HP3 (Debit)
                    // nilaiHppHp3 = total bahan − total nilai produk HP3
                    // ---------------------------------------------------------
                    $nilaiHppHp3 = $totalHargaBahanGlobal - $totalHargaProdukHp3;

                    if ($nilaiHppHp3 != 0) {
                        $akunHppGlobal = $this->getAkunHpp('triplek');
                        $rows[]        = $this->makeRow(
                            $akunHppGlobal['nama'], $akunHppGlobal['no'],
                            $tglStr, $namaMesinSingkat,
                            '', 'd', '', '', '', $nilaiHppHp3
                        );
                    }
                }
            }

            // Baris kosong pemisah antar shift
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }

    // =========================================================================
    // MAP: inject formula Excel di kolom Total (N)
    // =========================================================================
    public function map($row): array
    {
        $this->rowIndex++;

        // Header & baris kosong → langsung return
        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r        = $this->rowIndex;
        $row[13]  = "=IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r}))";

        return $row;
    }
}