<?php
/**
 * Tests for analytics period comparison (issue #51).
 */

declare(strict_types=1);

use ClandevsSmartCatalogFiltersPro\AdvancedAnalytics;
use PHPUnit\Framework\TestCase;

final class AnalyticsPeriodTest extends TestCase
{
    public function test_previous_period_is_the_same_length_and_ends_the_day_before(): void
    {
        // 7-day window: 8th-14th, so the previous window is 1st-7th.
        $previous = AdvancedAnalytics::get_previous_period('2026-03-08', '2026-03-14');

        $this->assertSame('2026-03-01', $previous['from']);
        $this->assertSame('2026-03-07', $previous['to']);
    }

    public function test_single_day_period_compares_against_the_day_before(): void
    {
        $previous = AdvancedAnalytics::get_previous_period('2026-03-08', '2026-03-08');

        $this->assertSame('2026-03-07', $previous['from']);
        $this->assertSame('2026-03-07', $previous['to']);
    }

    public function test_previous_period_crosses_a_month_boundary(): void
    {
        // March has 31 days, so the previous window is the 31 days ending Feb 28:
        // Jan 29-31 (3 days) plus all 28 days of February.
        $previous = AdvancedAnalytics::get_previous_period('2026-03-01', '2026-03-31');

        $this->assertSame('2026-01-29', $previous['from']);
        $this->assertSame('2026-02-28', $previous['to']);
    }

    public function test_previous_period_always_matches_the_current_length(): void
    {
        $cases = array(
            array('2026-03-01', '2026-03-31'),
            array('2026-03-08', '2026-03-14'),
            array('2024-02-01', '2024-02-29'),
            array('2026-01-01', '2026-12-31'),
        );

        foreach ($cases as list($from, $to)) {
            $previous = AdvancedAnalytics::get_previous_period($from, $to);

            $current_length  = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
            $previous_length = (new DateTimeImmutable($previous['from']))->diff(new DateTimeImmutable($previous['to']))->days + 1;

            $this->assertSame($current_length, $previous_length, $from . ' to ' . $to);
            // The previous window must end the day before the current one starts.
            $this->assertSame(
                (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d'),
                $previous['to']
            );
        }
    }

    public function test_inverted_range_yields_no_previous_period(): void
    {
        $previous = AdvancedAnalytics::get_previous_period('2026-03-31', '2026-03-01');

        $this->assertSame('', $previous['from']);
    }

    public function test_percentage_change_reports_growth_and_decline(): void
    {
        $this->assertSame(100.0, AdvancedAnalytics::get_percentage_change(200, 100));
        $this->assertSame(-50.0, AdvancedAnalytics::get_percentage_change(50, 100));
        $this->assertSame(0.0, AdvancedAnalytics::get_percentage_change(100, 100));
    }

    public function test_percentage_change_is_null_without_a_baseline(): void
    {
        // Avoids dividing by zero and reporting a meaningless "infinite" rise.
        $this->assertNull(AdvancedAnalytics::get_percentage_change(10, 0));
    }

    public function test_range_presets_are_offered(): void
    {
        $presets = array_keys(AdvancedAnalytics::get_range_presets());

        foreach (array('last_7', 'last_30', 'last_90', 'custom') as $expected) {
            $this->assertContains($expected, $presets);
        }
    }
}
