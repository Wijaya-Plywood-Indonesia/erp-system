<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\PenggunaanLahanRotary;

/**
 * HppVeneerBasahBatchService
 *
 * Sumber HPP veneer basah = Persentase Kayu (per batch lahan).
 * Service ini hanya jembatan: palet → baris penggunaan lahan → batch (closure)
 * → komponen HPP per m³ dari ProduksiInflowService::hitungKomponenHppBatch().
 *
 * ISTILAH:
 *   - closure = baris penggunaan_lahan_rotaries yang sudah "Selesaikan Lahan"
 *               (jumlah_batang > 0). Sama dengan definisi di Persentase Kayu.
 *   - batch   = semua baris lahan yang sama antara closure sebelumnya
 *               (eksklusif) s/d closure ini (inklusif).
 *
 * Palet yang batch-nya BELUM ditutup → komponen = null (HPP belum final).
 * Pemanggil memutuskan: isi sementara atau tunggu sampai lahan selesai.
 */
class HppVeneerBasahBatchService
{
    public function __construct(
        protected ProduksiInflowService $inflow,
    ) {}

    /**
     * Cari closure (penutup batch) untuk satu baris penggunaan lahan:
     * baris lahan yang sama, jumlah_batang > 0, dibuat pada/ setelah baris ini.
     */
    public function cariClosure(PenggunaanLahanRotary $penggunaan): ?PenggunaanLahanRotary
    {
        return PenggunaanLahanRotary::query()
            ->where('id_lahan', $penggunaan->id_lahan)
            ->where('jumlah_batang', '>', 0)
            ->where('created_at', '>=', $penggunaan->created_at)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    /** Komponen HPP per m³ untuk satu closure. */
    public function komponenUntukClosure(PenggunaanLahanRotary $closure): array
    {
        return $this->inflow->hitungKomponenHppBatch($closure);
    }

    /** Komponen HPP per m³ untuk satu palet rotary; null jika batch lahannya belum selesai. */
    public function komponenUntukPalet(DetailHasilPaletRotary $palet): ?array
    {
        $penggunaan = $palet->penggunaanLahan;
        if (! $penggunaan) {
            return null;
        }

        $closure = $this->cariClosure($penggunaan);
        if (! $closure) {
            return null;
        }

        return $this->komponenUntukClosure($closure);
    }
}