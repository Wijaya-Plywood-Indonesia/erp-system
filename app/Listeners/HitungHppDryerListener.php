<?php

namespace App\Listeners;

use App\Events\ProductionUpdated;
use App\Models\ValidasiKedi;
use App\Models\ValidasiPressDryer;
use App\Services\HppDryerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HitungHppDryerListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'hpp';

    public function __construct(protected HppDryerService $service)
    {
    }

    public function handle(ProductionUpdated $event): void
    {
        // Press Dryer — hanya proses jika produksi sudah divalidasi
        if ($event->type === 'dryer') {
            $sudahValidasi = ValidasiPressDryer::where('id_produksi_dryer', $event->productionId)
                ->where('status', 'divalidasi')
                ->exists();

            if (! $sudahValidasi) {
                return;
            }

            $this->service->prosesProduksi($event->productionId);

            return;
        }

        // Kedi — hanya proses jika bongkar sudah divalidasi
        if ($event->type === 'kedi') {
            $sudahBongkar = ValidasiKedi::where('id_produksi_kedi', $event->productionId)
                ->where('tipe', 'bongkar')
                ->where('status', 'divalidasi')
                ->exists();

            if (! $sudahBongkar) {
                return;
            }

            $this->service->prosesBanyakKedi([(int) $event->productionId]);
        }
    }

    public function failed(ProductionUpdated $event, \Throwable $exception): void
    {
        Log::error(
            "Gagal hitung HPP {$event->type} untuk produksi #{$event->productionId}: "
            . $exception->getMessage()
        );
    }
}
