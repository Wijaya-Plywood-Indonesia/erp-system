<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $productionId,
        public $type
    ) {}

    public function broadcastOn(): array
    {
        // Channel khusus berdasarkan tipe (veneer) dan ID produksinya
        return [
            new Channel("production.{$this->type}.{$this->productionId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProductionUpdated';
    }
}
