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
     * Keterangan untuk sumber 'gudang_jadi' (Veneer Keluar dari Gudang Veneer Jadi menuju Repair).
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

        // diterima_oleh tersimpan format "nama - Produksi REPAIR", ambil nama bersihnya saja.
        $diterimaOlehRaw = $serahTerima->diterima_oleh ?: 'Tidak diketahui';
        $diterimaOleh = trim(explode(' - ', $diterimaOlehRaw)[0]);

        return "Keluar Gudang Jadi (Palet jd-{$palet->nomor_palet}) - Tujuan: {$tujuan} - "
            ."Penyerah: {$dikeluarkanOleh} - Penerima: {$diterimaOleh} - {$tanggalRepair}";
    }

    /**
     * Terima veneer kering, tapi dicatat sebagai stok Veneer Jadi (jalur "kering langsung").
     * HPP = HPP veneer kering batch itu sendiri (Press Dryer / Kedi tidak dirata-rata),
     * lewat HppVeneerJadiService::komponenInput().
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

        $hpp = app(HppVeneerJadiService::class);

        return $hpp->catatMasuk(
            idJenisKayu: (int) $idJenisKayu,
            panjang: (float) ($ukuran->panjang ?? 0),
            lebar: (float) ($ukuran->lebar ?? 0),
            tebal: (float) ($ukuran->tebal ?? 0),
            kw: $kwGrade,
            lembar: $qty,
            komponen: $hpp->komponenInput($serahTerima),
            jalur: 'kering_langsung',
            keterangan: $this->buatKeterangan($serahTerima, $sumber),
            referensi: $serahTerima,
        );
    }

    /**
     * Terima veneer jadi dari SerahTerimaVeneerKering (tipe_sumber = 'gudang_jadi')
     * → titik dimana stok Gudang Veneer Jadi betul-betul BERKURANG & tercatat di log.
     * Veneer keluar memakai HPP Average stok saat itu; rincian rata-rata stok tidak berubah.
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

            // Kunci mutasi supaya tidak bisa diedit/diambil dua kali.
            // Jangan pernah menulis penanda kosong (kasus mutasi 17 & 18).
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
     * Masuk dengan HPP Average stok saat itu (rincian K/P/M/B, lahan, dan mesin stok ikut terbawa),
     * jadi rata-rata stok tidak berubah.
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

            // Validasi ulang sisa DI DALAM transaksi.
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

            $tanggal = $produksiHp?->tanggal
                ? Carbon::parse($produksiHp->tanggal)->format('d/m/Y')
                : now()->format('d/m/Y');

            $keterangan = "Pengembalian sisa veneer jadi dari Hotpress (Palet {$palet->nomor_palet}) - {$tanggal}";

            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->where('lebar', $lebar)
                ->where('tebal', $tebal)
                ->where('kw_grade', $kwGrade)
                ->first();

            $hpp = app(HppVeneerJadiService::class);

            $stokBaru = $hpp->catatMasuk(
                idJenisKayu: $idJenisKayu,
                panjang: (float) $panjang,
                lebar: (float) $lebar,
                tebal: (float) $tebal,
                kw: $kwGrade,
                lembar: $jumlah,
                komponen: $hpp->komposisiJadi($stok), // HPP Average stok saat ini
                jalur: 'gudang_jadi',
                keterangan: $keterangan,
                referensi: $palet,
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