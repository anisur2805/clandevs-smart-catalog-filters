<?php
/**
 * Unit tests for query-clause and request-state normalization.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WooFilters\ShopFilters;

final class ShopFiltersQueryTest extends TestCase {
    /** @var ShopFilters */
    private $subject;

    protected function setUp(): void {
        parent::setUp();
        $_GET = array();

        $this->subject = new ShopFilters('https://example.test/', 'test');
        $this->setPrivateProperty('brand_taxonomy', 'pa_brand');
        $this->setPrivateProperty('color_taxonomy', 'pa_color');
        $this->invokePrivate('bootstrap_taxonomies');
    }

    protected function tearDown(): void {
        $_GET = array();
        parent::tearDown();
    }

    public function test_multiselect_mode_defaults_to_or(): void {
        $mode = $this->invokePrivate('get_request_multiselect_mode');

        self::assertSame('or', $mode);
    }

    public function test_multiselect_mode_accepts_and(): void {
        $_GET['wf_logic'] = 'and';

        $mode = $this->invokePrivate('get_request_multiselect_mode');

        self::assertSame('and', $mode);
    }

    public function test_slug_list_is_normalized_and_deduplicated(): void {
        $_GET['wf_brand'] = array('Apple', 'apple', 'ASUS', '');

        $list = $this->invokePrivate('get_request_slug_list', 'wf_brand');

        self::assertSame(array('apple', 'asus'), $list);
    }

    public function test_request_filter_clauses_use_and_operator_when_selected(): void {
        $_GET['wf_logic'] = 'and';
        $_GET['wf_brand'] = array('apple', 'asus');
        $_GET['wf_color'] = array('red', 'blue');

        $clauses = $this->invokePrivate('get_request_filter_clauses');
        $tax = $clauses['tax'];

        self::assertCount(2, $tax);
        self::assertSame('AND', $tax[0]['operator']);
        self::assertSame('AND', $tax[1]['operator']);
    }

    public function test_request_filter_clauses_include_dynamic_attribute_key(): void {
        $_GET['wf_attr_pa_size'] = array('small', 'medium');

        $clauses = $this->invokePrivate('get_request_filter_clauses');
        $tax = $clauses['tax'];

        self::assertNotEmpty($tax);
        $found = false;
        foreach ($tax as $clause) {
            if (isset($clause['taxonomy']) && 'pa_size' === $clause['taxonomy']) {
                $found = true;
                self::assertSame(array('small', 'medium'), $clause['terms']);
                self::assertSame('IN', $clause['operator']);
            }
        }

        self::assertTrue($found);
    }

    public function test_current_query_args_strips_nonce_and_empty_values(): void {
        $_GET['wf_nonce'] = 'abc';
        $_GET['wf_brand'] = array('apple', '');
        $_GET['paged'] = '2';
        $_GET['_ignored'] = '1';

        $args = $this->invokePrivate('get_current_query_args');

        self::assertArrayHasKey('wf_brand', $args);
        self::assertSame(array('apple'), $args['wf_brand']);
        self::assertArrayHasKey('paged', $args);
        self::assertArrayNotHasKey('wf_nonce', $args);
        self::assertArrayNotHasKey('_ignored', $args);
    }

    public function test_on_sale_filter_uses_post_in_ids(): void {
        $_GET['wf_on_sale'] = '1';

        $clauses = $this->invokePrivate('get_request_filter_clauses');

        self::assertArrayHasKey('post_in', $clauses);
        self::assertSame(array(10, 20), $clauses['post_in']);
    }

    /**
     * @param string $method
     * @param mixed  ...$args
     * @return mixed
     */
    private function invokePrivate(string $method, ...$args) {
        $reflection = new ReflectionMethod($this->subject, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->subject, $args);
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @return void
     */
    private function setPrivateProperty(string $name, $value): void {
        $reflection = new ReflectionProperty($this->subject, $name);
        $reflection->setAccessible(true);
        $reflection->setValue($this->subject, $value);
    }
}
