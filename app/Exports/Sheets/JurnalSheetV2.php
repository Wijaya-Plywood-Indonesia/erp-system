<?php

namespace App\Exports\Sheets;

use App\Models\BahanPenolongProduksi;
use App\Models\JenisKayu;
use App\Models\ReferensiHargaProduksi;
use App\Services\CoaAliasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
// - Bahan penolong yang tidak dikenali fallback ke akun umum
//   1402.0 Persediaan Bahan Baku.
// - Baris selisih debit-kredit pakai 5069.2 Selisih harga patok produksi.
//   Arah selisih dihitung: debit > kredit => kredit, sebaliknya => debit.
// - Harga bahan penolong dibaca dari master BahanPenolongProduksi
//   (kategori joint), TIDAK di-hardcode.
// - Kolom "ID Barang" (O) diisi hasil resolve API eksternal
//   /api/barang/resolve-veneer (HASIL = Veneer Jadi, MODAL = Veneer Kering),
//   di-cache per kombinasi parameter.
// ============================================================
class JurnalSheetV2 implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    protected CoaAliasService $coaAlias;

    /**
     * Cache harga bahan penolong dari master: nama (lowercase) => harga.
     * Static karena JoinJurnalSource membuat objek sheet baru per produksi;
     * hanya hidup selama satu request, jadi perubahan master langsung terbaca
     * saat halaman dimuat ulang.
     *
     * @var array<string, float>|null
     */
    private static ?array $bahanCache = null;

    /**
     * Cache hasil resolve id_barang, dikunci per kombinasi parameter
     * (jenis_veneer|bagian|jenis_kayu|ketebalan|ukuran|kw).
     */
    private array $idBarangCache = [];

    public function __construct(protected $rawCollection, ?CoaAliasService $coaAlias = null)
    {
        $this->coaAlias = $coaAlias ?? new CoaAliasService;
    }

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
                    // Rumus sama dengan JurnalSheetRows::total() (dibulatkan ke rupiah).
                    $sheet->getCell("N{$row}")->setValue(
                        "=ROUND(IF(J{$row}=\"m\",M{$row}*L{$row},IF(J{$row}=\"b\",M{$row}*K{$row},M{$row})), 0)"
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
     * Ambil ReferensiHargaProduksi utuh (bukan cuma harga int),
     * supaya bisa dialiaskan lewat CoaAliasService::extractAkunVeneer().
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
     * Harga patok: DB dulu, fallback hardcode.
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

    /**
     * Harga satuan bahan penolong dari master BahanPenolongProduksi
     * (kategori joint). Cocok persis dulu, lalu cocok sebagian.
     * Kalau tidak ada harga di master, kembalikan 0 (tidak mengarang angka)
     * dan catat ke log supaya master bisa dilengkapi.
     */
    private function getHargaBahan(string $namaBahan): float
    {
        if (self::$bahanCache === null) {
            self::$bahanCache = [];
            BahanPenolongProduksi::where('kategori_produksi', 'joint')
                ->get()
                ->each(function (BahanPenolongProduksi $b) {
                    self::$bahanCache[strtolower(trim($b->nama_bahan_penolong))] = (float) ($b->harga ?? 0);
                });
        }

        $nama = strtolower(trim($namaBahan));

        // 1. Cocok persis
        $persis = self::$bahanCache[$nama] ?? 0;
        if ($persis > 0) {
            return $persis;
        }

        // 2. Cocok sebagian (nama mengandung kunci master, atau sebaliknya)
        foreach (self::$bahanCache as $kunci => $harga) {
            if ($harga > 0 && $kunci !== '' && $nama !== '' && (str_contains($nama, $kunci) || str_contains($kunci, $nama))) {
                return $harga;
            }
        }

        Log::warning('[JurnalJoin] Harga bahan penolong tidak ditemukan di master', ['bahan' => $namaBahan]);

        return 0;
    }

    /**
     * Resolve id_barang dari API eksternal /api/barang/resolve-veneer,
     * dengan cache per kombinasi parameter. Mengembalikan null (bukan
     * melempar exception) kalau API gagal/timeout.
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

        // Periksa: parameter false = domain WJY (ext 00). Pastikan sesuai untuk Join.
        $akunGaji = $this->coaAlias->getAkunGaji(false);
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
                $totalValue = round($m3 * $hargaPatok, 0);

                // Hasil selalu "Veneer Jadi"
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

            // 2. KREDIT: Modal (akun digabung, id_barang dianggap "Veneer Kering")
            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');

                $ref = $this->getRefVeneerDb($jnsNorm, (float) $ukuran->tebal, $isAf);
                [$noAkun, $namaAkun] = $this->getAkunVeneerJadi($ref, (float) $ukuran->tebal, $isAf);
                $keterangan = ($isAf ? 'af ' : '130 ').strtolower($modal->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;

                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($ref, $jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = round($m3 * $hargaPatok, 0);

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

            // 3. KREDIT: Bahan penolong (harga dari master, akun COA baru, fallback 1402.0)
            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlah = (float) ($bahan->jumlah ?? 0);
                if ($jumlah > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';

                    [$akun, $namaAkun] = $this->getAkunBahan($namaBahanRaw);
                    $hargaH = $this->getHargaBahan($namaBahanRaw);
                    $total = round($hargaH * $jumlah, 0);

                    // Nama akun persis sama dengan COA; nama bahan asli ditaruh di Keterangan
                    $keterangan = ucfirst(strtolower(trim($namaBahanRaw)));

                    $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $akun, $keterangan, 'k', $jumlah, 0, $hargaH, $total, 'b');
                    $totalKredit += $total;
                }
            }

            // 4. KREDIT: Gaji -> Hutang Gaji
            $jmlPekerja = (int) $produksi->pegawaiJoint->count();
            if ($jmlPekerja > 0) {
                $jurnalBlock[] = $this->makeRow($akunGaji['hutang']['nama'], $tglFormat, $akunGaji['hutang']['no'], '', 'k', $jmlPekerja, 0, 150000, ($jmlPekerja * 150000), 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // 5. Selisih debit-kredit -> 5069.2 Selisih harga patok produksi
            //    debit > kredit => selisih di kredit; kredit > debit => selisih di debit.
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                $dkSelisih = $selisih > 0 ? 'k' : 'd';
                $jurnalBlock[] = $this->makeRow($akunHpp['nama'], $tglFormat, $akunHpp['no'], '', $dkSelisih, 0, 0, abs($selisih), abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 15, '');
        }

        return $rows;
    }
}
