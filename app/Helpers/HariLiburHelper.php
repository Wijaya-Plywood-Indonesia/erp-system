<?php

use App\Models\HariLibur;
use Carbon\Carbon;

/**
 * Cek apakah suatu tanggal adalah hari libur nasional.
 */
if (!function_exists('isHoliday')) {
    function isHoliday($date): bool
    {
        return HariLibur::onDate(
            Carbon::parse($date)->toDateString()
        )->exists();
    }
}

/**
 * Ambil detail hari liburnya (jika ada).
 */
if (!function_exists('getHoliday')) {
    function getHoliday($date)
    {
        return HariLibur::onDate(
            Carbon::parse($date)->toDateString()
        )->first();
    }
}

/**
 * Cek apakah suatu tanggal adalah hari kerja.
 * Senin–Sabtu kerja | Minggu libur | Libur nasional libur
 */
if (!function_exists('isWorkingDay')) {
    function isWorkingDay($date): bool
    {
        $carbon = Carbon::parse($date);

        return !$carbon->isSunday()
            && !isHoliday($carbon->toDateString());
    }
}

/**
 * Mendapatkan hari kerja berikutnya.
 */
if (!function_exists('nextWorkingDay')) {
    function nextWorkingDay($date)
    {
        $date = Carbon::parse($date);

        while ($date->isSunday() || isHoliday($date->toDateString())) {
            $date->addDay();
        }

        return $date;
    }
}

/**
 * Mendapatkan hari kerja sebelumnya.
 */
if (!function_exists('previousWorkingDay')) {
    function previousWorkingDay($date)
    {
        $date = Carbon::parse($date);

        while ($date->isSunday() || isHoliday($date->toDateString())) {
            $date->subDay();
        }

        return $date;
    }
}

/**
 * Ambil semua hari libur dalam rentang tanggal.
 */
if (!function_exists('holidaysBetween')) {
    function holidaysBetween($start, $end)
    {
        return HariLibur::whereBetween('date', [
            Carbon::parse($start)->toDateString(),
            Carbon::parse($end)->toDateString(),
        ])->get();
    }
}
