<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_repeat_yearly',
        'source',
    ];

    protected $casts = [
        'date' => 'date',
        'is_repeat_yearly' => 'boolean',
    ];

    /**
     * Scope: cek libur pada tanggal tertentu.
     */
    public function scopeOnDate(Builder $query, $date): Builder
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $query->where(function ($q) use ($date) {
            $q->whereDate('date', $date->toDateString())
                ->orWhere(function ($q2) use ($date) {
                    $q2->where('is_repeat_yearly', true)
                        ->whereRaw("DATE_FORMAT(date, '%m-%d') = ?", [$date->format('m-d')]);
                });
        });
    }
}