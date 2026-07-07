<?php

namespace App\Services;

use App\Models\DetailNotaBarangMasuk;
use App\Models\StokVeneerKering;
use App\Models\User;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SerahTerimaVeneerKeringService
{
    /**
     * Pola nilai `tipe_veneer` yang dianggap "veneer kering".
     */
    public const TIPE_KERING_LIKE = '%kering%';

    /**
     * Terima baris VENEER KERING TERPILIH dari sebuah VeneerMutasi.
     *
     * @param array $detailIds  Id VeneerMutasiDetail yang dicentang user.
     *                          Kosong = terima semua baris kering.
     *
     * Inilah titik di mana stok kering BENAR-BENAR masuk: menulis baris
     * 'masuk' ke stok_veneer_kerings (tabel yang jadi sumber Stok & Log HPP),
     * lalu memanggil recalculateStokKering() agar snapshot/HPP terhitung.
     *
     * Idempotent: detail yang sudah punya baris di stok_veneer_kerings dilewati.
     */
    public function terima(VeneerMutasi $mutasi, int $userId, array $detailIds = []): void
    {
        DB::transaction(function () use ($mutasi, $userId, $detailIds) {
            $details = $mutasi->details()
                ->where('tipe_veneer', 'like', self::TIPE_KERING_LIKE)
                ->when(! empty($detailIds), fn ($q) => $q->whereIn('id', $detailIds))
                ->with('jenisKayu')
                ->get();

            $namaPenerima = User::find($userId)?->name ?? 'Tidak diketahui';

            // Sumber keterangan per baris.
            $notaDetails = DetailNotaBarangMasuk::query()
                ->where('id_nota_bm', $mutasi->id_nota_bm)
                ->get();

            $mutasiService = app(VeneerMutasiService::class);

            foreach ($details as $detail) {
                $sudahAda = StokVeneerKering::query()
                    ->where('id_veneer_mutasi_detail', $detail->id)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                $ketDetail  = $this->cariKeteranganDetail($detail, $notaDetails);
                $keterangan = $this->susunKeterangan($mutasi, $namaPenerima, $ketDetail);

                // Insert baris mentah (snapshot 0) — persis pola updateStokKering.
                StokVeneerKering::create([
                    'id_produksi_dryer'       => null,
                    'id_ukuran'               => $detail->id_ukuran,
                    'id_jenis_kayu'           => $detail->id_jenis_kayu,
                    'kw'                      => $detail->kw,
                    'jenis_transaksi'         => 'masuk',
                    'tanggal_transaksi'       => $mutasi->tanggal,
                    'qty'                     => $detail->qty,
                    'm3'                      => $detail->m3,
                    'hpp_veneer_basah_per_m3' => 0,
                    'ongkos_dryer_per_m3'     => 0,
                    'hpp_kering_per_m3'       => 0,
                    'nilai_transaksi'         => 0,
                    'stok_lembar_sebelum'     => 0,
                    'stok_lembar_sesudah'     => 0,
                    'stok_m3_sebelum'         => 0,
                    'stok_m3_sesudah'         => 0,
                    'nilai_stok_sebelum'      => 0,
                    'nilai_stok_sesudah'      => 0,
                    'hpp_average'             => 0,
                    'keterangan'              => $keterangan,
                    'id_veneer_mutasi'        => $mutasi->id,
                    'id_veneer_mutasi_detail' => $detail->id,
                ]);

                // Hitung ulang running balance & HPP untuk kombinasi ini.
                $mutasiService->recalculateStokKering(
                    (int) $detail->id_ukuran,
                    (int) $detail->id_jenis_kayu,
                    (string) $detail->kw
                );
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
            $namaTNS = str_replace(' ', '', $nama);
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
     * Susun keterangan lengkap:
     * "No Nota: {no} | Diterima: {nama}" (+ " | Ket: {ket}" bila ada).
     */
    protected function susunKeterangan(VeneerMutasi $mutasi, string $namaPenerima, ?string $ketDetail): string
    {
        $parts = [
            'No Nota: ' . (trim((string) $mutasi->no_nota) !== '' ? $mutasi->no_nota : '-'),
            'Diterima: ' . $namaPenerima,
        ];

        $ket = $ketDetail
            ?? (trim((string) $mutasi->keterangan) !== '' ? trim((string) $mutasi->keterangan) : null);

        if ($ket !== null) {
            $parts[] = 'Ket: ' . $ket;
        }

        return implode(' | ', $parts);
    }
}