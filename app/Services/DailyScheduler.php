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
        // Ambil data schedule dari database
        $schedule = ScheduledNotification::firstOrCreate(
            ['key' => $key],
            ['scheduled_at' => Carbon::today()->setTimeFromTimeString($time)]
        );

        // Gunakan tanggal dan jam dari DB, BUKAN dari "today()"
        $scheduledDateTime = Carbon::parse($schedule->scheduled_at);

        // Jika schedule sudah lewat dan belum dijalankan hari ini
        if (
            now()->greaterThan($scheduledDateTime) &&
            ($schedule->last_run_at === null || !$schedule->last_run_at->isToday())
        ) {
            // Jalankan job
            $callback();

            // Update waktu job dijalankan
            $schedule->last_run_at = now();

            // Jadwalkan eksekusi besok pada jam yang sama
            $schedule->scheduled_at = now()->addDay()->setTimeFromTimeString($time);

            $schedule->save();

            return true;
        }

        return false;
    }
}