<?php
/**
 * Tests for the date range quick presets (issue #50).
 */

declare(strict_types=1);

use ClandevsSmartCatalogFiltersPro\DateRangeFilter;
use PHPUnit\Framework\TestCase;

final class DateRangePresetTest extends TestCase
{
    public function test_last_7_days_is_an_inclusive_seven_day_window(): void
    {
        $range = DateRangeFilter::resolve_preset('last_7', '2026-03-15');

        $this->assertSame('2026-03-09', $range['from']);
        $this->assertSame('2026-03-15', $range['to']);
    }

    public function test_last_30_days_is_an_inclusive_thirty_day_window(): void
    {
        $range = DateRangeFilter::resolve_preset('last_30', '2026-03-15');

        $this->assertSame('2026-02-14', $range['from']);
        $this->assertSame('2026-03-15', $range['to']);
    }

    public function test_last_90_days_is_an_inclusive_ninety_day_window(): void
    {
        $range = DateRangeFilter::resolve_preset('last_90', '2026-03-15');

        $this->assertSame('2025-12-16', $range['from']);
        $this->assertSame('2026-03-15', $range['to']);
    }

    public function test_this_year_starts_on_january_first(): void
    {
        $range = DateRangeFilter::resolve_preset('this_year', '2026-03-15');

        $this->assertSame('2026-01-01', $range['from']);
        $this->assertSame('2026-03-15', $range['to']);
    }

    public function test_leap_day_is_handled(): void
    {
        $range = DateRangeFilter::resolve_preset('last_7', '2024-03-01');

        $this->assertSame('2024-02-24', $range['from']);
    }

    public function test_custom_and_unknown_presets_resolve_to_no_range(): void
    {
        foreach (array('custom', '', 'bogus') as $preset) {
            $range = DateRangeFilter::resolve_preset($preset, '2026-03-15');

            $this->assertSame('', $range['from'], $preset);
            $this->assertSame('', $range['to'], $preset);
        }
    }

    public function test_malformed_reference_date_resolves_to_no_range(): void
    {
        $range = DateRangeFilter::resolve_preset('last_7', 'not-a-date');

        $this->assertSame('', $range['from']);
    }

    public function test_preset_list_covers_every_documented_option(): void
    {
        $presets = array_keys(DateRangeFilter::get_presets());

        foreach (array('last_7', 'last_30', 'last_90', 'this_year', 'custom') as $expected) {
            $this->assertContains($expected, $presets);
        }
    }
}
