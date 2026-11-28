<?php

namespace Tests\Unit;

use App\Reports\CsvWriter;
use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $input
     */
    private function range(array $input): string
    {
        $p = ReportPeriod::resolve($input, CarbonImmutable::parse('2026-02-15'));

        return $p->key.' '.$p->from->toDateString().'..'.$p->to->toDateString();
    }

    public function test_named_periods_are_whole_calendar_periods_relative_to_today(): void
    {
        $this->assertSame('this_month 2026-02-01..2026-02-28', $this->range([]));
        $this->assertSame('last_month 2026-01-01..2026-01-31', $this->range(['period' => 'last_month']));
        $this->assertSame('this_quarter 2026-01-01..2026-03-31', $this->range(['period' => 'this_quarter']));
        $this->assertSame('last_quarter 2025-10-01..2025-12-31', $this->range(['period' => 'last_quarter']));
        $this->assertSame('this_year 2026-01-01..2026-12-31', $this->range(['period' => 'this_year']));
        $this->assertSame('last_year 2025-01-01..2025-12-31', $this->range(['period' => 'last_year']));
    }

    public function test_the_month_before_march_does_not_overflow(): void
    {
        $p = ReportPeriod::resolve(['period' => 'last_month'], CarbonImmutable::parse('2026-03-31'));

        $this->assertSame('2026-02-01..2026-02-28', $p->from->toDateString().'..'.$p->to->toDateString());
    }

    public function test_a_custom_range_is_kept_ordered_and_garbage_falls_back_to_this_month(): void
    {
        $this->assertSame('custom 2026-01-10..2026-01-20', $this->range(['period' => 'custom', 'from' => '2026-01-10', 'to' => '2026-01-20']));
        $this->assertSame('custom 2026-01-10..2026-01-20', $this->range(['period' => 'custom', 'from' => '2026-01-20', 'to' => '2026-01-10']));
        $this->assertSame('this_month 2026-02-01..2026-02-28', $this->range(['period' => 'custom', 'from' => '2026-01-10']));
        $this->assertSame('this_month 2026-02-01..2026-02-28', $this->range(['period' => 'custom', 'from' => 'x', 'to' => 'y']));
        $this->assertSame('this_month 2026-02-01..2026-02-28', $this->range(['period' => 'forever']));
        $this->assertSame('this_month 2026-02-01..2026-02-28', $this->range(['period' => 'custom', 'from' => '2026-13-45', 'to' => '2026-02-30']));
    }

    public function test_the_csv_is_excel_ready_and_formulas_are_written_as_text(): void
    {
        $csv = CsvWriter::render(['A', 'B'], [['=HYPERLINK("x")', 'Kowalski; Jan'], ['-12.50', "multi\nline"], ['@cmd', null]]);

        $this->assertStringStartsWith("\xEF\xBB\xBFA;B\r\n", $csv);
        $this->assertStringContainsString("'=HYPERLINK(\"\"x\"\")", $csv);
        $this->assertStringContainsString('"Kowalski; Jan"', $csv);
        $this->assertStringContainsString("-12.50;\"multi\nline\"", $csv, 'A negative number is a number, not a formula.');
        $this->assertStringContainsString("'@cmd;", $csv);
    }
}
