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
    public const AKUN_KAYU_130 = ['no' => '1402.1', 'nama' => 'Persediaan Kayu 130'];

    public const AKUN_KAYU_260 = ['no' => '1402.2', 'nama' => 'Persediaan Kayu 260'];

    /** Persediaan logcore / kayu sambungan */
    public const AKUN_LOGCORE_130 = ['no' => '1402.21', 'nama' => 'Persediaan Logcore 130'];

    public const AKUN_LOGCORE_260 = ['no' => '1402.22', 'nama' => 'Persediaan Logcore 260'];

    /** Veneer basah (sengon & meranti digabung) */
    public const AKUN_VENEER_BASAH_FB = ['no' => '1402.3', 'nama' => 'Persediaan Veneer Basah Face Back'];

    public const AKUN_VENEER_BASAH_CORE = ['no' => '1402.4', 'nama' => 'Persediaan Veneer Basah Core'];

    /** Veneer kering (BARU – sebelumnya belum ada di alias) */
    public const AKUN_VENEER_KERING_FB = ['no' => '1402.5', 'nama' => 'Persediaan Veneer Kering Face Back'];

    public const AKUN_VENEER_KERING_CORE = ['no' => '1402.6', 'nama' => 'Persediaan Veneer Kering Core'];

    /** Barang setengah jadi / jadi (BARU) */
    public const AKUN_BARANG_SETENGAH_JADI = ['no' => '1402.7', 'nama' => 'Persediaan Barang Dalam Proses'];

    public const AKUN_BARANG_JADI = ['no' => '1402.8', 'nama' => 'Persediaan Barang Jadi'];

    /** Gaji */
    public const AKUN_BEBAN_GAJI_HARIAN = ['no' => '5061.1', 'nama' => 'Beban Gaji Harian Produksi'];

    public const AKUN_BEBAN_GAJI_BORONGAN = ['no' => '5061.2', 'nama' => 'Beban Gaji Borongan Produksi'];

    public const AKUN_HUTANG_GAJI = ['no' => '2195.1', 'nama' => 'Hutang Gaji'];

    public const AKUN_HUTANG_GAJI_BORONGAN = ['no' => '2195.2', 'nama' => 'Hutang Gaji Borongan'];

    /** Selisih harga patok produksi (akun DE -> selalu di sisi debit) */
    public const AKUN_SELISIH_HPP = ['no' => '5069.2', 'nama' => 'Selisih harga patok produksi'];

    /** Beban ongkos mesin / produksi (BARU) */
    public const AKUN_BEBAN_PRODUKSI = ['no' => '5069.1', 'nama' => 'Beban Produksi'];

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

        // veneer kering
        '115-09' => self::AKUN_VENEER_KERING_FB,
        '1431.00' => self::AKUN_VENEER_KERING_FB,
        '115-10' => self::AKUN_VENEER_KERING_CORE,
        '1436.00' => self::AKUN_VENEER_KERING_CORE,

        // gaji
        '210-02' => self::AKUN_HUTANG_GAJI,
        '2231.00' => self::AKUN_HUTANG_GAJI,

        // hpp / selisih
        '510-01' => self::AKUN_SELISIH_HPP,
        '5101.00' => self::AKUN_SELISIH_HPP,
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

    // ---------------------------------------------------------------------
    // VENEER
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
            'hutang' => $isBorongan ? self::AKUN_HUTANG_GAJI_BORONGAN : self::AKUN_HUTANG_GAJI,
        ];
    }

    /** @return string[] semua nomor akun hutang gaji */
    public function nomorAkunHutangGaji(): array
    {
        return [self::AKUN_HUTANG_GAJI['no'], self::AKUN_HUTANG_GAJI_BORONGAN['no']];
    }

    // ---------------------------------------------------------------------
    // HPP / SELISIH / BEBAN
    // ---------------------------------------------------------------------

    /** @return array{no:string,nama:string} */
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
