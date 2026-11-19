<?php

namespace App\Console\Commands;

use App\Models\BkbmOutbox;
use App\Services\BkBm\BkBmKirimService;
use Illuminate\Console\Command;

class KirimBkbmOutbox extends Command
{
    protected $signature = 'bkbm:kirim-outbox';
    protected $description = 'Kirim ulang pesan BK/BM antar pabrik yang masih pending';

    public function handle(BkBmKirimService $svc): int
    {
        $rows = BkbmOutbox::where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('kirim_berikutnya_at')->orWhere('kirim_berikutnya_at', '<=', now()))
            ->orderBy('id')->limit(50)->get();

        foreach ($rows as $row) {
            $this->line(($svc->kirim($row) ? 'OK    ' : 'GAGAL ') . "#{$row->id} {$row->jenis}");
        }
        return self::SUCCESS;
    }
}
