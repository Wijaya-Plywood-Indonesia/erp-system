<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        // Ambil entry pertama pada targetDate (setelah skip prevCheckout), dipakai di beberapa langkah.
        $firstOfDay = null;
        foreach ($filtered as $e) {
            if ($e['date'] !== $targetDate) {
                continue;
            }
            if ($prevCheckout && $e['full']->diffInMinutes($prevCheckout, true) <= 5) {
                continue;
            }
            $firstOfDay = $e;
            break;
        }

        // 2. Determine the shift first (PAGI or MALAM) based on forcedShift, explicit time windows,
        //    or the presence of a night-time entry.
        $isShiftMalam = false;

        if ($forcedShift === 'MALAM') {
            $isShiftMalam = true;
        } elseif ($forcedShift === 'PAGI') {
            $isShiftMalam = false;
        } elseif ($firstOfDay) {
            // Auto-detect dari scan pertama targetDate.
            $isShiftMalam = $firstOfDay['time'] >= '14:00:00';
        } else {
            // FIX #1: Tidak ada entry sama sekali di targetDate (mis. shift malam yang jam masuknya
            // tidak ter-scan). Cek apakah ada entry di nextDate pada jendela checkout malam
            // (00:00-12:00) — kalau ada, kemungkinan besar ini shift malam yang kehilangan scan masuk.
            $hasNightCheckoutOnly = collect($filtered)->contains(
                fn($e) => $e['date'] === $nextDate && $e['time'] >= '00:00:00' && $e['time'] <= '12:00:00'
            );

            if ($hasNightCheckoutOnly) {
                $isShiftMalam = true;
                Log::warning('Absensi: scan masuk hilang, shift malam terdeteksi dari checkout saja.', [
                    'targetDate' => $targetDate,
                    'nextDate' => $nextDate,
                ]);
            }
        }

        // 3. Find the first valid IN scan on targetDate according to the detected shift.
        $firstTap = null;
        foreach ($filtered as $entry) {
            if ($entry['date'] !== $targetDate) {
                continue;
            }
            if ($prevCheckout && $entry['full']->diffInMinutes($prevCheckout, true) <= 5) {
                continue;
            }

            $time = $entry['time'];
            if ($isShiftMalam) {
                if ($time >= '14:00:00' && $time <= '23:59:59') {
                    $firstTap = $entry;
                    break;
                }
            } else {
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

        // FIX #1 (lanjutan): Jika memang tidak ada scan sama sekali di targetDate (kasus shift malam
        // yang scan masuknya hilang total), tetap lanjut ke pencarian checkout tanpa jam_masuk pasti,
        // alih-alih langsung return null dan kehilangan data jam_pulang yang sebenarnya ada.
        if (!$firstTap && !$isShiftMalam) {
            return null; // Tidak ada entry sama sekali untuk targetDate, dan bukan kasus shift malam khusus.
        }

        $jamMasuk = $firstTap['time'] ?? null;
        $jamMasukDt = $firstTap['full'] ?? null;

        if (!$jamMasuk && !$isShiftMalam) {
            return null;
        }

        $jamPulang = null;
        if ($isShiftMalam) {
            // Night Shift: look for checkout scan on nextDate in the OUT window (00:00:00 - 12:00:00).
            // FIX #3: tambahkan guard eksplisit agar checkout selalu > jam masuk (melindungi dari
            // data corrupt / kasus targetDate == nextDate).
            $checkoutScan = null;
            foreach ($filtered as $entry) {
                if ($entry['date'] === $nextDate) {
                    $time = $entry['time'];
                    $afterMasuk = !$jamMasukDt || $entry['full']->gt($jamMasukDt);
                    if ($time >= '00:00:00' && $time <= '12:00:00' && $afterMasuk) {
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

        // Jika shift malam tanpa jam masuk terdeteksi (FIX #1) dan checkout pun tidak ketemu,
        // baru dianggap benar-benar tidak ada data.
        if (!$jamMasuk && !$jamPulang) {
            return null;
        }

        return [
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
        ];
    }
}
