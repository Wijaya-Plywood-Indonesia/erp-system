<?php

namespace App\Exports\Sheets;

use App\Models\JenisKayu;
use App\Models\ReferensiHargaProduksi;
use App\Services\CoaAliasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// SHEET 4: JURNAL BARU — COA GENERAL (pakai CoaAliasService)
// ------------------------------------------------------------
// Perbedaan dari JurnalSheet (COA lama):
// - Veneer jadi TIDAK dipecah per jenis kayu (sengon/meranti), semua
//   masuk ke satu akun sesuai kelompok face-back / core / ppc.
// - Nomor & nama akun mengikuti daftar COA baru (1402.x, 2195.x, 5069.x),
//   diambil lewat CoaAliasService (fallback ke mapping manual kalau
//   data ReferensiHargaProduksi/subAnakAkun tidak ditemukan).
// - Bahan penolong yang tidak dikenali (bukan lem/tepung/hardner/dst)
//   fallback ke akun umum 1402.0 Persediaan Bahan Baku.
// - Baris selisih debit-kredit pakai 5069.2 Selisih harga patok produksi.
// - Perhitungan harga patok / harga bahan / m3 tetap memakai logic yang
//   sama seperti JurnalSheet (tidak diubah), hanya akun & nama akun yang
//   di-general-kan lewat CoaAliasService.
// - NEW: Kolom "ID Barang" (O) diisi dengan hasil resolve dari API
//   eksternal /api/barang/resolve-veneer, mengikuti pola yang sama
//   dipakai di JurnalRepairSheetV2 & LaporanProduksiHotPressJurnalSheetV2.
//   Resolver dipanggil untuk baris HASIL (debit, selalu "Veneer Jadi")
//   dan baris MODAL (kredit, dianggap "Veneer Kering" — bahan mentah
//   sebelum diproses jadi veneer jadi), sesuai peran masing-masing di
//   proses dryer/join. Baris bahan penolong, gaji, dan selisih HPP
//   tidak relevan dengan ID Barang veneer, jadi tetap null. Hasil
//   di-cache per kombinasi parameter supaya tidak memanggil API
//   berkali-kali untuk kombinasi ukuran/jenis/kw yang identik.
// ============================================================
class JurnalSheetV2 implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    protected CoaAliasService $coaAlias;

    public function __construct(protected $rawCollection, ?CoaAliasService $coaAlias = null)
    {
        $this->coaAlias = $coaAlias ?? new CoaAliasService;
    }

    /**
     * Cache hasil resolve id_barang dari API eksternal, dikunci per
     * kombinasi parameter (jenis_veneer|bagian|jenis_kayu|ketebalan|
     * ukuran|kw) supaya tidak memanggil API berkali-kali untuk
     * kombinasi yang sama dalam satu laporan.
     */
    private array $idBarangCache = [];

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, 'B' => 15, 'C' => 12, 'D' => 12, 'E' => 8, 'F' => 8,
            'G' => 15, 'H' => 45, 'I' => 8, 'J' => 8, 'K' => 14, 'L' => 16, 'M' => 16, 'N' => 22, 'O' => 12,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '@',              // No Akun sebagai TEKS -> titik desimal tidak pernah dikonversi jadi koma
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '99CC99']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:O{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("O2:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($row = 2; $row <= $lastRow; $row++) {
                // Paksa No Akun (kolom D) sebagai string eksplisit, supaya Excel
                // tidak auto-convert "1402.8" jadi angka lalu ditampilkan "1402,80".
                $noAkunVal = $sheet->getCell("D{$row}")->getValue();
                if ($noAkunVal !== null && $noAkunVal !== '') {
                    $sheet->getCell("D{$row}")->setValueExplicit((string) $noAkunVal, DataType::TYPE_STRING);
                }

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
     * Ambil $ref ReferensiHargaProduksi utuh (bukan cuma harga int),
     * supaya bisa dialiaskan lewat CoaAliasService::extractAkunVeneer().
     * Logic pencarian tetap sama seperti versi lama (getHargaVeneerDb).
     */
    private function getRefVeneerDb(string $jenis, float $tebal, bool $isAf): ?ReferensiHargaProduksi
    {
        $jns = str_contains(strtolower(trim($jenis)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayu = JenisKayu::where('nama_kayu', $jns)->first();
        if (! $jenisKayu) {
            return null;
        }

        $kelompok = $isAf
            ? (($tebal < 1) ? 'ppc_faceback' : 'ppc_core')
            : (($tebal < 1) ? 'faceback' : 'core');

        $ukuranOptions = $kelompok === 'faceback'
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ($kelompok === 'ppc_faceback' ? ['ppc_faceback'] : [$kelompok]);

        $kwOptions = array_map(
            fn ($opt) => 'KW 1 - '.ucfirst(str_replace('_', ' ', $opt)),
            $ukuranOptions
        );

        return ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->whereHas('kategoriBarang', fn ($q) => $q->where('nama_kategori', 'Veneer Jadi'))
            ->whereIn('nama', $kwOptions)
            ->with('subAnakAkun')
            ->first();
    }

    /**
     * Harga patok tetap pakai logic lama (DB dulu, fallback hardcode).
     */
    private function getHargaPatok(?ReferensiHargaProduksi $ref, string $jenis, float $tebal, bool $isAf = false): int
    {
        if ($ref && (float) $ref->harga > 0) {
            return (int) $ref->harga;
        }

        $jns = $this->normalizeJenis($jenis);

        if ($isAf) {
            return ($jns === 'sengon') ? 1500000 : 1800000;
        }

        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $harga = [
            'sengon' => ['faceback' => 4000000, 'core' => 2250000],
            'meranti' => ['faceback' => 12500000, 'core' => 2800000],
        ];

        return $harga[$jns][$kelompok] ?? 0;
    }

    /**
     * Akun veneer jadi: alias lewat CoaAliasService kalau $ref ada dan
     * dikenali, fallback ke mapping manual (COA baru) kalau tidak
     * ketemu di DB atau hasilnya UNKNOWN.
     *
     * @return array{0: string, 1: string} [noAkun, namaAkun]
     */
    private function getAkunVeneerJadi(?ReferensiHargaProduksi $ref, float $tebal, bool $isAf): array
    {
        if ($ref) {
            [$namaAkunBaru, $noAkunBaru] = $this->coaAlias->extractAkunVeneer($ref);
            if ($namaAkunBaru !== 'UNKNOWN') {
                return [$noAkunBaru, $namaAkunBaru];
            }
        }

        if ($isAf) {
            return ['1402.18', 'Persediaan Veneer Jadi PPC'];
        }

        return ($tebal < 1)
            ? ['1402.7', 'Persediaan Veneer Jadi Face Back']
            : ['1402.8', 'Persediaan Veneer Jadi Core'];
    }

    /**
     * Akun bahan penolong: alias lewat CoaAliasService, fallback ke
     * 1402.0 Persediaan Bahan Baku kalau tidak ada keyword yang cocok.
     *
     * @return array{0: string, 1: string} [noAkun, namaAkun]
     */
    private function getAkunBahan(string $namaBahan): array
    {
        [$namaAkun, $noAkun] = $this->coaAlias->extractAkunPenolong(null, $namaBahan);

        if ($namaAkun === 'UNKNOWN') {
            return ['1402.0', 'Persediaan Bahan Baku'];
        }

        return [$noAkun, $namaAkun];
    }

    // Harga satuan per bahan tetap sama seperti sebelumnya (default 15000,
    // override untuk aruki/dover/tepung). Silakan sesuaikan bila ada
    // harga bahan lain yang perlu di-hardcode juga.
    private function getHargaBahan(string $namaBahan): int
    {
        $nama = strtolower(trim($namaBahan));

        if (str_contains($nama, 'aruki')) {
            return 6900;
        }
        if (str_contains($nama, 'dover')) {
            return 6950;
        }
        if (str_contains($nama, 'tepung')) {
            return 4500;
        }

        return 15000;
    }

    /**
     * Resolve id_barang dari API eksternal /api/barang/resolve-veneer,
     * dengan cache per kombinasi parameter supaya tidak memanggil API
     * berkali-kali untuk kombinasi ukuran/jenis/kw yang identik dalam
     * satu laporan. Mengembalikan null (bukan melempar exception) kalau
     * API gagal/timeout, supaya proses export tidak gagal total hanya
     * karena resolver down.
     *
     * @param  string  $jenisVeneer  'Veneer Jadi' atau 'Veneer Kering'
     */
    private function resolveIdBarang(
        string $jenisVeneer,
        bool $isAf,
        float $tebal,
        string $jenisKayu,
        float $panjang,
        float $lebar,
        string $kw
    ): ?int {
        $bagian = $isAf ? 'PPC' : ($tebal < 1 ? 'Face Back' : 'Core');
        $ukuran = $panjang.'x'.$lebar;

        $cacheKey = implode('|', [
            $jenisVeneer,
            $bagian,
            strtolower(trim($jenisKayu)),
            $tebal,
            $ukuran,
            strtolower(trim($kw)),
        ]);

        if (array_key_exists($cacheKey, $this->idBarangCache)) {
            return $this->idBarangCache[$cacheKey];
        }

        $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/').'/api/barang/resolve-veneer';

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($urlApi, [
                'jenis_veneer' => $jenisVeneer,
                'bagian' => $bagian,
                'jenis_kayu' => $jenisKayu,
                'ketebalan' => $tebal,
                'ukuran' => $ukuran,
                'kw' => $kw,
            ]);

            $idBarang = $response->json('id_barang');
        } catch (\Throwable $e) {
            $idBarang = null;
        }

        return $this->idBarangCache[$cacheKey] = $idBarang;
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm', $idBarang = null): array
    {
        return [
            $namaAkun, (string) $tgl, '', (string) $noAkun, '', '', 'nyambung', $keterangan,
            strtolower($map), strtolower($hitKbk), (float) $banyak, (float) $m3, (float) $harga, (float) $total,
            $idBarang,
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang'];

        $akunGaji = $this->coaAlias->getAkunGaji(false); // dryer = domain WJY -> ext 00
        $akunHpp = $this->coaAlias->getAkunHpp();

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');
            $totalDebit = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            // 1. DEBIT: Hasil (veneer jadi, akun digabung tanpa split jenis kayu)
            foreach ($produksi->hasilJoint as $hasil) {
                $ukuran = $hasil->ukuran;
                $jnsNorm = $this->normalizeJenis($hasil->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($hasil->kw ?? ''), 'af');

                $ref = $this->getRefVeneerDb($jnsNorm, (float) $ukuran->tebal, $isAf);
                [$noAkun, $namaAkun] = $this->getAkunVeneerJadi($ref, (float) $ukuran->tebal, $isAf);
                $keterangan = ($isAf ? 'af ' : '130 ').strtolower($hasil->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;

                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $hasil->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($ref, $jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                // NEW: resolve id_barang — hasil selalu "Veneer Jadi"
                $idBarang = $this->resolveIdBarang(
                    'Veneer Jadi',
                    $isAf,
                    (float) $ukuran->tebal,
                    $hasil->jenisKayu->nama_kayu ?? '',
                    (float) $ukuran->panjang,
                    (float) $ukuran->lebar,
                    (string) ($hasil->kw ?? '')
                );

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm', $idBarang);
                $totalDebit += $totalValue;
            }

            // 2. KREDIT: Modal (sama seperti hasil, akun digabung)
            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');

                $ref = $this->getRefVeneerDb($jnsNorm, (float) $ukuran->tebal, $isAf);
                [$noAkun, $namaAkun] = $this->getAkunVeneerJadi($ref, (float) $ukuran->tebal, $isAf);
                $keterangan = ($isAf ? 'af ' : '130 ').strtolower($modal->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;

                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($ref, $jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                // NEW: resolve id_barang — modal dianggap "Veneer Kering"
                // (bahan mentah sebelum diproses jadi veneer jadi), sama
                // seperti pola peran modal/hasil di JurnalRepairSheetV2.
                $idBarang = $this->resolveIdBarang(
                    'Veneer Kering',
                    $isAf,
                    (float) $ukuran->tebal,
                    $modal->jenisKayu->nama_kayu ?? '',
                    (float) $ukuran->panjang,
                    (float) $ukuran->lebar,
                    (string) ($modal->kw ?? '')
                );

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm', $idBarang);
                $totalKredit += $totalValue;
            }

            // 3. KREDIT: Bahan penolong (akun sesuai COA baru, fallback 1402.0)
            // ID Barang tidak relevan untuk bahan penolong -> null.
            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlah = (float) ($bahan->jumlah ?? 0);
                if ($jumlah > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';

                    [$akun, $namaAkun] = $this->getAkunBahan($namaBahanRaw);
                    $hargaH = $this->getHargaBahan($namaBahanRaw);
                    $total = $hargaH * $jumlah;

                    // Nama akun persis sama dengan COA; nama bahan asli ditaruh di Keterangan
                    $keterangan = ucfirst(strtolower(trim($namaBahanRaw)));

                    $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $akun, $keterangan, 'k', $jumlah, 0, $hargaH, $total, 'b');
                    $totalKredit += $total;
                }
            }

            // 4. KREDIT: Gaji -> 2195.1 Hutang Gaji (ID Barang null)
            $jmlPekerja = (int) $produksi->pegawaiJoint->count();
            if ($jmlPekerja > 0) {
                $jurnalBlock[] = $this->makeRow($akunGaji['hutang']['nama'], $tglFormat, $akunGaji['hutang']['no'], '', 'k', $jmlPekerja, 0, 150000, ($jmlPekerja * 150000), 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // 5. Selisih debit-kredit -> 5069.2 Selisih harga patok produksi (ID Barang null)
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                $jurnalBlock[] = $this->makeRow($akunHpp['nama'], $tglFormat, $akunHpp['no'], '', 'd', 0, 0, abs($selisih), abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 15, '');
        }

        return $rows;
    }
}
