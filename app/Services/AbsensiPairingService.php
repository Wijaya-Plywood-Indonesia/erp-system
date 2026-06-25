<?php

namespace App\Services;

use Carbon\Carbon;

class AbsensiPairingService
{
    /**
      * Pair raw log entries for a single employee on a given target date.
     *
     * @param array $entries Raw log entries containing 'date', 'time', and 'full' (Carbon instance)
     * @param string $targetDate Format 'Y-m-d'
     * @param string $nextDate Format 'Y-m-d'
     * @param Carbon|null $prevCheckout Previous shift's checkout Carbon datetime (if any)
     * @param string|null $forcedShift Override detected shift ('PAGI' or 'MALAM')
     * @return array|null Returns ['jam_masuk' => string, 'jam_pulang' => string|null] or null if no valid check-in
     */
    public function pairEmployeeLogs(array $entries, string $targetDate, string $nextDate, ?Carbon $prevCheckout = null, ?string $forcedShift = null): ?array
    {
        // 1. Filter out duplicate scans within a 5-minute threshold.
        $sorted = collect($entries)->sortBy('full');
        $filtered = [];
        foreach ($sorted as $entry) {
            if (empty($filtered)) {
                $filtered[] = $entry;
            } else {
                $last = end($filtered);
                if ($entry['full']->diffInMinutes($last['full'], true) >= 5) {
                    $filtered[] = $entry;
                }
            }
        }

        // 2. Find the first valid IN scan on targetDate.
        $firstTap = null;
        foreach ($filtered as $entry) {
            if ($entry['date'] === $targetDate) {
                // If this scan corresponds to the checkout of the previous shift, skip it
                if ($prevCheckout && $entry['full']->diffInMinutes($prevCheckout, true) <= 5) {
                    continue;
                }

                $time = $entry['time'];
                
                // If forced shift is specified, filter by its specific check-in window
                if ($forcedShift === 'MALAM') {
                    if ($time >= '14:00:00' && $time <= '23:59:59') {
                        $firstTap = $entry;
                        break;
                    }
                } elseif ($forcedShift === 'PAGI') {
                    if ($time >= '05:00:00' && $time <= '13:59:59') {
                        $firstTap = $entry;
                        break;
                    }
                } else {
                    // Default logic: Day Shift (05:00 - 13:59) or Night Shift (14:00 - 23:59)
                    if ($time >= '05:00:00' && $time <= '13:59:59') {
                        $firstTap = $entry;
                        break;
                    }
                }
            }
        }

        if (!$firstTap) {
            return null;
        }

        $jamMasuk = $firstTap['time'];
        $jamMasukDt = $firstTap['full'];

        // 3. Classify Shift
        if ($forcedShift === 'MALAM') {
            $isShiftMalam = true;
        } elseif ($forcedShift === 'PAGI') {
            $isShiftMalam = false;
        } else {
            // Shift Malam: tap masuk antara jam 14:00 - 23:59
            $isShiftMalam = $jamMasukDt->hour >= 14;
        }

        $jamPulang = null;
        if ($isShiftMalam) {
            // Night Shift: look for checkout scan on nextDate in the OUT window (00:00:00 - 12:00:00)
            $checkoutScan = null;
            foreach ($filtered as $entry) {
                if ($entry['date'] === $nextDate) {
                    $time = $entry['time'];
                    if ($time >= '00:00:00' && $time <= '12:00:00') {
                        $checkoutScan = $entry; // Pick last scan in window
                    }
                }
            }
            if ($checkoutScan) {
                $jamPulang = $checkoutScan['time'];
            }
        } else {
            // Day Shift: look for checkout scan on targetDate in the OUT window (12:00:00 - 23:59:59) after jamMasuk
            $checkoutScan = null;
            foreach ($filtered as $entry) {
                if ($entry['date'] === $targetDate) {
                    $time = $entry['time'];
                    if ($time >= '12:00:00' && $time <= '23:59:59' && $entry['full']->gt($jamMasukDt)) {
                        $checkoutScan = $entry; // Pick last scan in window
                    }
                }
            }
            if ($checkoutScan) {
                $jamPulang = $checkoutScan['time'];
            }
        }

        return [
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
        ];
    }
}
