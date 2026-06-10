<?php

namespace Tests\Unit;

use App\Services\AbsensiParsingService;
use Tests\TestCase;

class AbsensiParsingServiceTest extends TestCase
{
    public function test_parse_content_preserves_five_digit_employee_code_with_leading_zero(): void
    {
        $service = new AbsensiParsingService();
        $content = "1 100 09199 2026-06-10 07:05:00";

        $result = $service->parseContent($content, 'absensi.dat', ['2026-06-10']);

        $this->assertArrayHasKey('09199', $result['raw_logs']);
        $this->assertSame('07:05:00', $result['raw_logs']['09199'][0]['time']);
    }

    public function test_parse_content_supports_tab_separated_txt_and_normalizes_time(): void
    {
        $service = new AbsensiParsingService();
        $content = "1\t100\t01234\t2026/06/10\t17:15";

        $result = $service->parseContent($content, 'absensi.txt', ['2026-06-10']);

        $this->assertArrayHasKey('01234', $result['raw_logs']);
        $this->assertSame('17:15:00', $result['raw_logs']['01234'][0]['time']);
    }

    public function test_parse_content_falls_back_when_time_not_after_date_index(): void
    {
        $service = new AbsensiParsingService();
        $content = "1 100 09999 2026-06-10 IN 18:00:00";

        $result = $service->parseContent($content, 'absensi.dat', ['2026-06-10']);

        $this->assertArrayHasKey('09999', $result['raw_logs']);
        $this->assertSame('18:00:00', $result['raw_logs']['09999'][0]['time']);
    }

    public function test_parse_content_counts_invalid_lines_as_skipped(): void
    {
        $service = new AbsensiParsingService();
        $content = "1 100 09999 2026-06-10\n2 100 09999 2026-06-10 08:00:00";

        $result = $service->parseContent($content, 'absensi.dat', ['2026-06-10']);

        $this->assertSame(1, $result['skipped_lines']);
        $this->assertArrayHasKey('09999', $result['raw_logs']);
    }
}
