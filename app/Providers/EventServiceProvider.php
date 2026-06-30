<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ProductionUpdated::class => [
            \App\Listeners\HitungHppDryerListener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}