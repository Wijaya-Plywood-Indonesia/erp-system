<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DashboardPengawas\DashboardPengawasService;
use App\Services\DashboardPengawas\Sources\HpDashboardSource;
use App\Services\DashboardPengawas\Sources\RotaryDashboardSource;
use App\Services\DashboardPengawas\Sources\PressDryerDashboardSource;

class DashboardPengawasServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DashboardPengawasService::class, function ($app) {
            // Instansiasi source spesifik
            $specificSources = [
                'Hotpress'   => new HpDashboardSource(),
                'Rotary'      => new RotaryDashboardSource(),
                'Press Dryer' => new PressDryerDashboardSource(),
                'Pot Siku'    => new \App\Services\DashboardPengawas\Sources\PotSikuDashboardSource(),
                'Pot Jelek'   => new \App\Services\DashboardPengawas\Sources\PotJelekDashboardSource(),
            ];

            // Ambil semua sumber absensi
            $absensiService = $app->make(\App\Services\NewRekapAbsensiPegawaiService::class);
            $absensiSources = $absensiService->getSources();

            $dashboardSources = [];

            // Masukkan source spesifik yang punya implementasi detail
            foreach ($specificSources as $source) {
                $dashboardSources[] = $source;
            }

            $sourceMappings = [
                'Graji Stik' => ['graji_stiks', 'hasil_graji_stiks', 'hasil_graji', 'id_graji_stiks', false, 'Lembar'],
                'Kedi' => ['produksi_kedi', 'detail_bongkar_kedi', 'jumlah', 'id_produksi_kedi', true, 'Lembar'],
                'Stik' => ['produksi_stik', 'detail_hasil_stik', 'total_lembar', 'id_produksi_stik', true, 'Lembar'],
                'Repair' => ['produksi_repairs', 'detail_hasil_repairs', 'jumlah', 'id_produksi_repair', true, 'Lembar'],
                'Joint' => ['produksi_joint', 'hasil_joint', 'jumlah', 'id_produksi_joint', true, 'Lembar'],
                'Pot AF Joint' => ['produksi_pot_af_joint', 'hasil_pot_af_joint', 'jumlah', 'id_produksi_pot_af_joint', true, 'Lembar'],
                'Sanding Joint' => ['produksi_sanding_joint', 'hasil_sanding_joint', 'jumlah', 'id_produksi_sanding_joint', true, 'Lembar'],
                'Graji Balken' => ['produksi_graji_balken', 'hasil_graji_balken', 'jumlah', 'id_produksi_graji_balken', false, 'Lembar'],
                'Guellotine' => ['produksi_guellotine', 'hasil_guellotine', 'jumlah', 'id_produksi_guellotine', false, 'Lembar'],
                'Pilih Veneer' => ['produksi_pilih_veneer', 'hasil_pilih_veneer', 'jumlah', 'id_produksi_pilih_veneer', true, 'Lembar'],
                'Sanding' => ['produksi_sandings', 'hasil_sandings', 'kuantitas', 'id_produksi_sanding', false, 'Lembar'],
                'Tembel Triplek' => ['produksi_tembel_triplek', 'hasil_tembel_triplek', 'hasil', 'id_produksi_tembel_triplek', false, 'Lembar'],
                'Dempul' => ['produksi_dempuls', 'detail_dempuls', 'hasil', 'id_produksi_dempul', false, 'Lembar'],
                'Graji Triplek' => ['produksi_graji_triplek', 'hasil_graji_triplek', 'isi', 'id_produksi_graji_triplek', false, 'Lembar'],
                'Pilih Plywood' => ['produksi_pilih_plywood', 'hasil_pilih_plywood', 'jumlah', 'id_produksi_pilih_plywood', false, 'Lembar'],
                'Terima Gudang Satu' => ['produksi_terima_gudang_satu', 'hasil_terima_gudang_satu', 'jumlah', 'id_produksi_terima_gudang_satu', false, 'Lembar'],
                'Turun Kayu' => ['turun_kayus', 'detail_turun_kayus', 'jumlah_kayu', 'id_turun_kayu', false, 'Batang'],
            ];

            // Untuk sisanya, gunakan GenericDashboardSource atau Configurable
            foreach ($absensiSources as $abSource) {
                $label = $abSource->label();
                if (!array_key_exists($label, $specificSources)) {
                    if (array_key_exists($label, $sourceMappings)) {
                        $m = $sourceMappings[$label];
                        $dashboardSources[] = new \App\Services\DashboardPengawas\Sources\ConfigurableDashboardSource(
                            $label, $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $abSource
                        );
                    } else {
                        $dashboardSources[] = new \App\Services\DashboardPengawas\Sources\GenericDashboardSource($abSource);
                    }
                }
            }

            return new DashboardPengawasService($dashboardSources);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
