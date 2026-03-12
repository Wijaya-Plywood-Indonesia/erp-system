<?php

namespace App\Listeners;

use App\Events\ProductionUpdated;
use App\Services\HppDryerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HitungHppDryerListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'hpp';

    public function __construct(protected HppDryerService $service)
    {
    }

    public function handle(ProductionUpdated $event): void
    {
        // Hanya proses event dari dryer, bukan departemen lain
        if ($event->tipe !== 'dryer') {
            return;
        }

        $this->service->prosesProduksi($event->idProduksi);
    }

    public function failed(ProductionUpdated $event, \Throwable $exception): void
    {
        \Log::error(
            "Gagal hitung HPP dryer untuk produksi #{$event->idProduksi}: "
            . $exception->getMessage()
        );
    }
}