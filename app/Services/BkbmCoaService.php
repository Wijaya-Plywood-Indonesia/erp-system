<?php

namespace App\Services;

class BkbmCoaService
{
    // =========================================================================
    // DAFTAR AKUN COA BARU (KHUSUS BK & BM - TANPA AKUN .0)
    // =========================================================================

    // Kas & Setara Kas
    public const AKUN_KAS_UTAMA = ['no' => '1101.1', 'nama' => 'Kas Utama'];
    public const AKUN_KAS_2 = ['no' => '1101.2', 'nama' => 'Kas 2'];

    // Persediaan Bahan Baku (1402)
    public const AKUN_KAYU_130 = ['no' => '1402.1', 'nama' => 'Persediaan kayu 130'];
    public const AKUN_KAYU_260 = ['no' => '1402.2', 'nama' => 'Persediaan kayu 260'];

    public const AKUN_VENEER_BASAH_FB = ['no' => '1402.3', 'nama' => 'Persediaan Veneer Basah Face Back'];
    public const AKUN_VENEER_BASAH_CORE = ['no' => '1402.4', 'nama' => 'Persediaan Veneer Basah Core'];
    public const AKUN_VENEER_BASAH_PPC = ['no' => '1402.16', 'nama' => 'Persediaan Veneer Basah PPC'];

    public const AKUN_VENEER_KERING_FB = ['no' => '1402.5', 'nama' => 'Persediaan Veneer Kering Face Back'];
    public const AKUN_VENEER_KERING_CORE = ['no' => '1402.6', 'nama' => 'Persediaan Veneer Kering Core'];
    public const AKUN_VENEER_KERING_PPC = ['no' => '1402.17', 'nama' => 'Persediaan Veneer Kering PPC'];

    public const AKUN_VENEER_JADI_FB = ['no' => '1402.7', 'nama' => 'Persediaan Veneer Jadi Face Back'];
    public const AKUN_VENEER_JADI_CORE = ['no' => '1402.8', 'nama' => 'Persediaan Veneer Jadi Core'];
    public const AKUN_VENEER_JADI_PPC = ['no' => '1402.18', 'nama' => 'Persediaan Veneer Jadi PPC'];

    // Bahan Penolong Gudang
    public const AKUN_LEM = ['no' => '1402.9', 'nama' => 'Persediaan Lem'];
    public const AKUN_HARDNER = ['no' => '1402.10', 'nama' => 'Persediaan Hardner'];
    public const AKUN_ISI_STAPLES = ['no' => '1402.11', 'nama' => 'Persediaan Isi Staples'];
    public const AKUN_PEWARNA = ['no' => '1402.12', 'nama' => 'Persediaan pewarna'];
    public const AKUN_TEPUNG = ['no' => '1402.13', 'nama' => 'Persediaan Tepung'];
    public const AKUN_SOLASI_COKLAT = ['no' => '1402.14', 'nama' => 'Persediaan Solasi Coklat'];
    public const AKUN_SOLASI_PUTIH = ['no' => '1402.15', 'nama' => 'Persediaan Solasi Putih'];
    public const AKUN_REELING_TAPE = ['no' => '1402.23', 'nama' => 'Persediaan Reeling Tape'];

    public const AKUN_LOGCORE_130 = ['no' => '1402.21', 'nama' => 'Persediaan Logcore 130'];
    public const AKUN_LOGCORE_260 = ['no' => '1402.22', 'nama' => 'Persediaan Logcore 260'];

    // Persediaan Barang Dalam Proses (1403)
    public const AKUN_PLATFORM_MENTAH = ['no' => '1403.1', 'nama' => 'Persediaan Platform Mentah'];
    public const AKUN_TRIPLEK_MENTAH = ['no' => '1403.2', 'nama' => 'Persediaan Triplek Mentah'];
    public const AKUN_PLATFORM_JADI = ['no' => '1403.3', 'nama' => 'Persediaan Platform Jadi'];
    public const AKUN_TRIPLEK_JADI_PROSES = ['no' => '1403.4', 'nama' => 'Persediaan Triplek Jadi'];

    // Persediaan Barang Jadi (1404)
    public const AKUN_TRIPLEK_GUDANG_1 = ['no' => '1404.1', 'nama' => 'Persediaan Triplek Gudang 1'];
    public const AKUN_TRIPLEK_SIAP_JUAL = ['no' => '1404.2', 'nama' => 'Persediaan Triplek Siap Jual'];

    // Perlengkapan & Aset Lancar Lainnya
    public const AKUN_PERLENGKAPAN_PABRIK = ['no' => '1499.2', 'nama' => 'Perlengkapan Pabrik'];
    public const AKUN_UANG_MUKA_PEMBELIAN = ['no' => '1499.1', 'nama' => 'Uang Muka Pembelian'];

    // Hutang
    public const AKUN_HUTANG_OWNER = ['no' => '2103.1', 'nama' => 'Hutang owner'];
    public const AKUN_HUTANG_KAS = ['no' => '2103.2', 'nama' => 'Hutang Kas'];
    public const AKUN_HUTANG_GAJI = ['no' => '2195.1', 'nama' => 'Hutang Gaji'];
    public const AKUN_HUTANG_ONGKOS_TURUN_KAYU = ['no' => '2195.2', 'nama' => 'Hutang ongkos turun kayu'];

    // Biaya & Beban (5000)
    public const AKUN_BIAYA_OPERASIONAL_PABRIK = ['no' => '5069.3', 'nama' => 'Biaya Operasional Pabrik'];
    public const AKUN_SELISIH_HARGA_PATOK = ['no' => '5069.2', 'nama' => 'Selisih harga patok produksi'];
    public const AKUN_HPP = ['no' => '5069.1', 'nama' => 'Harga Pokok Penjualan'];

    /**
     * Tentukan akun Veneer berdasarkan tipe veneer (basah, kering, jadi),
     * kw (af/aff = ppc), dan ketebalan (< 1 = face back, >= 1 = core).
     */
    public function getAkunVeneer(string $tipe, ?string $kw, float $tebal): array
    {
        $tipe = strtolower(trim($tipe));
        $kw = strtolower(trim((string) $kw));
        $isPpc = in_array($kw, ['af', 'aff', 'ppc'], true);
        $isFaceBack = ($tebal > 0 && $tebal < 1.0);

        if ($tipe === 'basah') {
            if ($isPpc) return self::AKUN_VENEER_BASAH_PPC;
            return $isFaceBack ? self::AKUN_VENEER_BASAH_FB : self::AKUN_VENEER_BASAH_CORE;
        }

        if ($tipe === 'kering') {
            if ($isPpc) return self::AKUN_VENEER_KERING_PPC;
            return $isFaceBack ? self::AKUN_VENEER_KERING_FB : self::AKUN_VENEER_KERING_CORE;
        }

        // Default 'jadi'
        if ($isPpc) return self::AKUN_VENEER_JADI_PPC;
        return $isFaceBack ? self::AKUN_VENEER_JADI_FB : self::AKUN_VENEER_JADI_CORE;
    }

    /**
     * Tentukan akun untuk item non-veneer (barang penolong, triplek mentah, kayu bakar, logcore, karung, dll).
     */
    public function getAkunBarang(string $namaBarang, string $keterangan = '', ?float $panjang = null): ?array
    {
        $teks = strtolower(trim($namaBarang . ' ' . $keterangan));

        // Kayu Bakar / Sampah
        if (str_contains($teks, 'kayu bakar') || str_contains($teks, 'sampah')) {
            return self::AKUN_BIAYA_OPERASIONAL_PABRIK;
        }

        // Logcore
        if (str_contains($teks, 'logcore') || str_contains($teks, 'log core')) {
            $is130 = ($panjang !== null && $panjang <= 130) || str_contains($teks, '130');
            return $is130 ? self::AKUN_LOGCORE_130 : self::AKUN_LOGCORE_260;
        }

        // Kayu Bulat
        if (str_contains($teks, 'kayu bulat') || str_contains($teks, 'log')) {
            $is130 = ($panjang !== null && $panjang <= 130) || str_contains($teks, '130');
            return $is130 ? self::AKUN_KAYU_130 : self::AKUN_KAYU_260;
        }

        // Platform & Triplek Mentah
        if (str_contains($teks, 'platform mentah')) {
            return self::AKUN_PLATFORM_MENTAH;
        }
        if (str_contains($teks, 'triplek mentah') || str_contains($teks, 'mth') || str_contains($teks, 'lp mth')) {
            return self::AKUN_TRIPLEK_MENTAH;
        }
        if (str_contains($teks, 'platform')) {
            return self::AKUN_PLATFORM_JADI;
        }
        if (str_contains($teks, 'triplek') || str_contains($teks, 'plywood')) {
            return self::AKUN_TRIPLEK_SIAP_JUAL;
        }

        // Bahan Penolong Spesifik
        if (preg_match('/\blem\b/', $teks)) return self::AKUN_LEM;
        if (str_contains($teks, 'hardner') || str_contains($teks, 'hadner')) return self::AKUN_HARDNER;
        if (str_contains($teks, 'staples') || str_contains($teks, 'staple') || str_contains($teks, 'straples')) return self::AKUN_ISI_STAPLES;
        if (str_contains($teks, 'pewarna')) return self::AKUN_PEWARNA;
        if (str_contains($teks, 'tepung')) return self::AKUN_TEPUNG;
        if (str_contains($teks, 'reeling')) return self::AKUN_REELING_TAPE;
        if (str_contains($teks, 'solasi') || str_contains($teks, 'isolasi')) {
            return str_contains($teks, 'putih') ? self::AKUN_SOLASI_PUTIH : self::AKUN_SOLASI_COKLAT;
        }

        // Deteksi Veneer dari nama barang jika masuk sebagai barang manual
        if (str_contains($teks, 'vener') || str_contains($teks, 'veneer') || (str_contains($teks, 'af') && str_contains($teks, 'kering'))) {
            $isPpc = str_contains($teks, 'af') || str_contains($teks, 'aff') || str_contains($teks, 'ppc');
            $tipe = 'jadi';
            if (str_contains($teks, 'basah')) $tipe = 'basah';
            elseif (str_contains($teks, 'kering')) $tipe = 'kering';

            // Coba ambil tebal dari format ukuran misal 130x65x3.7 atau 70×55×0,5
            $tebal = 0.0;
            if (preg_match('/(?:x|×)\s*([0-9]+(?:[\.,][0-9]+)?)/i', $teks, $m)) {
                $parts = preg_split('/(?:x|×)/i', $teks);
                if (count($parts) >= 3) {
                    preg_match('/[0-9]+(?:[\.,][0-9]+)?/', $parts[2], $tm);
                    if (!empty($tm[0])) {
                        $tebal = (float) str_replace(',', '.', $tm[0]);
                    }
                }
            }
            return $this->getAkunVeneer($tipe, $isPpc ? 'af' : null, $tebal);
        }

        // Karung / Sak Serbuk / Perlengkapan Pabrik / Sparepart
        if (
            str_contains($teks, 'karung') ||
            str_contains($teks, 'sak') ||
            str_contains($teks, 'batu asah') ||
            str_contains($teks, 'fitting') ||
            str_contains($teks, 'relay') ||
            str_contains($teks, 'inverter') ||
            str_contains($teks, 'valve') ||
            str_contains($teks, 'v-belt') ||
            str_contains($teks, 'kawat las') ||
            str_contains($teks, 'perlengkapan')
        ) {
            return self::AKUN_PERLENGKAPAN_PABRIK;
        }

        return null;
    }
}
