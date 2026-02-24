<?php

namespace App\Helpers;

use App\Models\HariLibur;
use Carbon\Carbon;

class HolidayHelper
{
    /**
     * Cek apakah suatu tanggal adalah hari libur nasional.
     */
    public static function isHoliday($date): bool
    {
        return HariLibur::onDate(
            Carbon::parse($date)->toDateString()
        )->exists();
    }

    /**
     * Ambil detail hari liburnya (jika ada).
     */
    public static function getHoliday($date)
    {
        return HariLibur::onDate(
            Carbon::parse($date)->toDateString()
        )->first();
    }

    /**
     * Cek apakah suatu tanggal adalah hari kerja.
     * Senin–Sabtu kerja | Minggu libur | Libur nasional libur
     */
    public static function isWorkingDay($date): bool
    {
        $carbon = Carbon::parse($date);

        return !$carbon->isSunday()
            && !self::isHoliday($carbon->toDateString());
    }

    /**
     * Mendapatkan hari kerja berikutnya.
     */
    public static function nextWorkingDay($date)
    {
        $date = Carbon::parse($date);

        while ($date->isSunday() || self::isHoliday($date->toDateString())) {
            $date->addDay();
        }

        return $date;
    }

    /**
     * Mendapatkan hari kerja sebelumnya.
     */
    public static function previousWorkingDay($date)
    {
        $date = Carbon::parse($date);

        while ($date->isSunday() || self::isHoliday($date->toDateString())) {
            $date->subDay();
        }

        return $date;
    }

    /**
     * Ambil semua hari libur dalam rentang tanggal.
     */
    public static function holidaysBetween($start, $end)
    {
        return HariLibur::whereBetween('date', [
            Carbon::parse($start)->toDateString(),
            Carbon::parse($end)->toDateString(),
        ])->get();
    }
}
