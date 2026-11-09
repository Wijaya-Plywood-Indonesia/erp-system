<?php

namespace App\Jobs;

use App\Models\BkbmOutbox;
use App\Services\BkBm\BkBmKirimService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class KirimBkbmOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // pengulangan diatur sendiri oleh kolom kirim_berikutnya_at

    public function __construct(public int $idOutbox) {}

    public function handle(BkBmKirimService $svc): void
    {
        $row = BkbmOutbox::find($this->idOutbox);
        if ($row && $row->status === 'pending') {
            $svc->kirim($row);
        }
    }
}
