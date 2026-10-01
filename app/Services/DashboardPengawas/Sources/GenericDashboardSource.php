<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use Illuminate\Support\Facades\DB;

class GenericDashboardSource implements DashboardSourceInterface
{
    protected AbsensiSourceInterface $absensiSource;

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
        return true; 
    }

    public function getProduksi(string $tanggal): array
    {
        return [
            'total'  => 0,
            'satuan' => '-',
            'detail' => [],
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        return [
            'total'  => 0,
            'satuan' => '-',
            'detail' => [],
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        // Because the dashboard now uses NewRekapAbsensiPegawaiService for 
        // the table display, this just needs to return a quick total 
        // for the summary card.
        $rows = $this->absensiSource->fetch($tanggal);
        return [
            'total' => $rows->count(),
            'list'  => []
        ];
    }
}
