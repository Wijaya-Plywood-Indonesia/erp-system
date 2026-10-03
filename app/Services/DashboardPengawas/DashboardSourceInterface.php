<?php

namespace App\Services\DashboardPengawas;

use App\Models\User;

interface DashboardSourceInterface
{
    /**
     * Nama departemen / sumber (misal: "Produksi Rotary")
     */
    public function getLabel(): string;

    /**
     * Cek apakah user saat ini punya akses ke sumber ini
     */
    public function canAccess(User $user): bool;

    /**
     * Ambil data rekap produksi (misal total barang, satuan, dsb.)
     * @return array{total: int|float, satuan: string, detail: array}
     */
    public function getProduksi(string $tanggal): array;

    /**
     * Ambil data rekap serah terima barang
     * @return array{total: int|float, satuan: string, detail: array}
     */
    public function getSerahTerima(string $tanggal): array;

    /**
     * Ambil data rekap kehadiran pegawai (total hadir, dan list nama + jam masuk)
     * @return array{total: int, list: array}
     */
    public function getPegawai(string $tanggal): array;
}
