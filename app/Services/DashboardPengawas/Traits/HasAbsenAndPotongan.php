<?php

namespace App\Services\DashboardPengawas\Traits;

use App\Models\User;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use App\Services\PotonganGajiService;

trait HasAbsenAndPotongan
{
    protected ?AbsensiSourceInterface $absensiSource = null;

    public function setAbsensiSource(AbsensiSourceInterface $source): void
    {
        $this->absensiSource = $source;
    }

    public function getAbsensiSource(): ?AbsensiSourceInterface
    {
        return $this->absensiSource;
    }

    /**
     * Cek akses user ke divisi ini berdasarkan permission di
     * config/dashboard_pengawas.php.
     *
     * Memakai $user->can() supaya permission dari role ikut terhitung
     * dan Gate::before super admin Shield tetap berlaku.
     */
    protected function bolehAkses(User $user): bool
    {
        $cfg = config('dashboard_pengawas', []);

        $bypass = $cfg['bypass_roles'] ?? [];
        if ($bypass !== [] && $user->hasAnyRole($bypass)) {
            return true;
        }

        foreach ($cfg['akses'][$this->getLabel()] ?? [] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function pegawaiDariAbsensi(string $tanggal): array
    {
        if (! $this->absensiSource) {
            return ['total' => 0, 'list' => [], 'filter_absen' => true];
        }

        $total = $this->absensiSource->fetch($tanggal)
            ->pluck('id_pegawai')->filter()->unique()->count();

        return [
            'total' => $total,
            'list' => [],
            'filter_absen' => true,
        ];
    }

    public function getPotonganMap(string $tanggal): array
    {
        $methodMap = [
            'Rotary' => 'loadPotonganRotary',
            'Press Dryer' => 'loadPotonganDryer',
            'Pot Siku' => 'loadPotonganPotSiku',
            'Pot Jelek' => 'loadPotonganPotJelek',
            'Kedi' => 'loadPotonganKedi',
            'Hotpress' => 'loadPotonganHotpress',
            'Graji Stik' => 'loadPotonganStik',
            'Stik' => 'loadPotonganStik',
            'Repair' => 'loadPotonganRepair',
            'Joint' => 'loadPotonganJoint',
            'Pot AF Joint' => 'loadPotonganPotAfJoint',
            'Sanding Joint' => 'loadPotonganSandingJoint',
            'Pilih Veneer' => 'loadPotonganPilihVeneer',
            'Sanding' => 'loadPotonganSanding',
            'Tembel Triplek' => 'loadPotonganTembelTriplek',
            'Dempul' => 'loadPotonganDempul',
            'Pilih Plywood' => 'loadPotonganPilihPlywood',
            'Buat Palet' => 'loadPotonganBuatPalet',
            'Guellotine' => 'loadPotonganGuellotine',
            'Nyusup' => 'loadPotonganNyusup',
        ];

        $label = $this->getLabel();
        if (isset($methodMap[$label])) {
            return app(PotonganGajiService::class)
                ->getPotonganMapFor($tanggal, $methodMap[$label]);
        }

        return [];
    }
}
