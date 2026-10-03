<?php

namespace App\Services\DashboardPengawas;

use App\Models\User;

interface DashboardSourceInterface
{
    public function getLabel(): string;

    public function canAccess(User $user): bool;

    /** @return array{total: int|float, satuan: string, detail: array} */
    public function getProduksi(string $tanggal): array;

    /** @return array{total: int|float, satuan: string, detail: array} */
    public function getSerahTerima(string $tanggal): array;

    /** @return array{total: int, list: array, filter_absen?: bool} */
    public function getPegawai(string $tanggal): array;

    /** @return array<string, int> */
    public function getPotonganMap(string $tanggal): array;
}
