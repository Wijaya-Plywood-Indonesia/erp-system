<?php

namespace App\Services;

use App\Models\GudangVeneerKering;
use App\Models\StokVeneerKering;
use App\Models\User;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use Illuminate\Support\Facades\DB;

class SerahTerimaVeneerKeringService
{
    /**
     * Pola nilai `tipe_veneer` yang dianggap "veneer kering".
     * Pakai LIKE agar aman terhadap variasi penyimpanan
     * ('veneer_kering', 'veneer kering', 'Veneer Kering', dst).
     * Ubah di sini kalau konvensi datamu berbeda.
     */
    public const TIPE_KERING_LIKE = '%kering%';

    /**
     * Terima seluruh baris VENEER KERING dari sebuah VeneerMutasi ke
     * gudang_veneer_kering (jenis_transaksi = 'masuk').
     *
     * Idempotent: detail yang sudah punya baris ledger dilewati, jadi aman
     * kalau tombol tak sengaja ditekan dua kali / proses terputus di tengah.
     */
    public function terima(VeneerMutasi $mutasi, int $userId): void
    {
        DB::transaction(function () use ($mutasi, $userId) {
            $details = $mutasi->details()
                ->where('tipe_veneer', 'like', self::TIPE_KERING_LIKE)
                ->get();

            $namaPenerima = User::find($userId)?->name ?? 'Tidak diketahui';

            foreach ($details as $detail) {
                $sudahAda = GudangVeneerKering::query()
                    ->where('id_veneer_mutasi_detail', $detail->id)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $this->postMasuk($mutasi, $detail, $userId, $namaPenerima);
            }
        });
    }

    /**
     * Catat satu baris transaksi MASUK ke ledger gudang_veneer_kering
     * lengkap dengan snapshot moving-average.
     */
    protected function postMasuk(VeneerMutasi $mutasi, VeneerMutasiDetail $detail, int $userId, string $namaPenerima): void
    {
        $idUkuran    = (int) $detail->id_ukuran;
        $idJenisKayu = (int) $detail->id_jenis_kayu;
        $kw          = (string) $detail->kw;

        // Snapshot m3 & nilai sebelum transaksi ini.
        // Karena berjalan dalam 1 transaksi DB, baris yang baru saja
        // di-insert untuk produk yang sama sudah ikut terbaca -> rantai
        // moving average tetap benar walau ada beberapa baris sekaligus.
        $snapshot          = GudangVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
        $stokLembarSebelum = GudangVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);

        $qty = (float) $detail->qty;
        $m3  = (float) $detail->m3;

        // ── HPP ────────────────────────────────────────────────────────────
        // VeneerMutasiDetail tidak membawa data biaya, jadi di sini HPP
        // masuk memakai rata-rata terakhir (kalau ada) supaya average tidak
        // terdistorsi. GANTI bagian ini bila kamu punya sumber HPP nyata
        // (mis. hpp_veneer_basah_per_m3 + ongkos_dryer_per_m3).
        $hppKeringPerM3 = $snapshot['hpp_average'] > 0 ? $snapshot['hpp_average'] : 0.0;
        $nilaiTransaksi = $hppKeringPerM3 * $m3;

        $stokM3Sesudah    = $snapshot['stok_m3'] + $m3;
        $nilaiStokSesudah = $snapshot['nilai_stok'] + $nilaiTransaksi;
        $hppAverage       = $stokM3Sesudah > 0 ? ($nilaiStokSesudah / $stokM3Sesudah) : 0.0;

        // Keterangan final: No Nota + siapa yang menerima + keterangan asli (kalau ada).
        $bagian = [
            'No Nota: ' . ($mutasi->no_nota ?: '-'),
            'Diterima oleh: ' . $namaPenerima,
        ];

        if (trim((string) $mutasi->keterangan) !== '') {
            $bagian[] = 'Keterangan: ' . trim((string) $mutasi->keterangan);
        }

        $keteranganFinal = implode(' · ', $bagian);

        GudangVeneerKering::create([
            'id_ukuran'               => $idUkuran,
            'id_jenis_kayu'           => $idJenisKayu,
            'kw'                      => $kw,
            'jenis_transaksi'         => 'masuk',
            'tanggal_transaksi'       => optional($mutasi->tanggal)->toDateString() ?? now()->toDateString(),
            'qty'                     => $qty,
            'm3'                      => $m3,
            'stok_lembar_sebelum'     => $stokLembarSebelum,
            'stok_lembar_sesudah'     => $stokLembarSebelum + (int) round($qty),
            'hpp_veneer_basah_per_m3' => 0,
            'ongkos_dryer_per_m3'     => 0,
            'hpp_kering_per_m3'       => $hppKeringPerM3,
            'nilai_transaksi'         => $nilaiTransaksi,
            'stok_m3_sebelum'         => $snapshot['stok_m3'],
            'nilai_stok_sebelum'      => $snapshot['nilai_stok'],
            'stok_m3_sesudah'         => $stokM3Sesudah,
            'nilai_stok_sesudah'      => $nilaiStokSesudah,
            'hpp_average'             => $hppAverage,
            'keterangan'              => $keteranganFinal,             // dari VM + siapa yang terima
            'diterima_oleh'           => $userId,                      // user login
            'id_veneer_mutasi_detail' => $detail->id,
        ]);

        // ── 2) TULIS JUGA KE STOK RESMI (stok_veneer_kerings) ────────────────
        // Ini tabel yang dibaca halaman "Stok Veneer Kering" dan dipakai
        // Opname — jadi transaksi Terima WAJIB masuk ke sini juga, bukan cuma
        // ke ledger gudang_veneer_kering di atas. Rantai moving-average-nya
        // dihitung terpisah karena ini tabel yang berbeda.
        $snapshotStok          = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
        $stokLembarSebelumStok = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);

        $hppKeringStok    = $snapshotStok['hpp_average'] > 0 ? $snapshotStok['hpp_average'] : 0.0;
        $nilaiTransaksiStok = $hppKeringStok * $m3;

        $stokM3SesudahStok    = $snapshotStok['stok_m3'] + $m3;
        $nilaiStokSesudahStok = $snapshotStok['nilai_stok'] + $nilaiTransaksiStok;
        $hppAverageStok       = $stokM3SesudahStok > 0 ? ($nilaiStokSesudahStok / $stokM3SesudahStok) : 0.0;

        StokVeneerKering::create([
            'id_ukuran'               => $idUkuran,
            'id_jenis_kayu'           => $idJenisKayu,
            'kw'                      => $kw,
            'jenis_transaksi'         => 'masuk',
            'tanggal_transaksi'       => optional($mutasi->tanggal)->toDateString() ?? now()->toDateString(),
            'qty'                     => $qty,
            'm3'                      => $m3,
            'stok_lembar_sebelum'     => $stokLembarSebelumStok,
            'stok_lembar_sesudah'     => $stokLembarSebelumStok + (int) round($qty),
            'hpp_veneer_basah_per_m3' => 0,
            'ongkos_dryer_per_m3'     => 0,
            'hpp_kering_per_m3'       => $hppKeringStok,
            'nilai_transaksi'         => $nilaiTransaksiStok,
            'stok_m3_sebelum'         => $snapshotStok['stok_m3'],
            'nilai_stok_sebelum'      => $snapshotStok['nilai_stok'],
            'stok_m3_sesudah'         => $stokM3SesudahStok,
            'nilai_stok_sesudah'      => $nilaiStokSesudahStok,
            'hpp_average'             => $hppAverageStok,
            'keterangan'              => $keteranganFinal,
            'id_veneer_mutasi'        => $mutasi->id,
            'id_veneer_mutasi_detail' => $detail->id,
        ]);
    }
}