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
    // MAPPING AKUN PRODUK (Pencocokan Clean Text)
    // =========================================================================
    private function getAkunProduk(string $tipe, float $tebal, string $jenisKayu, string $grade): array
    {
        $tipeStr     = strtolower(trim($tipe));
        $gradeStr    = strtolower(trim($grade));
        $kayuSingkat = str_contains(strtolower($jenisKayu), 'sengon') ? 's' : 'm';
        
        // Memastikan angka tebal berupa integer utuh (misal: 12.00 jadi 12)
        $tebalVal = fmod($tebal, 1) == 0 ? (int)$tebal : $tebal;
        $sfx      = $this->isWhn() ? 'MTH' : 'WJY';

        if ($tipeStr === 'platform') {
            $baseNama = "platform {$tebalVal} {$gradeStr}";
        } else {
            $baseNama = "{$tebalVal}{$kayuSingkat} {$gradeStr}";
        }

        $namaAkun = "{$baseNama} {$sfx}";
        
        // Normalisasi total input teks: hilangkan spasi dan samakan variasi bahasa english/indo
        $cleanInput = str_replace([' ', 'local', '_', '-'], ['', 'lokal', '', ''], strtolower($baseNama));

        // DI SINI TEMPAT ANDA MENGGANTI NOMOR JIKA ADA YANG MELeset LAGI
        $rawDaftarAkun = [
            '1506.01' => '110x70x2 lp', '1506.02' => '110x70x3 lp', '1506.03' => '45x45x2 lp', '1506.04' => '12fm mentah',
            '1506.05' => '12m better', '1506.06' => '12m better lokal', '1506.07' => '12m pg', '1506.08' => '62x51x2 lp',
            '1506.09' => '15fm', '1506.10' => '15m better', '1506.11' => '15m better lokal', '1506.12' => '15m uty lokal',
            '1506.13' => '15s uty lokal', '1506.14' => '15s aj', '1506.15' => '18fm', '1506.16' => '18m better',
            '1506.17' => '18m better lokal', '1506.18' => '18m uty lokal', '1506.19' => '18s aj', '1506.20' => '3 flexi',
            '1506.21' => '3m uty lokal', '1506.22' => '4m better', '1506.23' => '5m better', '1506.24' => '5m uty',
            '1506.25' => '5m uty lokal', '1506.26' => '5s uty lokal', '1506.27' => '8fm', '1506.28' => '8m better',
            '1506.29' => '8m better lokal', '1506.30' => '8m uty lokal', '1506.31' => '8s aj', '1506.32' => '9fm',
            '1506.33' => '9m better', '1506.34' => '9m better lokal', '1506.35' => '9m uty lokal', '1506.36' => '9s aj',
            '1506.37' => 'karet mentah', '1506.38' => 'log core afkir', '1506.40' => 'palet afkir/bs',
            '1506.41' => 'papir uk 110x70x2', 
            '1506.42' => 'platform 12 pg',  // <-- INI SUDAH SAYA PERBAIKI SESUAI INSTRUKSI ANDA
            '1506.43' => 'papir uk 62x51x2',
            '1506.44' => 'pinus mentah', '1506.45' => 'platform 11 fm', '1506.46' => 'platform 11 uty',
            '1506.47' => 'platform 12 fm', '1506.48' => 'platform 12 uty', '1506.49' => 'platform 14 fm',
            '1506.50' => 'platform 15 fm', '1506.51' => 'platform 18 aj', '1506.52' => 'platform 18 uty',
            '1506.53' => 'platform 8 fm', '1506.54' => 'platform 8 uty', '1506.55' => 'platform 9 uty',
            '1506.56' => 'poliester 110x70x2', '1506.57' => 'poliester 110x70x3', '1506.58' => 'poliester 45x45x2',
            '1506.59' => 'poliester 62x51x2', '1506.60' => 'potongan bs', '1506.61' => 'semi mentah',
            '1506.62' => '12m uty lokal', '1506.63' => '3m better', '1506.64' => '8m uty', '1506.65' => '9m uty',
            '1506.66' => '12m uty', '1506.67' => '15m uty', '1506.68' => '18m uty', 
            '1506.69' => 'papir uk 45x45x2', // <-- Bertukar dengan 1506.42
            '1506.70' => '12s aj', '1506.71' => 'platform 11 pg', '1506.72' => '15s pg', '1506.73' => 'platform 8 pg',
            '1506.74' => 'platform 15 pg', '1506.75' => 'platform 14 pg', '1506.76' => 'platform 15 uty',
            '1506.77' => 'platform 18 pg', '1506.78' => '3m pg', '1506.79' => '5m pg', '1506.80' => '8m pg',
            '1506.81' => '9m pg', '1506.82' => '15m pg', '1506.83' => '18m pg', '1506.84' => '5s aj',
            '1506.85' => '8s uty lokal', '1506.86' => '9s uty lokal', '1506.87' => '12s uty lokal',
            '1506.88' => '5s pg', '1506.89' => '8s pg', '1506.90' => '9s pg', '1506.91' => '12s pg',
            '1506.92' => '18s pg', '1506.93' => 'platform 9 pg'
        ];

        foreach ($rawDaftarAkun as $no => $namaMaster) {
            $cleanMaster = str_replace([' ', 'local', '_', '-'], ['', 'lokal', '', ''], strtolower($namaMaster));
            if ($cleanInput === $cleanMaster) {
                return ['nama' => $namaAkun, 'no' => $no];
            }
        }

        return ['nama' => $namaAkun, 'no' => ''];
    }

    // =========================================================================
    // MAPPING AKUN VENEER
    // =========================================================================
    private function getAkunVeneer(float $tebal, string $jenisKayu, string $grade = ''): array
    {
        $kayu = str_contains(strtolower($jenisKayu), 'sengon') ? 'sengon' : 'meranti';
        $sf   = $this->isWhn() ? 'WHN' : 'WJY';
        $ext  = $this->isWhn() ? '01'  : '00';

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
    // =========================================================================
    private function getAkunPenolong(string $namaBahan): array
    {
        $namaLower = strtolower(trim($namaBahan));

        $daftarPenolong = [
            'lem_aruki'      => ['nama' => 'Lem Aruki',          'no' => '1507.20'],
            'lem aruki'      => ['nama' => 'Lem Aruki',          'no' => '1507.20'],
            'lem_dover'      => ['nama' => 'Lem Dover',          'no' => '1507.20'],
            'lem dover'      => ['nama' => 'Lem Dover',          'no' => '1507.20'],
            'dover'          => ['nama' => 'Lem Dover',          'no' => '1507.20'],
            'lem_hq'         => ['nama' => 'Lem HQ',             'no' => '1507.57'],
            'lem hq'         => ['nama' => 'Lem HQ',             'no' => '1507.57'],
            'tepung_wjy'     => ['nama' => 'Tepung',             'no' => '1507.62'],
            'tepung wjy'     => ['nama' => 'Tepung',             'no' => '1507.62'],
            'tepung'         => ['nama' => 'Tepung',             'no' => '1507.16'],
            'hadner'         => ['nama' => 'Hadner',             'no' => '1507.11'],
            'hardener'       => ['nama' => 'Hadner',             'no' => '1507.11'],
            'hdr'            => ['nama' => 'Hadner',             'no' => '1507.11'],
            'pewarna'        => ['nama' => 'Pewarna',            'no' => '1507.49'],
            'isolasi_coklat' => ['nama' => 'isolasi coklat WHN', 'no' => '1507.35'],
            'isolasi coklat' => ['nama' => 'isolasi coklat WHN', 'no' => '1507.35'],
            'isolasi_putih'  => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'isolasi putih'  => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'solasi putih'   => ['nama' => 'isolasi putih',      'no' => '1507.36'],
            'solasi'         => ['nama' => 'isolasi putih',      'no' => '1507.36'],
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
                    '4.7'  => 65400,  '7.7'  => 96800,  '9.1'  => 100800,
                    '12.1' => 97400,  '12.4' => 103300, '15.1' => 132300,
                    '15.5' => 153800, '18.5' => 173800,
                ];
                return $p[$tbl] ?? 96800;
            } elseif (str_contains($gr, 'fm')) {
                $p = ['9' => 156843, '12' => 207903, '15' => 258963, '18' => 291708];
                return $p[(string) round($tebal)] ?? 207903;
            } else {
                $p = [
                    '4.7'  => 47000,  '7.7'  => 66400,  '9.1'  => 47000,
                    '12.1' => 107100, '12.4' => 106400, '15.1' => 122900,
                    '15.5' => 125400, '18.5' => 135900,
                ];
                return $p[$tbl] ?? 47000;
            }
        } else {
            if (str_contains($gr, 'better')) {
                $p = ['5.1' => 70300, '8.2' => 78300, '8.7' => 94300];
                return $p[$tbl] ?? 78300;
            } elseif (str_contains($gr, 'lokal')) {
                $p = [
                    '5.1'  => 70300,  '8.2'  => 90800,  '11.9' => 95800,
                    '14.8' => 126300, '17.8' => 156800, '16.1' => 126300,
                    '19.1' => 177300,
                ];
                return $p[$tbl] ?? 90800;
            } else {
                $p = [
                    '2.8'  => 40000,  '5.1'  => 65500,  '8.2'  => 79500,
                    '8.7'  => 76500,  '11.9' => 103500, '14.8' => 125500,
                    '17.8' => 150300, '16.1' => 125500, '19.1' => 150300,
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
        $jns = str_contains(strtolower(trim($jenisKayu)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayuObj = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
        if (!$jenisKayuObj) {
            return 0.0;
        }

        if ($isPpc) {
            $kelompok = ($tebal < 1) ? 'ppc_faceback' : 'ppc_core';
        } else {
            $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        }

        $ukuranOptions = $kelompok === 'faceback'
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ($kelompok === 'ppc_faceback' ? ['ppc_faceback'] : [$kelompok]);

        $kwOptions = array_map(function($opt) {
            return 'KW 1 - ' . ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $hargaVeneer = \App\Models\ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayuObj->id)
            ->where('jenis_barang', 'Veneer Jadi')
            ->whereIn('kw', $kwOptions)
            ->first();

        if ($hargaVeneer) {
            return (float) $hargaVeneer->harga;
        }

        // Fallback legacy hardcoded
        if ($isPpc) return 1700000;
        if ($tebal >= 1) {
            return ($jns === 'Sengon') ? 2250000 : 2800000;
        } else {
            return ($jns === 'Sengon') ? 4000000 : 10000000;
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
        $defaultGaji        = $this->isWhn() ? 150000 : 115000;
        $hargaPegawaiMaster = HargaPegawai::first()->harga ?? $defaultGaji;

        foreach ($produksis as $prod) {
            $shiftStr     = $prod->shift ?? 'pagi';
            $hasilByMesin = [];

            foreach ($prod->triplekHasilHp as $t) {
                $hasilByMesin[$t->id_mesin][] = ['tipe' => 'triplek',  'data' => $t];
            }
            foreach ($prod->platformHasilHp as $p) {
                $hasilByMesin[$p->id_mesin][] = ['tipe' => 'platform', 'data' => $p];
            }

            $jumlahMesin = count($hasilByMesin);
            $hp3Id = null;
            if ($jumlahMesin === 1) {
                $hp3Id = array_key_first($hasilByMesin);
            } else {
                foreach ($hasilByMesin as $mId => $items) {
                    $namaMesin = strtolower($items[0]['data']->mesin->nama_mesin ?? '');
                    if (str_contains($namaMesin, '3')) { $hp3Id = $mId; break; }
                }
                if (!$hp3Id) $hp3Id = array_key_last($hasilByMesin);
            }

            $jumlahPekerja = $prod->detailPegawaiHp->count();

            foreach ($hasilByMesin as $mId => $items) {
                $namaMesinAsli    = $items[0]['data']->mesin->nama_mesin ?? 'HOTPRESS 1';
                $namaMesinSingkat = $this->getNamaMesinSingkat($namaMesinAsli, $shiftStr);

                $totalHargaProdukHp3   = 0;
                $totalHargaBahanGlobal = 0;

                // 1. JURNAL HASIL PRODUKSI
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

                // 2. BIAYA & GAJI (HP3)
                if ($mId == $hp3Id) {

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

                    // Bahan Penolong (Kredit)
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

                    // Hutang Gaji (Kredit)
                    if ($jumlahPekerja > 0) {
                        $akunGaji  = $this->getAkunGaji();
                        $totalGaji = $jumlahPekerja * $hargaPegawaiMaster;

                        $totalHargaBahanGlobal += $totalGaji;

                        $rows[] = $this->makeRow(
                            $akunGaji['hutang']['nama'], $akunGaji['hutang']['no'],
                            $tglStr, $namaMesinSingkat,
                            '', 'k', 'b', $jumlahPekerja, '', $hargaPegawaiMaster
                        );
                    }

                    // HPP Penyeimbang HP3 (Debit)
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

            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }

    // =========================================================================
    // MAP: inject formula Excel
    // =========================================================================
    public function map($row): array
    {
        $this->rowIndex++;

        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r        = $this->rowIndex;
        $row[13]  = "=IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r}))";

        return $row;
    }
}