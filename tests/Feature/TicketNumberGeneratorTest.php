<?php

namespace Tests\Feature;

use App\Services\Common\TicketNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected TicketNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TicketNumberGenerator();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset mocked time after each test
        parent::tearDown();
    }

    /**
     * Test first number of a year is TKT-<year>-000001, then 000002.
     */
    public function test_first_number_of_year_starts_at_one_and_increments_sequentially(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');

        $first = $this->generator->next();
        $second = $this->generator->next();

        $this->assertSame('TKT-2026-000001', $first);
        $this->assertSame('TKT-2026-000002', $second);
    }

    /**
     * Test a new year restarts at 000001.
     */
    public function test_new_year_restarts_at_one(): void
    {
        // Year 2026
        Carbon::setTestNow('2026-12-31 23:59:59');
        $this->generator->next(); // TKT-2026-000001
        $this->generator->next(); // TKT-2026-000002

        // Jump to Year 2027
        Carbon::setTestNow('2027-01-01 00:00:00');
        $newYearFirst = $this->generator->next();

        $this->assertSame('TKT-2027-000001', $newYearFirst);
    }

    /**
     * Test 50 numbers generated in a loop are all strictly unique.
     */
    public function test_fifty_numbers_generated_in_loop_are_all_unique(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $numbers = [];
        for ($i = 0; $i < 50; $i++) {
            $numbers[] = $this->generator->next();
        }

        $this->assertCount(50, $numbers);
        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('TKT-2026-000050', end($numbers));
    }

    /**
     * Test that generated ticket numbers adhere to the required regex pattern.
     */
    public function test_number_strictly_matches_expected_regex_format(): void
    {
        Carbon::setTestNow('2026-07-20 14:30:00');

        $number = $this->generator->next();

        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}$/', $number);
    }
}
