<?php

namespace App\Services;

use App\Models\HppVeneerJadiLog;
use App\Models\ProduksiHp;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerJadi;
use App\Models\Ukuran;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StokVeneerJadiService
{
    /**
     * Bangun keterangan informatif:
     * "Dari Kedi (Palet 20) ke Repair - 01/07/2026"
     */
    protected function buatKeterangan(SerahTerimaVeneerKering $serahTerima, $sumber): string
    {
        $labelSumber = $serahTerima->label_sumber; // "Press Dryer" | "Kedi"
        $noPalet = $sumber->no_palet ?? '-';

        $produksiRepair = $serahTerima->produksiRepair;
        $tanggalRepair = $produksiRepair?->tanggal
            ? Carbon::parse($produksiRepair->tanggal)->format('d/m/Y')
            : now()->format('d/m/Y');

        return "Dari {$labelSumber} (Palet {$noPalet}) ke Repair - {$tanggalRepair}";
    }

    /**
     * Bangun keterangan untuk sumber 'gudang_jadi' (Veneer Keluar dari
     * Gudang Veneer Jadi menuju Repair):
     * "Keluar Gudang Jadi (Palet jd-2) - Tujuan: Repair - Penyerah: Admin -
     *  Penerima: Budi - 01/07/2026"
     */
    protected function buatKeteranganGudangJadi(SerahTerimaVeneerKering $serahTerima, VeneerJadiMutasiKeluarPalet $palet): string
    {
        $mutasi = $palet->mutasiKeluar;

        $produksiRepair = $serahTerima->produksiRepair;
        $tanggalRepair = $produksiRepair?->tanggal
            ? Carbon::parse($produksiRepair->tanggal)->format('d/m/Y')
            : now()->format('d/m/Y');

        $tujuan = $mutasi?->tujuan ?? 'Repair';
        $dikeluarkanOleh = $mutasi?->operator?->name ?? 'Tidak diketahui';

        // diterima_oleh tersimpan format "nama - Produksi REPAIR", ambil
        // nama bersihnya saja untuk keterangan yang lebih ringkas.
        $diterimaOlehRaw = $serahTerima->diterima_oleh ?: 'Tidak diketahui';
        $diterimaOleh = trim(explode(' - ', $diterimaOlehRaw)[0]);

        return "Keluar Gudang Jadi (Palet jd-{$palet->nomor_palet}) - Tujuan: {$tujuan} - "
            ."Penyerah: {$dikeluarkanOleh} - Penerima: {$diterimaOleh} - {$tanggalRepair}";
    }

    /**
     * Terima veneer kering, tapi dicatat sebagai stok Veneer Jadi
     * (dimensi/kw diambil otomatis dari sumber).
     */
    public function terimaRepair(SerahTerimaVeneerKering $serahTerima): StokVeneerJadi
    {
        $sumber = $serahTerima->sumber; // DetailHasil | DetailBongkarKedi

        if (! $sumber) {
            throw new \RuntimeException('Sumber data serah terima tidak ditemukan.');
        }

        $idJenisKayu = $sumber->id_jenis_kayu;
        $kwGrade = (string) $sumber->kw;
        $qty = (float) ($sumber->isi ?? $sumber->jumlah ?? 0);

        $ukuran = $sumber->ukuran ?? Ukuran::find($sumber->id_ukuran);
        $panjang = $ukuran->panjang ?? 0;
        $lebar = $ukuran->lebar ?? 0;
        $tebal = $ukuran->tebal ?? 0;

        // TODO: sesuaikan satuan panjang/lebar/tebal dengan rumus m3 yang benar
        $m3PerLembar = ((float) $panjang) * ((float) $lebar) * ((float) $tebal);
        $kubikasi = $qty * $m3PerLembar / 10000000;

        $keterangan = $this->buatKeterangan($serahTerima, $sumber);

        return DB::transaction(function () use (
            $serahTerima, $idJenisKayu, $panjang, $lebar, $tebal, $kwGrade, $qty, $kubikasi, $keterangan
        ) {
            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->lockForUpdate()
                ->first();

            $stokLembarBefore = $stok->stok_lembar ?? 0;
            $stokKubikasiBefore = $stok->stok_kubikasi ?? 0.0;
            $nilaiStokBefore = $stok->nilai_stok ?? 0.0;
            $hppAverage = $stok->hpp_average ?? 0.0; // TODO: hitung ulang HPP average sesuai rumus resmi

            // TODO: ganti dengan rumus HPP pekerja & bahan penolong yang resmi
            $hppPekerja = 0.0;
            $hppBahanPenolong = 0.0;
            $nilaiTransaksi = $kubikasi * $hppAverage;

            $stokLembarAfter = $stokLembarBefore + $qty;
            $stokKubikasiAfter = $stokKubikasiBefore + $kubikasi;
            $nilaiStokAfter = $nilaiStokBefore + $nilaiTransaksi;

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
                'kw_grade' => $kwGrade,
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'masuk',
                'keterangan' => $keterangan,
                'referensi_type' => SerahTerimaVeneerKering::class,
                'referensi_id' => $serahTerima->id,
                'total_lembar' => $qty,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => $hppPekerja,
                'hpp_bahan_penolong' => $hppBahanPenolong,
                'hpp_average' => $hppAverage,
                'nilai_stok' => $nilaiTransaksi,
                'stok_lembar_before' => $stokLembarBefore,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'nilai_stok_before' => $nilaiStokBefore,
                'stok_lembar_after' => $stokLembarAfter,
                'stok_kubikasi_after' => $stokKubikasiAfter,
                'nilai_stok_after' => $nilaiStokAfter,
            ]);

            return StokVeneerJadi::updateOrCreate(
                [
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'kw_grade' => $kwGrade,
                ],
                [
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverage,
                    'hpp_pekerja_last' => $hppPekerja,
                    'hpp_bahan_penolong_last' => $hppBahanPenolong,
                    'id_last_log' => $log->id,
                ]
            );
        });
    }

    /**
     * Terima veneer jadi dari SerahTerimaVeneerKering (tipe_sumber = 'gudang_jadi')
     * → titik dimana stok Gudang Veneer Jadi betul-betul BERKURANG & tercatat
     * di log, konsisten dengan pola StokVeneerKeringService::terimaKeluarGudang().
     *
     * Alur: Gudang Veneer Jadi "Proses Barang Keluar" (tujuan=Repair/Joint) HANYA
     * mencatat VeneerJadiMutasiKeluar + palet-paletnya, dan membuat antrean
     * SerahTerimaVeneerKering (tipe_sumber='gudang_jadi') per palet — belum
     * menyentuh stok. Baru saat palet itu di-"Terima" di Produksi Repair/Joint,
     * method ini dipanggil dan:
     *   1. Validasi stok fisik masih cukup
     *   2. Insert baris HppVeneerJadiLog (tipe_transaksi='KELUAR')
     *   3. Kurangi StokVeneerJadi
     *   4. Tandai VeneerJadiMutasiKeluar sebagai sudah diambil Repair/Joint
     *      supaya tidak bisa diedit/diambil dua kali
     *
     * Palet yang TIDAK PERNAH diterima tidak pernah memanggil method ini,
     * jadi tidak pernah mengurangi stok/log — sesuai desain yang sama
     * dengan sisi kering.
     */
    public function terimaKeluarGudang(SerahTerimaVeneerKering $serahTerima): StokVeneerJadi
    {
        $palet = $serahTerima->mutasiKeluarPaletJadi;

        if (! $palet instanceof VeneerJadiMutasiKeluarPalet) {
            throw new \RuntimeException('Data palet mutasi keluar tidak ditemukan.');
        }

        $mutasi = $palet->mutasiKeluar;

        if (! $mutasi) {
            throw new \RuntimeException('Data mutasi keluar veneer jadi tidak ditemukan.');
        }

        if (! is_null($mutasi->id_produksi_repair) || ! is_null($mutasi->id_produksi_hp)) {
            throw new \RuntimeException('Veneer ini sudah diambil di sisi tujuan lain.');
        }

        $idJenisKayu = (int) $mutasi->id_jenis_kayu;
        $panjang = $mutasi->panjang;
        $lebar = $mutasi->lebar;
        $tebal = $mutasi->tebal;
        $kwGrade = (string) $mutasi->kw_grade;
        $qty = (float) $palet->jumlah_lembar;

        if (! $idJenisKayu) {
            throw new \RuntimeException('Data jenis kayu pada mutasi keluar tidak lengkap.');
        }

        // TODO: sesuaikan satuan panjang/lebar/tebal dengan rumus m3 yang benar
        // (sama seperti hitungKubikasi() di GudangVeneerJadi Page)
        $kubikasi = ((float) $panjang * (float) $lebar * (float) $tebal * $qty) / 10000000;

        $keterangan = $this->buatKeteranganGudangJadi($serahTerima, $palet);

        return DB::transaction(function () use (
            $serahTerima, $mutasi, $idJenisKayu, $panjang, $lebar, $tebal, $kwGrade, $qty, $kubikasi, $keterangan
        ) {
            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->lockForUpdate()
                ->first();

            if (! $stok || $qty > $stok->stok_lembar) {
                $saldo = $stok->stok_lembar ?? 0;

                throw new \RuntimeException(
                    "Stok tidak cukup. Saldo saat ini {$saldo} lbr, diminta ".(int) round($qty).' lbr.'
                );
            }

            $stokLembarBefore = $stok->stok_lembar;
            $stokKubikasiBefore = $stok->stok_kubikasi;
            $nilaiStokBefore = $stok->nilai_stok;
            $hppAverage = $stok->hpp_average ?? 0.0;

            $nilaiTransaksi = $kubikasi * $hppAverage;

            $stokLembarAfter = $stokLembarBefore - $qty;
            $stokKubikasiAfter = $stokKubikasiBefore - $kubikasi;
            $nilaiStokAfter = $nilaiStokBefore - $nilaiTransaksi;

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
                'kw_grade' => $kwGrade,
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'KELUAR',
                'keterangan' => $keterangan,
                'referensi_type' => SerahTerimaVeneerKering::class,
                'referensi_id' => $serahTerima->id,
                'total_lembar' => $qty,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $hppAverage,
                'nilai_stok' => $nilaiTransaksi,
                'stok_lembar_before' => $stokLembarBefore,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'nilai_stok_before' => $nilaiStokBefore,
                'stok_lembar_after' => $stokLembarAfter,
                'stok_kubikasi_after' => $stokKubikasiAfter,
                'nilai_stok_after' => $nilaiStokAfter,
            ]);

            $stok->update([
                'stok_lembar' => $stokLembarAfter,
                'stok_kubikasi' => $stokKubikasiAfter,
                'nilai_stok' => $nilaiStokAfter,
                'hpp_average' => $hppAverage,
                'id_last_log' => $log->id,
            ]);

            // Kunci mutasi supaya tidak bisa diedit/diambil dua kali —
            // konsisten dengan GudangVeneerJadi::mutasiKeluarBisaDiedit()
            // yang mengecek is_null(id_produksi_hp) && is_null(id_produksi_repair).
            //
            // 🆕 FIX: sebelumnya method ini SELALU menulis
            // 'id_produksi_repair' => $serahTerima->id_produksi_repair — padahal
            // untuk tujuan Joint, id_produksi_repair pada $serahTerima itu NULL
            // (yang keisi adalah id_produksi_joint), jadi mutasi tidak pernah
            // benar-benar terkunci untuk Joint. Sekarang keduanya ditangani
            // eksplisit; mutasi 'dipinjamkan' kolom id_produksi_repair-nya
            // sebagai penanda umum "sudah diambil", supaya tidak perlu ubah
            // skema tabel veneer_jadi_mutasi_keluar.
            // 🔧 FIX: jangan pernah menulis penanda kosong. Kalau NULL,
            // mutasi tidak terkunci dan palet yang sama bisa diambil ulang
            // padahal stoknya sudah berkurang (kasus mutasi 17 & 18).
            $idPenanda = $serahTerima->id_produksi_joint ?? $serahTerima->id_produksi_repair;

            if (empty($idPenanda)) {
                throw new \RuntimeException(
                    'Penanda produksi penerima kosong — mutasi keluar tidak bisa dikunci. '
                    .'Serah terima #'.$serahTerima->id.' tidak punya id_produksi_repair '
                    .'maupun id_produksi_joint.'
                );
            }

            $mutasi->update([
                'id_produksi_repair' => $idPenanda,
            ]);

            return $stok->fresh();
        });
    }

    /**
     * Terima kembali sisa veneer jadi dari Hotpress ke Gudang Veneer Jadi.
     *
     * Dipanggil saat tombol "Kembalikan ke Gudang" di tab Bahan Hot Press
     * ditekan, dengan $palet = palet veneer yang dipilih dan $jumlah =
     * berapa lembar yang dikembalikan. Method ini bertanggung jawab penuh
     * atas SATU transaksi pengembalian:
     *   1. Kunci baris palet (lockForUpdate) & validasi ulang sisa fisik
     *      (jumlah_lembar - total isi bahan_hotpress - jumlah_dikembalikan
     *      yang sudah ada) supaya tidak kembali lebih dari yang tersedia,
     *      dan aman dari race condition kalau ada 2 orang input barengan.
     *   2. Insert baris HppVeneerJadiLog (tipe_transaksi = 'MASUK')
     *   3. Tambah StokVeneerJadi (stok_lembar, stok_kubikasi, nilai_stok)
     *   4. Naikkan `jumlah_dikembalikan` pada PALET itu sendiri — bukan
     *      pada baris bahan_hotpress manapun, karena sisa yang belum
     *      dipakai adalah properti palet, bukan properti 1 baris
     *      pemakaian tertentu. Tabel Bahan Hot Press sama sekali tidak
     *      disentuh oleh method ini.
     */
    public function kembaliDariHotpress(VeneerJadiMutasiKeluarPalet $palet, float $jumlah, ?ProduksiHp $produksiHp = null): StokVeneerJadi
    {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah pengembalian harus lebih dari 0.');
        }

        return DB::transaction(function () use ($palet, $jumlah, $produksiHp) {
            $palet = VeneerJadiMutasiKeluarPalet::query()
                ->lockForUpdate()
                ->find($palet->id);

            if (! $palet) {
                throw new \RuntimeException('Palet veneer jadi tidak ditemukan.');
            }

            $mutasi = $palet->mutasiKeluar;

            if (! $mutasi) {
                throw new \RuntimeException('Data mutasi keluar veneer jadi tidak ditemukan.');
            }

            // Validasi ulang sisa DI DALAM transaksi (bukan cuma di form),
            // memakai rumus yang sama dengan VeneerJadiMutasiKeluarPalet::sisa.
            $terpakai = $palet->pemakaianHotpress()->sum('isi');
            $sisaSaatIni = (float) $palet->jumlah_lembar - (float) $terpakai - (float) $palet->jumlah_dikembalikan;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException("Jumlah melebihi sisa yang tersedia di palet ini ({$sisaSaatIni} lembar).");
            }

            $idJenisKayu = (int) $mutasi->id_jenis_kayu;
            $panjang = $mutasi->panjang;
            $lebar = $mutasi->lebar;
            $tebal = $mutasi->tebal;
            $kwGrade = (string) $mutasi->kw_grade;

            if (! $idJenisKayu) {
                throw new \RuntimeException('Data jenis kayu pada mutasi keluar tidak lengkap.');
            }

            // Rumus kubikasi disamakan dengan terimaKeluarGudang() di atas.
            $kubikasi = ((float) $panjang * (float) $lebar * (float) $tebal * $jumlah) / 10000000;

            $tanggal = $produksiHp?->tanggal
                ? Carbon::parse($produksiHp->tanggal)->format('d/m/Y')
                : now()->format('d/m/Y');

            $keterangan = "Pengembalian sisa veneer jadi dari Hotpress (Palet {$palet->nomor_palet}) - {$tanggal}";

            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->lockForUpdate()
                ->first();

            $stokLembarBefore = $stok->stok_lembar ?? 0;
            $stokKubikasiBefore = $stok->stok_kubikasi ?? 0.0;
            $nilaiStokBefore = $stok->nilai_stok ?? 0.0;
            $hppAverage = $stok->hpp_average ?? 0.0;

            $nilaiTransaksi = $kubikasi * $hppAverage;

            $stokLembarAfter = $stokLembarBefore + $jumlah;
            $stokKubikasiAfter = $stokKubikasiBefore + $kubikasi;
            $nilaiStokAfter = $nilaiStokBefore + $nilaiTransaksi;

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $panjang,
                'lebar' => $lebar,
                'tebal' => $tebal,
                'kw_grade' => $kwGrade,
                'tanggal' => now()->toDateString(),
                'tipe_transaksi' => 'MASUK',
                'keterangan' => $keterangan,
                'referensi_type' => VeneerJadiMutasiKeluarPalet::class,
                'referensi_id' => $palet->id,
                'total_lembar' => $jumlah,
                'total_kubikasi' => $kubikasi,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $hppAverage,
                'nilai_stok' => $nilaiTransaksi,
                'stok_lembar_before' => $stokLembarBefore,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'nilai_stok_before' => $nilaiStokBefore,
                'stok_lembar_after' => $stokLembarAfter,
                'stok_kubikasi_after' => $stokKubikasiAfter,
                'nilai_stok_after' => $nilaiStokAfter,
            ]);

            $stokBaru = StokVeneerJadi::updateOrCreate(
                [
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'kw_grade' => $kwGrade,
                ],
                [
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverage,
                    'id_last_log' => $log->id,
                ]
            );

            // Baris bahan_hotpress TIDAK disentuh — hanya palet yang dicatat
            // sudah menerima pengembalian sebanyak $jumlah lembar.
            $palet->update([
                'jumlah_dikembalikan' => (float) $palet->jumlah_dikembalikan + $jumlah,
            ]);

            return $stokBaru;
        });
    }
}
