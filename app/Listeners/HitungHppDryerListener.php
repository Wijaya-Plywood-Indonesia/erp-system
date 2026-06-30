<?php

namespace App\Listeners;

use App\Events\ProductionUpdated;
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
    if ($event->type !== 'dryer') {
        return;
    }

    // ✅ Hanya proses jika produksi sudah divalidasi
    $sudahValidasi = \App\Models\ValidasiPressDryer::where('id_produksi_dryer', $event->productionId)
        ->where('status', 'divalidasi')
        ->exists();

    if (!$sudahValidasi) {
        return;
    }

    $this->service->prosesProduksi($event->productionId);
}

    public function failed(ProductionUpdated $event, \Throwable $exception): void
    {
        Log::error(
            "Gagal hitung HPP dryer untuk produksi #{$event->productionId}: "
            . $exception->getMessage()
        );
    }
}