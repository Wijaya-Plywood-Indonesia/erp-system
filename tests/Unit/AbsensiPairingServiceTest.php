<?php

namespace Tests\Unit;

use App\Services\AbsensiPairingService;
use Carbon\Carbon;
use Tests\TestCase;

class AbsensiPairingServiceTest extends TestCase
{
    private AbsensiPairingService $pairingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pairingService = new AbsensiPairingService();
    }

    public function test_day_shift_normal_case(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '07:30:00',
                'full' => Carbon::parse("{$targetDate} 07:30:00"),
            ],
            [
                'date' => $targetDate,
                'time' => '16:30:00',
                'full' => Carbon::parse("{$targetDate} 16:30:00"),
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('07:30:00', $paired['jam_masuk']);
        $this->assertSame('16:30:00', $paired['jam_pulang']);
    }

    public function test_night_shift_crossing_midnight(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '17:32:00',
                'full' => Carbon::parse("{$targetDate} 17:32:00"),
            ],
            [
                'date' => $nextDate,
                'time' => '05:40:00',
                'full' => Carbon::parse("{$nextDate} 05:40:00"),
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('17:32:00', $paired['jam_masuk']);
        $this->assertSame('05:40:00', $paired['jam_pulang']);
    }

    public function test_missing_checkout(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '07:30:00',
                'full' => Carbon::parse("{$targetDate} 07:30:00"),
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('07:30:00', $paired['jam_masuk']);
        $this->assertNull($paired['jam_pulang']);
    }

    public function test_duplicate_scans(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '07:30:00',
                'full' => Carbon::parse("{$targetDate} 07:30:00"),
            ],
            [
                'date' => $targetDate,
                'time' => '07:32:00',
                'full' => Carbon::parse("{$targetDate} 07:32:00"), // Duplicate within 5 mins, should be ignored
            ],
            [
                'date' => $targetDate,
                'time' => '16:30:00',
                'full' => Carbon::parse("{$targetDate} 16:30:00"),
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('07:30:00', $paired['jam_masuk']);
        $this->assertSame('16:30:00', $paired['jam_pulang']);
    }

    public function test_multiple_scans_during_shift(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '07:30:00',
                'full' => Carbon::parse("{$targetDate} 07:30:00"),
            ],
            [
                'date' => $targetDate,
                'time' => '12:00:00',
                'full' => Carbon::parse("{$targetDate} 12:00:00"), // Break, ignored for checkout because last scan is preferred
            ],
            [
                'date' => $targetDate,
                'time' => '13:00:00',
                'full' => Carbon::parse("{$targetDate} 13:00:00"), // Return, ignored for checkout because last scan is preferred
            ],
            [
                'date' => $targetDate,
                'time' => '16:30:00',
                'full' => Carbon::parse("{$targetDate} 16:30:00"), // Actual checkout
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('07:30:00', $paired['jam_masuk']);
        $this->assertSame('16:30:00', $paired['jam_pulang']);
    }

    public function test_employee_still_working_during_active_night_shift(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '18:00:00',
                'full' => Carbon::parse("{$targetDate} 18:00:00"),
            ],
            // Employee is still working, no checkout scan has occurred on nextDate yet.
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('18:00:00', $paired['jam_masuk']);
        $this->assertNull($paired['jam_pulang']);
    }

    public function test_continuous_night_shifts(): void
    {
        $monDate = '2024-07-08';
        $tueDate = '2024-07-09';
        $wedDate = '2024-07-10';

        // 1. Process Monday's Night Shift (no previous checkout)
        $mondayEntries = [
            [
                'date' => $monDate,
                'time' => '18:00:00',
                'full' => Carbon::parse("{$monDate} 18:00:00"),
            ],
            [
                'date' => $tueDate,
                'time' => '06:00:00',
                'full' => Carbon::parse("{$tueDate} 06:00:00"),
            ],
        ];

        $monPaired = $this->pairingService->pairEmployeeLogs($mondayEntries, $monDate, $tueDate, null);
        $this->assertNotNull($monPaired);
        $this->assertSame('18:00:00', $monPaired['jam_masuk']);
        $this->assertSame('06:00:00', $monPaired['jam_pulang']);

        // 2. Process Tuesday's Night Shift
        // Tuesday morning 06:00 was Monday's checkout.
        // Tuesday evening 18:00 is Tuesday's check-in.
        // Wednesday morning 06:00 is Tuesday's checkout.
        $tuesdayEntries = [
            [
                'date' => $tueDate,
                'time' => '06:00:00',
                'full' => Carbon::parse("{$tueDate} 06:00:00"), // Already consumed as Mon checkout
            ],
            [
                'date' => $tueDate,
                'time' => '18:00:00',
                'full' => Carbon::parse("{$tueDate} 18:00:00"), // Tue night shift IN
            ],
            [
                'date' => $wedDate,
                'time' => '06:00:00',
                'full' => Carbon::parse("{$wedDate} 06:00:00"), // Tue night shift OUT
            ],
        ];

        $prevCheckout = Carbon::parse("{$tueDate} 06:00:00");
        $tuePaired = $this->pairingService->pairEmployeeLogs($tuesdayEntries, $tueDate, $wedDate, $prevCheckout);

        $this->assertNotNull($tuePaired);
        $this->assertSame('18:00:00', $tuePaired['jam_masuk']);
        $this->assertSame('06:00:00', $tuePaired['jam_pulang']);
    }

    public function test_night_shift_late_in_early_out(): void
    {
        $targetDate = '2024-07-08';
        $nextDate = '2024-07-09';

        $entries = [
            [
                'date' => $targetDate,
                'time' => '20:30:00', // Arrives late
                'full' => Carbon::parse("{$targetDate} 20:30:00"),
            ],
            [
                'date' => $nextDate,
                'time' => '02:30:00', // Leaves early
                'full' => Carbon::parse("{$nextDate} 02:30:00"),
            ],
        ];

        $paired = $this->pairingService->pairEmployeeLogs($entries, $targetDate, $nextDate);

        $this->assertNotNull($paired);
        $this->assertSame('20:30:00', $paired['jam_masuk']);
        $this->assertSame('02:30:00', $paired['jam_pulang']);
    }
}
