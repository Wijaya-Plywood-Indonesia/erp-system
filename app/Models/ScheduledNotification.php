<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledNotification extends Model
{
    //
    protected $table = 'scheduled_notifications';
    protected $fillable = [
        'key',
        'scheduled_at',
        'last_run_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];
}
