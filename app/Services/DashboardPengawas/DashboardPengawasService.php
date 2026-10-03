<?php

namespace App\Services\DashboardPengawas;

use App\Models\User;
use Illuminate\Support\Collection;

class DashboardPengawasService
{
    /** @var DashboardSourceInterface[] */
    protected array $sources;

    /**
     * @param DashboardSourceInterface[] $sources
     */
    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    /**
     * @return DashboardSourceInterface[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Menjalankan iterasi ke semua source, lalu mengambil data 
     * HANYA untuk source yang punya hak akses bagi user terkait.
     */
    public function getDataForUser(User $user, string $tanggal): Collection
    {
        $data = collect();

        foreach ($this->sources as $source) {
            if ($source->canAccess($user)) {
                $data->push([
                    'label'        => $source->getLabel(),
                    'produksi'     => $source->getProduksi($tanggal),
                    'serah_terima' => $source->getSerahTerima($tanggal),
                    'pegawai'      => $source->getPegawai($tanggal),
                ]);
            }
        }

        return $data;
    }
}
