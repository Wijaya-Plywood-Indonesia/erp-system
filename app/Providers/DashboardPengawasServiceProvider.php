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
                'Hot Press'   => new HpDashboardSource(),
                'Rotary'      => new RotaryDashboardSource(),
                'Press Dryer' => new PressDryerDashboardSource(),
            ];

            // Ambil semua sumber absensi
            $absensiService = $app->make(\App\Services\NewRekapAbsensiPegawaiService::class);
            $absensiSources = $absensiService->getSources();

            $dashboardSources = [];

            // Masukkan source spesifik yang punya implementasi detail
            foreach ($specificSources as $source) {
                $dashboardSources[] = $source;
            }

            // Untuk sisanya, gunakan GenericDashboardSource
            foreach ($absensiSources as $abSource) {
                $label = $abSource->label();
                if (!array_key_exists($label, $specificSources)) {
                    $dashboardSources[] = new \App\Services\DashboardPengawas\Sources\GenericDashboardSource($abSource);
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
