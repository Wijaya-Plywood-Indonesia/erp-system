<?php

namespace App\Services;

/**
 * Satu-satunya sumber kebenaran pemetaan COA baru untuk seluruh sheet V2
 * (rotary / dryer / hotpress / repair / kedi / kayu masuk / kayu keluar).
 *
 * Aturan umum COA baru:
 * - Jenis kayu (sengon / meranti / WHN / dst) TIDAK lagi dipecah per akun.
 *   Yang membedakan hanya kategori barang + ukuran/tipe.
 * - Nama jenis kayu asli tetap ditulis di kolom Keterangan, bukan di akun.
 *
 * Kalau ada akun baru yang belum terdaftar, cukup tambahkan di konstanta
 * di bawah + satu branch di method terkait. Jangan hardcode di Export.
 */
class CoaAliasService
{
    // ---------------------------------------------------------------------
    // KONSTANTA AKUN (COA BARU)
    // ---------------------------------------------------------------------

    /** Persediaan kayu bulat */
    public const AKUN_KAYU_130 = ['no' => '1402.1', 'nama' => 'Persediaan kayu 130'];

    public const AKUN_KAYU_260 = ['no' => '1402.2', 'nama' => 'Persediaan kayu 260'];

    /** Persediaan logcore / kayu sambungan */
    public const AKUN_LOGCORE_130 = ['no' => '1402.21', 'nama' => 'Persediaan Logcore 130'];

    public const AKUN_LOGCORE_260 = ['no' => '1402.22', 'nama' => 'Persediaan Logcore 260'];

    /** Veneer basah (sengon & meranti digabung) */
    public const AKUN_VENEER_BASAH_FB = ['no' => '1402.3', 'nama' => 'Persediaan Veneer Basah Face Back'];

    public const AKUN_VENEER_BASAH_CORE = ['no' => '1402.4', 'nama' => 'Persediaan Veneer Basah Core'];

    /** Veneer kering */
    public const AKUN_VENEER_KERING_FB = ['no' => '1402.5', 'nama' => 'Persediaan Veneer Kering Face Back'];

    public const AKUN_VENEER_KERING_CORE = ['no' => '1402.6', 'nama' => 'Persediaan Veneer Kering Core'];

    /** Veneer jadi (COA baru punya akun sendiri: 1402.7 FB, 1402.8 Core) */
    public const AKUN_VENEER_JADI_FB = ['no' => '1402.7', 'nama' => 'Persediaan Veneer Jadi Face Back'];

    public const AKUN_VENEER_JADI_CORE = ['no' => '1402.8', 'nama' => 'Persediaan Veneer Jadi Core'];

    /** Veneer PPC (dipakai untuk veneer AF / afalan) */
    public const AKUN_VENEER_BASAH_PPC = ['no' => '1402.16', 'nama' => 'Persediaan Veneer Basah PPC'];

    public const AKUN_VENEER_KERING_PPC = ['no' => '1402.17', 'nama' => 'Persediaan Veneer Kering PPC'];

    public const AKUN_VENEER_JADI_PPC = ['no' => '1402.18', 'nama' => 'Persediaan Veneer Jadi PPC'];

    /** Barang dalam proses (kelompok 1403) */
    public const AKUN_BARANG_SETENGAH_JADI = ['no' => '1403.0', 'nama' => 'Persediaan Barang Dalam Proses'];

    /**
     * Platform & triplek (kelompok 1403) — dipakai jurnal Hot Press.
     * - Platform bahan masuk HP (belum diproses)  -> 1403.1
     * - Triplek hasil produksi HP                 -> 1403.2
     * - Platform hasil produksi HP                -> 1403.3
     * Akun ditentukan dari JENIS barang, bukan dari kode sub_anak_akuns.
     */
    public const AKUN_PLATFORM_MENTAH = ['no' => '1403.1', 'nama' => 'Persediaan Platform Mentah'];

    public const AKUN_TRIPLEK_MENTAH = ['no' => '1403.2', 'nama' => 'Persediaan Triplek Mentah'];

    public const AKUN_PLATFORM_JADI = ['no' => '1403.3', 'nama' => 'Persediaan Platform Jadi'];

    /** Barang jadi (kelompok 1404) */
    public const AKUN_BARANG_JADI = ['no' => '1404.0', 'nama' => 'Persediaan Barang Jadi'];

    /** Gaji */
    public const AKUN_BEBAN_GAJI_HARIAN = ['no' => '5311.0', 'nama' => 'Gaji, Tunjangan, Bonus, Honorarium, THR, dsb'];

    public const AKUN_BEBAN_GAJI_BORONGAN = ['no' => '5050.0', 'nama' => 'Biaya Tenaga Kerja Langsung'];

    public const AKUN_HUTANG_GAJI = ['no' => '2195.1', 'nama' => 'Hutang Gaji'];

    /**
     * Hutang ongkos turun kayu (COA baru: 2195.2).
     * CATATAN: 2195.2 di COA baru adalah "Hutang ongkos turun kayu",
     * BUKAN gaji borongan. Gaji borongan (harian/borongan) tetap ke 2195.1.
     */
    public const AKUN_HUTANG_ONGKOS_KAYU = ['no' => '2195.2', 'nama' => 'Hutang ongkos turun kayu'];

    /** Pendapatan Usaha Lainnya (COA baru: 4199.0) */
    public const AKUN_PENDAPATAN = ['no' => '4199.0', 'nama' => 'Pendapatan Usaha Lainnya'];

    /** Kas Utama (COA baru: 1101.1) — dipakai sebagai Kas Mut di jurnal kayu masuk */
    public const AKUN_KAS_MUT = ['no' => '1101.1', 'nama' => 'Kas Utama'];

    /**
     * Bahan penolong gudang (COA baru: kelompok 1402.9 - 1402.15, 1402.23).
     * Akun lama-nya ada di kelompok 1507 "STOK GUDANG" / 115-13.
     */
    public const AKUN_LEM = ['no' => '1402.9', 'nama' => 'Persediaan Lem'];

    public const AKUN_HARDNER = ['no' => '1402.10', 'nama' => 'Persediaan Hardner'];

    public const AKUN_ISI_STAPLES = ['no' => '1402.11', 'nama' => 'Persediaan Isi Staples'];

    public const AKUN_PEWARNA = ['no' => '1402.12', 'nama' => 'Persediaan pewarna'];

    public const AKUN_TEPUNG = ['no' => '1402.13', 'nama' => 'Persediaan Tepung'];

    public const AKUN_SOLASI_COKLAT = ['no' => '1402.14', 'nama' => 'Persediaan Solasi Coklat'];

    public const AKUN_SOLASI_PUTIH = ['no' => '1402.15', 'nama' => 'Persediaan Solasi Putih'];

    public const AKUN_REELING_TAPE = ['no' => '1402.23', 'nama' => 'Persediaan Reeling Tape'];

    /** Selisih harga patok produksi (akun DE -> selalu di sisi debit) */
    public const AKUN_SELISIH_HPP = ['no' => '5069.2', 'nama' => 'Selisih harga patok produksi'];

    /** Harga Pokok Penjualan */
    public const AKUN_HPP = ['no' => '5069.1', 'nama' => 'Harga Pokok Penjualan'];

    /** Biaya Operasional Pabrik */
    public const AKUN_BEBAN_PRODUKSI = ['no' => '5069.3', 'nama' => 'Biaya Operasional Pabrik'];

    /**
     * Mapping nomor akun LAMA -> akun baru.
     * Dipakai sebagai jaring pengaman untuk payload jurnal yang masih
     * mengirim nomor akun versi lama.
     */
    private const MAP_AKUN_LAMA = [
        // kayu bulat
        '115-01' => self::AKUN_KAYU_130,
        '1411.01' => self::AKUN_KAYU_130,
        '115-02' => self::AKUN_KAYU_260,
        '1411.02' => self::AKUN_KAYU_260,

        // logcore
        '1413.01' => self::AKUN_LOGCORE_130,
        '1413.02' => self::AKUN_LOGCORE_260,
        '1414.00' => self::AKUN_LOGCORE_260,

        // veneer basah face/back
        '115-07' => self::AKUN_VENEER_BASAH_FB,
        '1421.00' => self::AKUN_VENEER_BASAH_FB,
        '1421.01' => self::AKUN_VENEER_BASAH_FB,
        '1422.00' => self::AKUN_VENEER_BASAH_FB,
        '1422.01' => self::AKUN_VENEER_BASAH_FB,

        // veneer basah core
        '115-08' => self::AKUN_VENEER_BASAH_CORE,
        '1426.00' => self::AKUN_VENEER_BASAH_CORE,
        '1426.01' => self::AKUN_VENEER_BASAH_CORE,
        '1427.00' => self::AKUN_VENEER_BASAH_CORE,
        '1427.01' => self::AKUN_VENEER_BASAH_CORE,
        '1428.00' => self::AKUN_VENEER_KERING_PPC, // Veneer Basah ppc meranti WJY -> Veneer Kering PPC (1402.17)
        '1428.01' => self::AKUN_VENEER_KERING_PPC, // Veneer Basah ppc meranti WHN -> Veneer Kering PPC (1402.17)
        '1429.00' => self::AKUN_VENEER_KERING_PPC, // Veneer Basah ppc sengon WJY -> Veneer Kering PPC (1402.17)
        '1429.01' => self::AKUN_VENEER_KERING_PPC, // Veneer Basah ppc sengon WHN -> Veneer Kering PPC (1402.17)

        // veneer kering
        '115-09' => self::AKUN_VENEER_KERING_FB,
        '1431.00' => self::AKUN_VENEER_KERING_FB,
        '1441' => self::AKUN_VENEER_KERING_FB,
        '1441.0' => self::AKUN_VENEER_KERING_FB,
        '1441.00' => self::AKUN_VENEER_KERING_FB,
        '115-10' => self::AKUN_VENEER_KERING_CORE,
        '1436.00' => self::AKUN_VENEER_KERING_CORE,
        '1446' => self::AKUN_VENEER_KERING_CORE,
        '1446.0' => self::AKUN_VENEER_KERING_CORE,
        '1446.00' => self::AKUN_VENEER_KERING_CORE,
        '1447' => self::AKUN_VENEER_KERING_PPC, // core meranti -> Veneer Kering PPC (1402.17)
        '1447.00' => self::AKUN_VENEER_KERING_PPC, // Veneer Kering 130 core meranti WJY -> Veneer Kering PPC (1402.17)
        '1447.01' => self::AKUN_VENEER_KERING_PPC, // Veneer Kering 130 core meranti WHN -> Veneer Kering PPC (1402.17)

        // gaji
        '210-02' => self::AKUN_HUTANG_GAJI,
        '2231.00' => self::AKUN_HUTANG_GAJI,

        // hpp / selisih
        '510-01' => self::AKUN_SELISIH_HPP,
        '5101.00' => self::AKUN_SELISIH_HPP,

        // bahan penolong: reeling tape
        '115-13' => self::AKUN_REELING_TAPE,   // kode lama di RotaryJurnalService::BAHAN_PENOLONG_MAP
        '1507.41' => self::AKUN_REELING_TAPE,  // 1507 STOK GUDANG: reeling tape

        // bahan penolong: solasi (kode dari sub_anak_akuns / referensi harga)
        '1507.35' => self::AKUN_SOLASI_COKLAT, // isolasi coklat WHN
        '1507.67' => self::AKUN_SOLASI_COKLAT, // isolasi coklat WJY
        '1507.36' => self::AKUN_SOLASI_PUTIH,  // isolasi putih
        '1507.66' => self::AKUN_SOLASI_PUTIH,  // isolasi putih WJY

        // bahan penolong: lem, hardner, staples, pewarna, tepung
        '1507.20' => self::AKUN_LEM,           // Lem Aruki
        '1507.21' => self::AKUN_LEM,           // Lem Dover
        '1507.22' => self::AKUN_LEM,           // Lem PAI
        '1507.57' => self::AKUN_LEM,           // lem HQ (lem putih)
        '1507.63' => self::AKUN_LEM,           // Lem Aruki WJY
        '1507.64' => self::AKUN_LEM,           // Lem Dover WJY
        '1507.65' => self::AKUN_LEM,           // Lem PAI WJY
        '1507.11' => self::AKUN_HARDNER,       // Hadner
        '1507.59' => self::AKUN_HARDNER,       // hadner WJY
        '1507.13' => self::AKUN_ISI_STAPLES,   // Isi Staples
        '1507.61' => self::AKUN_ISI_STAPLES,   // Isi Staples WJY
        '1507.49' => self::AKUN_PEWARNA,       // Pewarna
        '1507.16' => self::AKUN_TEPUNG,        // Tepung
        '1507.62' => self::AKUN_TEPUNG,        // Tepung WJY
    ];

    /**
     * Daftar nomor akun COA baru (dari file COA general). Dipakai isNomorAkunValid()
     * untuk menandai nomor akun yang belum ada di COA baru sebagai UNKNOWN.
     * Update daftar ini kalau COA bertambah.
     */
    private const NOMOR_AKUN_COA_BARU = [
        '1101.0' => true, '1101.1' => true, '1101.2' => true, '1101.3' => true, '1101.4' => true, '1101.5' => true,
        '1101.6' => true, '1101.7' => true, '1101.8' => true, '1122.0' => true, '1123.0' => true, '1124.0' => true,
        '1125.0' => true, '1131.0' => true, '1181.0' => true, '1200.0' => true, '1402.0' => true, '1402.1' => true,
        '1402.2' => true, '1402.3' => true, '1402.4' => true, '1402.5' => true, '1402.6' => true, '1402.7' => true,
        '1402.8' => true, '1402.9' => true, '1402.10' => true, '1402.11' => true, '1402.12' => true, '1402.13' => true,
        '1402.14' => true, '1402.15' => true, '1402.16' => true, '1402.17' => true, '1402.18' => true, '1402.19' => true,
        '1402.20' => true, '1402.21' => true, '1402.22' => true, '1402.23' => true, '1403.0' => true, '1403.1' => true,
        '1403.2' => true, '1403.3' => true, '1403.4' => true, '1404.0' => true, '1404.1' => true, '1404.2' => true,
        '1405.0' => true, '1421.0' => true, '1422.0' => true, '1423.0' => true, '1423.1' => true, '1423.2' => true,
        '1499.0' => true, '1499.1' => true, '1499.2' => true, '1501.0' => true, '1520.0' => true, '1523.0' => true,
        '1524.0' => true, '1525.0' => true, '1526.0' => true, '1527.0' => true, '1528.0' => true, '1529.0' => true,
        '1529.1' => true, '1530.0' => true, '1530.1' => true, '1533.0' => true, '1534.0' => true, '1551.0' => true,
        '1599.0' => true, '1600.0' => true, '1601.0' => true, '1611.0' => true, '1651.0' => true, '1658.0' => true,
        '1698.0' => true, '2102.0' => true, '2103.0' => true, '2103.1' => true, '2111.0' => true, '2186.0' => true,
        '2187.0' => true, '2191.0' => true, '2191.1' => true, '2191.2' => true, '2192.0' => true, '2195.0' => true,
        '2195.1' => true, '2195.2' => true, '2201.0' => true, '2202.0' => true, '2203.0' => true, '2203.1' => true,
        '2228.0' => true, '2228.1' => true, '2301.0' => true, '2303.0' => true, '2304.0' => true, '2312.0' => true,
        '2321.0' => true, '2322.0' => true, '2998.0' => true, '3102.0' => true, '3120.0' => true, '3200.0' => true,
        '3297.0' => true, '3298.0' => true, '3298.1' => true, '4002.0' => true, '4003.0' => true, '4011.0' => true,
        '4012.0' => true, '4013.0' => true, '4199.0' => true, '4501.0' => true, '4503.0' => true, '4511.0' => true,
        '4599.0' => true, '5050.0' => true, '5051.0' => true, '5052.0' => true, '5058.0' => true, '5059.0' => true,
        '5059.1' => true, '5059.2' => true, '5059.3' => true, '5059.4' => true, '5069.0' => true, '5069.1' => true,
        '5069.2' => true, '5069.3' => true, '5311.0' => true, '5312.0' => true, '5313.0' => true, '5314.0' => true,
        '5314.1' => true, '5314.2' => true, '5315.0' => true, '5316.0' => true, '5317.0' => true, '5318.0' => true,
        '5319.0' => true, '5320.0' => true, '5321.0' => true, '5322.0' => true, '5322.1' => true, '5322.2' => true,
        '5322.3' => true, '5322.4' => true, '5322.5' => true, '5322.6' => true, '5399.0' => true, '5405.0' => true,
        '5409.0' => true, '5421.0' => true, '5499.0' => true, '5499.1' => true,
    ];

    // ---------------------------------------------------------------------
    // KAYU MASUK / KAYU KELUAR
    // ---------------------------------------------------------------------

    /**
     * Akun persediaan kayu berdasarkan jenis kayu + panjang (cm).
     * Jenis kayu hanya dipakai untuk mendeteksi logcore; sengon / meranti /
     * WHN tetap masuk akun yang sama.
     *
     * @param  int|float|string|null  $panjang
     * @return array{no:string,nama:string}
     */
    public function getAkunKayuMasuk(string $jenisKayu, $panjang = null): array
    {
        $is130 = $this->is130($panjang);

        if ($this->isLogcore($jenisKayu)) {
            return $is130 ? self::AKUN_LOGCORE_130 : self::AKUN_LOGCORE_260;
        }

        return $is130 ? self::AKUN_KAYU_130 : self::AKUN_KAYU_260;
    }

    /** Alias supaya pemanggilan dari sisi kayu keluar lebih terbaca. */
    public function getAkunKayuKeluar(string $jenisKayu, $panjang = null): array
    {
        return $this->getAkunKayuMasuk($jenisKayu, $panjang);
    }

    /** @return string[] semua nomor akun yang dianggap "kayu/logcore" */
    public function nomorAkunKayu(): array
    {
        return [
            self::AKUN_KAYU_130['no'],
            self::AKUN_KAYU_260['no'],
            self::AKUN_LOGCORE_130['no'],
            self::AKUN_LOGCORE_260['no'],
        ];
    }

    /**
     * Hutang ongkos turun kayu → 2195.2 (COA baru).
     * Dipakai oleh LaporanJurnalKayuMasukSheet2New baris kredit pertama.
     *
     * @return array{no:string,nama:string}
     */
    public function getAkunHutangOngkosKayu(): array
    {
        return self::AKUN_HUTANG_ONGKOS_KAYU;
    }

    /**
     * Pendapatan Usaha Lainnya → 4199.0 (COA baru).
     * Dipakai oleh LaporanJurnalKayuMasukSheet2New baris kredit kedua.
     *
     * @return array{no:string,nama:string}
     */
    public function getAkunPendapatan(): array
    {
        return self::AKUN_PENDAPATAN;
    }

    /**
     * Kas Utama → 1101.1 (COA baru).
     * Dipakai oleh LaporanJurnalKayuMasukSheet2New baris kredit ketiga (Kas Mut).
     *
     * @return array{no:string,nama:string}
     */
    public function getAkunKasMut(): array
    {
        return self::AKUN_KAS_MUT;
    }

    // ---------------------------------------------------------------------

    /**
     * Akun veneer basah. Dikembalikan sebagai list [no, nama] agar bisa
     * langsung di-destructure: [$no, $nama] = $svc->getAkunVeneerBasah(true);
     *
     * @return array{0:string,1:string}
     */
    public function getAkunVeneerBasah(bool $isCore): array
    {
        $akun = $isCore ? self::AKUN_VENEER_BASAH_CORE : self::AKUN_VENEER_BASAH_FB;

        return [$akun['no'], $akun['nama']];
    }

    /** @return array{0:string,1:string} */
    public function getAkunVeneerKering(bool $isCore): array
    {
        $akun = $isCore ? self::AKUN_VENEER_KERING_CORE : self::AKUN_VENEER_KERING_FB;

        return [$akun['no'], $akun['nama']];
    }

    /** @return array{0:string,1:string} */
    public function getAkunVeneerJadi(bool $isCore): array
    {
        $akun = $isCore ? self::AKUN_VENEER_JADI_CORE : self::AKUN_VENEER_JADI_FB;

        return [$akun['no'], $akun['nama']];
    }

    /** @return string[] */
    public function nomorAkunVeneerBasah(): array
    {
        return [self::AKUN_VENEER_BASAH_FB['no'], self::AKUN_VENEER_BASAH_CORE['no']];
    }

    /** @return string[] */
    public function nomorAkunVeneerKering(): array
    {
        return [self::AKUN_VENEER_KERING_FB['no'], self::AKUN_VENEER_KERING_CORE['no']];
    }

    /** @return string[] */
    public function nomorAkunVeneerJadi(): array
    {
        return [self::AKUN_VENEER_JADI_FB['no'], self::AKUN_VENEER_JADI_CORE['no']];
    }

    // ---------------------------------------------------------------------
    // GAJI
    // ---------------------------------------------------------------------

    /**
     * @return array{beban:array{no:string,nama:string},hutang:array{no:string,nama:string}}
     */
    public function getAkunGaji(bool $isBorongan = false): array
    {
        return [
            'beban' => $isBorongan ? self::AKUN_BEBAN_GAJI_BORONGAN : self::AKUN_BEBAN_GAJI_HARIAN,
            // Borongan & harian sama-sama hutang ke 2195.1; 2195.2 khusus ongkos turun kayu.
            'hutang' => self::AKUN_HUTANG_GAJI,
        ];
    }

    /** @return string[] semua nomor akun hutang gaji */
    public function nomorAkunHutangGaji(): array
    {
        return [self::AKUN_HUTANG_GAJI['no']];
    }

    // ---------------------------------------------------------------------
    // HPP / SELISIH / BEBAN
    // ---------------------------------------------------------------------

    /**
     * Nama method historis "Hpp", tapi yang dikembalikan akun
     * Selisih harga patok produksi (5069.2), bukan HPP (5069.1).
     *
     * @return array{no:string,nama:string}
     */
    public function getAkunHpp(): array
    {
        return self::AKUN_SELISIH_HPP;
    }

    /** @return array{no:string,nama:string} */
    public function getAkunBebanProduksi(): array
    {
        return self::AKUN_BEBAN_PRODUKSI;
    }

    // ---------------------------------------------------------------------
    // MAPPING AKUN LAMA
    // ---------------------------------------------------------------------

    /**
     * Terjemahkan nomor akun lama ke COA baru. Kalau tidak dikenal,
     * kembalikan apa adanya supaya data tidak hilang diam-diam.
     *
     * @return array{no:string,nama:string}
     */
    public function mapAkunLama(string $noAkunLama, ?string $namaAkunLama = null): array
    {
        $key = trim($noAkunLama);

        if (isset(self::MAP_AKUN_LAMA[$key])) {
            return self::MAP_AKUN_LAMA[$key];
        }

        return ['no' => $noAkunLama, 'nama' => $namaAkunLama ?? $noAkunLama];
    }

    public function isAkunLamaDikenal(string $noAkunLama): bool
    {
        return isset(self::MAP_AKUN_LAMA[trim($noAkunLama)]);
    }

    /**
     * Apakah nomor akun terdaftar di COA baru (lihat NOMOR_AKUN_COA_BARU).
     * Kode lama ('115-13', '1507.41', '1421.00'), 'UNKNOWN' dan kosong -> false.
     */
    public function isNomorAkunValid($noAkun): bool
    {
        return isset(self::NOMOR_AKUN_COA_BARU[trim((string) $noAkun)]);
    }

    // ---------------------------------------------------------------------
    // VENEER REPAIR (sheet jurnal repair V2)
    // ---------------------------------------------------------------------

    /**
     * Akun veneer untuk sheet repair V2, ditentukan dari tipe + tebal
     * (+ status AF), BUKAN dari kode di sub_anak_akuns. Hasilnya sama di
     * semua web (kayu / wahana); jenis kayu tidak membedakan akun.
     *
     * - AF (afalan) -> akun PPC (1402.17 kering / 1402.18 jadi)
     * - selain AF   -> split face-back / core
     *
     * @param  string  $tipe  'jadi' | 'kering'
     * @param  bool  $isCore  tebal >= 1 → core, selain itu face/back
     * @param  bool  $isAf  veneer afalan → akun PPC
     * @return array{no:string,nama:string}
     */
    public function getAkunVeneerRepair(string $tipe, bool $isCore, bool $isAf = false): array
    {
        $tipe = strtolower($tipe);

        if ($isAf) {
            return match ($tipe) {
                'jadi' => self::AKUN_VENEER_JADI_PPC,
                'kering' => self::AKUN_VENEER_KERING_PPC,
                default => ['no' => 'UNKNOWN', 'nama' => 'UNKNOWN'],
            };
        }

        return match ($tipe) {
            'jadi' => $isCore ? self::AKUN_VENEER_JADI_CORE : self::AKUN_VENEER_JADI_FB,
            'kering' => $isCore ? self::AKUN_VENEER_KERING_CORE : self::AKUN_VENEER_KERING_FB,
            default => ['no' => 'UNKNOWN', 'nama' => 'UNKNOWN'],
        };
    }

    /**
     * Pengganti extractAkunVeneer() khusus sheet repair V2.
     * Akun dari COA baru; harga tetap dari referensi_harga_produksi.
     * Kalau referensi tidak ditemukan, akun tetap terisi dan harga 0
     * (penanda [UNKNOWN] di keterangan diatur di sheet).
     *
     * @return array{0:string,1:string,2:float} [nama, no, harga]
     */
    public function extractAkunVeneerRepair($ref, string $tipe, float $tebal, bool $isAf = false): array
    {
        $akun = $this->getAkunVeneerRepair($tipe, $tebal >= 1, $isAf);

        return [$akun['nama'], $akun['no'], (float) ($ref?->harga ?? 0)];
    }

    // ---------------------------------------------------------------------
    // EXTRACT AKUN DARI REFERENSI
    // ---------------------------------------------------------------------

    public function extractAkunVeneer($ref): array
    {
        return $this->extractAkunLama($ref);
    }

    /**
     * Akun platform untuk jurnal Hot Press, ditentukan dari JENIS barang
     * (bukan dari kode sub_anak_akuns):
     * - hasil produksi HP -> Platform Jadi   (1403.3)
     * - bahan masuk HP    -> Platform Mentah (1403.1)
     *
     * Kalau referensi tidak ditemukan -> UNKNOWN (tetap memblokir pengiriman
     * jurnal, supaya master data yang kurang ketahuan).
     *
     * @return array{0:string,1:string,2:float} [nama, no, harga]
     */
    public function extractAkunPlatform($ref, bool $isHasil = false): array
    {
        if (! $ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }

        $akun = $isHasil ? self::AKUN_PLATFORM_JADI : self::AKUN_PLATFORM_MENTAH;

        return [$akun['nama'], $akun['no'], (float) $ref->harga];
    }

    /**
     * Akun triplek hasil produksi Hot Press -> Triplek Mentah (1403.2) untuk
     * semua ukuran/grade. Referensi tidak ketemu -> UNKNOWN.
     *
     * @return array{0:string,1:string,2:float} [nama, no, harga]
     */
    public function extractAkunTriplek($ref): array
    {
        if (! $ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }

        $akun = self::AKUN_TRIPLEK_MENTAH;

        return [$akun['nama'], $akun['no'], (float) $ref->harga];
    }

    /**
     * Akun bahan penolong (lem, hardner, staples, pewarna, tepung, solasi, reeling tape).
     *
     * Urutan:
     * 1. Ambil dari sub_anak_akuns + MAP_AKUN_LAMA (extractAkunLama).
     * 2. Kalau hasilnya BUKAN akun spesifik (masih 1402.0 induk, UNKNOWN,
     *    atau kode lama yang belum terpetakan), tentukan akun dari NAMA bahan
     *    (+ nama sub akun) lewat akunPenolongDariNama().
     * 3. Kalau nama juga tidak dikenal, kembalikan hasil langkah 1.
     *
     * @param  mixed  $ref  baris referensi_harga_produksi
     * @param  string|null  $namaBahan  nama bahan dari sheet, mis. "Lem pai", "Tepung"
     * @return array{0:string,1:string,2:float} [nama, no, harga]
     */
    public function extractAkunPenolong($ref, $namaBahan = null): array
    {
        [$nama, $no, $harga] = $this->extractAkunLama($ref);

        if ($this->perluDeteksiAkunPenolong($no)) {
            $namaSub = $ref?->subAnakAkun?->nama_sub_anak_akun ?? '';
            $akun = $this->akunPenolongDariNama(trim((string) $namaBahan.' '.$namaSub));

            if ($akun) {
                return [$akun['nama'], $akun['no'], $harga];
            }
        }

        return [$nama, $no, $harga];
    }

    /**
     * True kalau nomor akun hasil map belum spesifik untuk bahan penolong:
     * akun induk 1402.0, UNKNOWN/kosong, atau tidak terdaftar di COA baru.
     */
    private function perluDeteksiAkunPenolong(?string $no): bool
    {
        $no = trim((string) $no);

        return $no === '' || $no === 'UNKNOWN' || $no === '1402.0' || ! $this->isNomorAkunValid($no);
    }

    /**
     * Tentukan akun bahan penolong dari teks nama bahan.
     *
     * @return array{no:string,nama:string}|null
     */
    private function akunPenolongDariNama(string $teks): ?array
    {
        $n = strtolower($teks);

        if ($n === '') {
            return null;
        }

        return match (true) {
            preg_match('/\blem\b/', $n) === 1 => self::AKUN_LEM,
            str_contains($n, 'hardner'), str_contains($n, 'hadner') => self::AKUN_HARDNER,
            str_contains($n, 'staples'), str_contains($n, 'staple') => self::AKUN_ISI_STAPLES,
            str_contains($n, 'pewarna') => self::AKUN_PEWARNA,
            str_contains($n, 'tepung') => self::AKUN_TEPUNG,
            str_contains($n, 'reeling') => self::AKUN_REELING_TAPE,
            str_contains($n, 'solasi'), str_contains($n, 'isolasi') => str_contains($n, 'putih')
                ? self::AKUN_SOLASI_PUTIH
                : self::AKUN_SOLASI_COKLAT,
            default => null,
        };
    }

    private function extractAkunLama($ref): array
    {
        if (! $ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }

        if (! $ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }

        $sub = $ref->subAnakAkun;
        $harga = (float) $ref->harga;

        if (! $sub) {
            return ['UNKNOWN', 'UNKNOWN', $harga];
        }

        $namaLama = trim($sub->nama_sub_anak_akun ?? '') ?: 'UNKNOWN';
        $noLama = trim($sub->kode_sub_anak_akun ?? '') ?: 'UNKNOWN';

        $mapped = $this->mapAkunLama($noLama, $namaLama);

        return [$mapped['nama'], $mapped['no'], $harga];
    }

    // ---------------------------------------------------------------------
    // HELPER INTERNAL
    // ---------------------------------------------------------------------

    private function is130($panjang): bool
    {
        if ($panjang === null || $panjang === '') {
            return false;
        }

        $val = (float) preg_replace('/[^0-9.]/', '', (string) $panjang);

        return $val > 0 && $val <= 130;
    }

    private function isLogcore(?string $jenisKayu): bool
    {
        $n = strtolower(trim((string) $jenisKayu));

        return $n !== '' && (
            str_contains($n, 'logcore')
            || str_contains($n, 'log core')
            || str_contains($n, 'sambungan')
        );
    }
}