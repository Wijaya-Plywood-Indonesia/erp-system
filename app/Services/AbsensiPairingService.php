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

        // 2. Determine the shift first (PAGI or MALAM) based on forcedShift, explicit time windows, or the presence of a night‑time entry.
        $isShiftMalam = false;
        // 2a. Forced shift takes priority
        if ($forcedShift === 'MALAM') {
            $isShiftMalam = true;
        } elseif ($forcedShift === 'PAGI') {
            $isShiftMalam = false;
        } else {
            // 2b. Auto‑detect: use the earliest entry of the target date *after* skipping a possible previous checkout
            $firstOfDay = null;
            foreach ($filtered as $e) {
                if ($e['date'] !== $targetDate) {
                    continue;
                }
                // Skip the scan that matches the previous night‑shift checkout (handled earlier)
                if ($prevCheckout && $e['full']->diffInMinutes($prevCheckout, true) <= 5) {
                    continue;
                }
                $firstOfDay = $e;
                break;
            }
            if ($firstOfDay && $firstOfDay['time'] >= '14:00:00') {
                $isShiftMalam = true;
            }
        }

        if (!$forcedShift) {
            $firstScanToday = $sorted->first(fn($e) => $e['date'] === $targetDate);
            if ($firstScanToday) {
                $scanHour    = Carbon::parse($firstScanToday['full'])->hour;
                $forcedShift = $scanHour >= 14 ? 'MALAM' : 'PAGI';
                $isShiftMalam = $forcedShift === 'MALAM'; // ← tambahkan baris ini
            }
        }

        // 3. Find the first valid IN scan on targetDate according to the detected shift.
        $firstTap = null;
        foreach ($filtered as $entry) {
            if ($entry['date'] !== $targetDate) {
                continue;
            }
            // Skip a scan that is essentially the checkout of the previous shift
            if ($prevCheckout && $entry['full']->diffInMinutes($prevCheckout, true) <= 5) {
                continue;
            }

            $time = $entry['time'];
            if ($isShiftMalam) {
                // Night shift: accept scans from 14:00 to 23:59
                if ($time >= '14:00:00' && $time <= '23:59:59') {
                    $firstTap = $entry;
                    break;
                }
            } else {
                // Day shift: accept scans from 05:00 to 13:59
                if ($time >= '05:00:00' && $time <= '13:59:59') {
                    $firstTap = $entry;
                    break;
                }
            }
        }

        // 4. Fallback – if no scan matches the strict window we still take the earliest entry of the day.
        if (!$firstTap) {
            foreach ($filtered as $entry) {
                if ($entry['date'] === $targetDate) {
                    $firstTap = $entry;
                    break;
                }
            }
        }

        if (!$firstTap) {
            return null; // No entry at all for the target date.
        }

        $jamMasuk = $firstTap['time'];
        $jamMasukDt = $firstTap['full'];

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
