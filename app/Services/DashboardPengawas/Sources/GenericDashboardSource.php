<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;

class GenericDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function __construct(AbsensiSourceInterface $absensiSource)
    {
        $this->absensiSource = $absensiSource;
    }

    public function getLabel(): string
    {
        return $this->absensiSource->label();
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        return ['total' => 0, 'satuan' => '-', 'detail' => []];
    }

    public function getSerahTerima(string $tanggal): array
    {
        return ['total' => 0, 'satuan' => '-', 'detail' => []];
    }

    public function getPegawai(string $tanggal): array
    {
        return [
            'total' => $this->absensiSource->fetch($tanggal)->count(),
            'list' => [],
        ];
    }
}
