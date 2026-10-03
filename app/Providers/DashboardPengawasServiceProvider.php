<?php

namespace App\Providers;

use App\Services\DashboardPengawas\DashboardPengawasService;
use App\Services\DashboardPengawas\Sources\ConfigurableDashboardSource;
use App\Services\DashboardPengawas\Sources\GenericDashboardSource;
use App\Services\DashboardPengawas\Sources\HpDashboardSource;
use App\Services\DashboardPengawas\Sources\KediDashboardSource;
use App\Services\DashboardPengawas\Sources\PotJelekDashboardSource;
use App\Services\DashboardPengawas\Sources\PotSikuDashboardSource;
use App\Services\DashboardPengawas\Sources\PressDryerDashboardSource;
use App\Services\DashboardPengawas\Sources\RotaryDashboardSource;
use App\Services\NewRekapAbsensiPegawaiService;
use Illuminate\Support\ServiceProvider;

class DashboardPengawasServiceProvider extends ServiceProvider
{
    /**
     * Label divisi yang TIDAK ditampilkan di dashboard.
     * Tambahkan label lain di sini bila perlu, mis. ['Lain-lain', 'Pegawai Palet'].
     */
    protected array $dikecualikan = ['Lain-lain'];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DashboardPengawasService::class, function ($app) {
            // Instansiasi source spesifik
            $specificSources = [
                'Hotpress' => new HpDashboardSource,
                'Rotary' => new RotaryDashboardSource,
                'Press Dryer' => new PressDryerDashboardSource,
                'Pot Siku' => new PotSikuDashboardSource,
                'Pot Jelek' => new PotJelekDashboardSource,
                'Kedi' => new KediDashboardSource,
            ];

            // Ambil semua sumber absensi
            $absensiService = $app->make(NewRekapAbsensiPegawaiService::class);
            $absensiSources = $absensiService->getSources();

            $dashboardSources = [];

            // Masukkan source spesifik yang punya implementasi detail
            foreach ($specificSources as $label => $source) {
                if (in_array($label, $this->dikecualikan, true)) {
                    continue;
                }

                if (method_exists($source, 'setAbsensiSource')) {
                    $match = collect($absensiSources)->first(fn ($a) => $a->label() === $label);
                    if ($match) {
                        $source->setAbsensiSource($match);
                    }
                }
                $dashboardSources[] = $source;
            }

            $sourceMappings = [
                'Graji Stik' => ['graji_stiks', 'hasil_graji_stiks', 'hasil_graji', 'id_graji_stiks', false, 'Lembar'],
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

                // Lewati divisi yang disembunyikan dari dashboard
                if (in_array($label, $this->dikecualikan, true)) {
                    continue;
                }

                if (! array_key_exists($label, $specificSources)) {
                    if (array_key_exists($label, $sourceMappings)) {
                        $m = $sourceMappings[$label];
                        $dashboardSources[] = new ConfigurableDashboardSource(
                            $label, $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $abSource
                        );
                    } else {
                        $dashboardSources[] = new GenericDashboardSource($abSource);
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
