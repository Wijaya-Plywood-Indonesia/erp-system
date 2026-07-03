<?php

namespace App\Services;

use App\Models\DetailNotaBarangMasuk;
use App\Models\GudangVeneerKering;
use App\Models\User;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use Illuminate\Support\Collection;
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
                ->with('jenisKayu')
                ->get();

            $namaPenerima = User::find($userId)?->name ?? 'Tidak diketahui';

            // Sumber keterangan per baris: detail_nota_barang_masuk milik nota ini.
            $notaDetails = DetailNotaBarangMasuk::query()
                ->where('id_nota_bm', $mutasi->id_nota_bm)
                ->get();

            foreach ($details as $detail) {
                $sudahAda = GudangVeneerKering::query()
                    ->where('id_veneer_mutasi_detail', $detail->id)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $ketDetail = $this->cariKeteranganDetail($detail, $notaDetails);

                $this->postMasuk($mutasi, $detail, $userId, $namaPenerima, $ketDetail);
            }
        });
    }

    /**
     * Cari keterangan detail nota BM yang cocok untuk satu baris veneer kering:
     * qty sama + nama_barang memuat jenis kayu + memuat KW.
     */
    protected function cariKeteranganDetail(VeneerMutasiDetail $detail, Collection $notaDetails): ?string
    {
        $qty  = (int) round((float) $detail->qty);
        $kw   = strtolower(trim((string) $detail->kw));
        $kayu = strtolower(trim((string) ($detail->jenisKayu?->nama_kayu ?? '')));

        $match = $notaDetails->first(function ($nd) use ($qty, $kw, $kayu) {
            $nama    = strtolower((string) ($nd->nama_barang ?? ''));
            $namaTNS = str_replace(' ', '', $nama); // tanpa spasi utk "kw 1" / "kw1"
            $jml     = (int) round((float) ($nd->jumlah ?? 0));

            $cocokQty  = $qty > 0 && $jml === $qty;
            $cocokKayu = $kayu !== '' && str_contains($nama, $kayu);
            $cocokKw   = $kw !== '' && (
                str_contains($nama, 'kw ' . $kw) ||
                str_contains($namaTNS, 'kw' . $kw)
            );

            return $cocokQty && $cocokKayu && $cocokKw;
        });

        $ket = $match?->keterangan;

        return ($ket !== null && trim((string) $ket) !== '') ? trim((string) $ket) : null;
    }

    /**
     * Susun keterangan lengkap untuk baris ledger:
     * "No Nota: {no} | Diterima: {nama}" (+ " | Ket: {ket}" bila ada).
     */
    protected function susunKeterangan(VeneerMutasi $mutasi, string $namaPenerima, ?string $ketDetail): string
    {
        $parts = [
            'No Nota: ' . (trim((string) $mutasi->no_nota) !== '' ? $mutasi->no_nota : '-'),
            'Diterima: ' . $namaPenerima,
        ];

        // Keterangan: prioritaskan keterangan per-baris (dari nota),
        // kalau tidak ada pakai keterangan mutasi (kalau terisi).
        $ket = $ketDetail
            ?? (trim((string) $mutasi->keterangan) !== '' ? trim((string) $mutasi->keterangan) : null);

        if ($ket !== null) {
            $parts[] = 'Ket: ' . $ket;
        }

        return implode(' | ', $parts);
    }

    /**
     * Catat satu baris transaksi MASUK ke ledger gudang_veneer_kering
     * lengkap dengan snapshot moving-average.
     */
    protected function postMasuk(
        VeneerMutasi $mutasi,
        VeneerMutasiDetail $detail,
        int $userId,
        string $namaPenerima,
        ?string $ketDetail
    ): void {
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

        $keteranganFinal = $this->susunKeterangan($mutasi, $namaPenerima, $ketDetail);

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
            'keterangan'              => $keteranganFinal,             // No Nota | Diterima | Ket
            'diterima_oleh'           => $userId,                      // user login
            'id_veneer_mutasi_detail' => $detail->id,
        ]);
    }
}