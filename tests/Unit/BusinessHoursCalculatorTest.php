<?php

namespace Tests\Unit;

use App\Services\Common\BusinessHoursCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BusinessHoursCalculatorTest extends TestCase
{
    protected BusinessHoursCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard Bangladesh business week: Sun-Thu, 09:00 - 18:00, Asia/Dhaka
        $this->calculator = new BusinessHoursCalculator(
            tz: 'Asia/Dhaka',
            days: [0, 1, 2, 3, 4], // Sun, Mon, Tue, Wed, Thu
            start: '09:00',
            end: '18:00',
            holidays: ['2026-10-19'] // Monday 2026-10-19 as test holiday
        );
    }

    public function test_simple_addition_within_same_business_day(): void
    {
        // Sunday 10:00 Asia/Dhaka + 120 min = Sunday 12:00 Asia/Dhaka (06:00 UTC)
        $start = CarbonImmutable::parse('2026-10-18 10:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 120);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-18 12:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-18 06:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_thursday_afternoon_rolls_over_weekend_to_sunday_morning(): void
    {
        // Thursday 2026-10-15 17:00 Asia/Dhaka + 120 min:
        // Consumes 60 min to Thursday 18:00 (closing).
        // Friday (16th) & Saturday (17th) are skipped.
        // Sunday (18th) opens at 09:00; consumes remaining 60 min -> Sunday 10:00 Asia/Dhaka (04:00 UTC).
        $start = CarbonImmutable::parse('2026-10-15 17:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 120);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-18 10:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-18 04:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_timestamp_at_closing_behaves_like_next_business_day_opening(): void
    {
        // Wednesday 2026-10-14 18:00 (closing) + 60 min -> advances to Thursday 09:00 + 60 min = Thursday 10:00 Asia/Dhaka
        $start = CarbonImmutable::parse('2026-10-14 18:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 60);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-15 10:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
    }

    public function test_timestamp_after_closing_behaves_like_next_business_day_opening(): void
    {
        // Wednesday 2026-10-14 20:30 (after hours) + 60 min -> Thursday 09:00 + 60 min = Thursday 10:00 Asia/Dhaka
        $start = CarbonImmutable::parse('2026-10-14 20:30:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 60);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-15 10:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
    }

    public function test_weekend_timestamp_advances_to_sunday_opening(): void
    {
        // Friday 2026-10-16 11:00 (weekend) + 60 min -> Sunday 09:00 + 60 min = Sunday 10:00 Asia/Dhaka
        $start = CarbonImmutable::parse('2026-10-16 11:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 60);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-18 10:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
    }

    public function test_early_morning_before_opening_advances_to_opening(): void
    {
        // Sunday 2026-10-18 08:00 (before opening) + 30 min -> Sunday 09:00 + 30 min = Sunday 09:30 Asia/Dhaka
        $start = CarbonImmutable::parse('2026-10-18 08:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 30);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-18 09:30:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
    }

    public function test_configured_holiday_is_skipped(): void
    {
        // 2026-10-19 (Monday) is configured as a holiday.
        // Starting Sunday 2026-10-18 at 17:00 + 120 min:
        // Consumes 60 min on Sunday to 18:00.
        // Monday 2026-10-19 is a holiday -> skipped!
        // Next business day is Tuesday 2026-10-20 at 09:00.
        // Consumes remaining 60 min -> Tuesday 10:00 Asia/Dhaka (04:00 UTC).
        $start = CarbonImmutable::parse('2026-10-18 17:00:00', 'Asia/Dhaka');
        $result = $this->calculator->addBusinessMinutes($start, 120);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-20 10:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
    }

    public function test_multi_day_span_for_low_priority_48_hours(): void
    {
        // Low priority = 48 business hours = 2880 business minutes
        // Daily business minutes = 9 hours * 60 = 540 min/day
        // 2880 / 540 = 5 full days (2700 min) + 180 min (3 hours)
        // Without holidays (using separate calculator instance):
        $calculator = new BusinessHoursCalculator(
            tz: 'Asia/Dhaka',
            days: [0, 1, 2, 3, 4],
            start: '09:00',
            end: '18:00',
            holidays: []
        );

        // Start Sunday 2026-10-18 09:00:
        // Day 1: Sun 10-18 (540 min, 2340 left)
        // Day 2: Mon 10-19 (540 min, 1800 left)
        // Day 3: Tue 10-20 (540 min, 1260 left)
        // Day 4: Wed 10-21 (540 min, 720 left)
        // Day 5: Thu 10-22 (540 min, 180 left)
        // Weekend: Fri 10-23 & Sat 10-24
        // Day 6: Sun 10-25 09:00 + 180 min = Sun 12:00 Asia/Dhaka (06:00 UTC)
        $start = CarbonImmutable::parse('2026-10-18 09:00:00', 'Asia/Dhaka');
        $result = $calculator->addBusinessMinutes($start, 2880);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-25 12:00:00', $result->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 06:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_input_in_utc_is_correctly_converted_and_calculated(): void
    {
        // 2026-10-15 11:00:00 UTC is 17:00:00 Asia/Dhaka (Thursday)
        $startUtc = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');
        $result = $this->calculator->addBusinessMinutes($startUtc, 120);

        $this->assertSame('UTC', $result->timezoneName);
        $this->assertSame('2026-10-18 04:00:00', $result->format('Y-m-d H:i:s'));
    }
}
