<?php
/**
 * Tests for preset query sanitization (issue #49) and CSV escaping (issue #51).
 */

declare(strict_types=1);

use ClandevsSmartCatalogFiltersPro\AdvancedAnalytics;
use ClandevsSmartCatalogFiltersPro\Presets;
use PHPUnit\Framework\TestCase;

final class PresetQueryTest extends TestCase
{
    /**
     * Call a private method under test.
     *
     * @param object $object Instance.
     * @param string $method Method name.
     * @param array  $args   Arguments.
     * @return mixed
     */
    private function callPrivate($object, string $method, array $args = [])
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    public function test_recognised_filter_parameters_are_kept(): void
    {
        $result = $this->callPrivate(new Presets(), 'sanitize_filter_query', ['cscf_cat=shoes&min_price=50']);

        parse_str($result, $parsed);

        $this->assertSame('shoes', $parsed['cscf_cat']);
        $this->assertSame('50', $parsed['min_price']);
    }

    public function test_unrecognised_parameters_are_discarded(): void
    {
        // A preset must not be able to smuggle arbitrary query args onto the shop URL.
        $result = $this->callPrivate(new Presets(), 'sanitize_filter_query', ['cscf_cat=shoes&redirect_to=evil.test&p=1']);

        parse_str($result, $parsed);

        $this->assertArrayHasKey('cscf_cat', $parsed);
        $this->assertArrayNotHasKey('redirect_to', $parsed);
        $this->assertArrayNotHasKey('p', $parsed);
    }

    public function test_attribute_parameters_are_allowed(): void
    {
        $result = $this->callPrivate(new Presets(), 'sanitize_filter_query', ['cscf_attr_pa_size[]=large']);

        parse_str($result, $parsed);

        $this->assertSame(array('large'), $parsed['cscf_attr_pa_size']);
    }

    public function test_leading_question_mark_is_tolerated(): void
    {
        $result = $this->callPrivate(new Presets(), 'sanitize_filter_query', ['?cscf_cat=shoes']);

        $this->assertStringContainsString('cscf_cat=shoes', $result);
    }

    public function test_query_with_nothing_recognisable_is_rejected(): void
    {
        $this->assertSame('', $this->callPrivate(new Presets(), 'sanitize_filter_query', ['foo=bar']));
        $this->assertSame('', $this->callPrivate(new Presets(), 'sanitize_filter_query', ['']));
    }

    public function test_non_scalar_input_is_rejected(): void
    {
        $this->assertSame('', $this->callPrivate(new Presets(), 'sanitize_filter_query', [array('a')]));
    }

    public function test_csv_fields_are_quoted_and_escaped(): void
    {
        $analytics = new AdvancedAnalytics();

        $this->assertSame('"plain"', $this->callPrivate($analytics, 'escape_csv_field', ['plain']));
        $this->assertSame('"say ""hi"""', $this->callPrivate($analytics, 'escape_csv_field', ['say "hi"']));
    }

    public function test_csv_formula_injection_is_neutralised(): void
    {
        $analytics = new AdvancedAnalytics();

        foreach (array('=cmd()', '+1', '-1', '@SUM(A1)') as $payload) {
            $escaped = $this->callPrivate($analytics, 'escape_csv_field', [$payload]);

            // A leading apostrophe stops spreadsheets evaluating the cell.
            $this->assertSame('"\'' . $payload . '"', $escaped, $payload);
        }
    }
}
