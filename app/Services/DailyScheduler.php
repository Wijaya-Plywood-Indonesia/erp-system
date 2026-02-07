<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\ScheduledNotification;

class DailyScheduler
{
    /**
     * Mengecek apakah job perlu dijalankan.
     */
    public static function checkAndRun(string $key, string $time, \Closure $callback)
    {
        // Ambil data dari DB
        $schedule = ScheduledNotification::firstOrCreate(
            ['key' => $key],
            ['scheduled_at' => Carbon::today()->setTimeFromTimeString($time)]
        );

        $today = Carbon::today();
        $scheduledDateTime = Carbon::today()->setTimeFromTimeString($time);

        // Jika jadwal sudah lewat dan belum dijalankan hari ini
        if (
            now()->greaterThan($scheduledDateTime) &&
            ($schedule->last_run_at === null || !$schedule->last_run_at->isToday())
        ) {
            // Jalankan callback
            $callback();

            // Update last_run_at
            $schedule->last_run_at = now();
            $schedule->save();

            return true; // menandakan job dieksekusi
        }

        return false; // job tidak dieksekusi
    }
}