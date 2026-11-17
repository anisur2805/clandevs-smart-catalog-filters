<?php
/**
 * Tests for SEO-friendly filter URLs (issue #60).
 */

declare(strict_types=1);

use ClandevsSmartCatalogFiltersPro\SEOFriendlyURLs;
use PHPUnit\Framework\TestCase;

final class SeoUrlTest extends TestCase
{
    /**
     * Subject under test.
     *
     * @var SEOFriendlyURLs
     */
    private $urls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urls = new SEOFriendlyURLs();
    }

    public function test_documented_path_maps_to_filter_parameters(): void
    {
        // The exact example from the issue.
        $params = $this->urls->parse_path('category/shoes/price/50-200');

        $this->assertSame('shoes', $params['cscf_cat']);
        $this->assertSame('50', $params['min_price']);
        $this->assertSame('200', $params['max_price']);
    }

    public function test_multiple_values_are_comma_separated(): void
    {
        $params = $this->urls->parse_path('color/red,blue');

        $this->assertSame(array('red', 'blue'), $params['cscf_color']);
    }

    public function test_attribute_names_resolve_to_their_taxonomy(): void
    {
        $params = $this->urls->parse_path('size/large');

        $this->assertSame(array('large'), $params['cscf_attr_pa_size']);
    }

    public function test_stock_and_sale_flags_are_recognised(): void
    {
        $params = $this->urls->parse_path('stock/in/sale/yes');

        $this->assertSame('1', $params['cscf_in_stock']);
        $this->assertSame('1', $params['cscf_on_sale']);
    }

    public function test_decimal_price_bounds_survive_parsing(): void
    {
        // sanitize_title() would strip the decimal point.
        $params = $this->urls->parse_path('price/10.50-99.95');

        $this->assertSame('10.50', $params['min_price']);
        $this->assertSame('99.95', $params['max_price']);
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $params = $this->urls->parse_path('bogus/value/category/shoes');

        $this->assertArrayNotHasKey('bogus', $params);
        $this->assertSame('shoes', $params['cscf_cat']);
    }

    public function test_dangling_segment_without_a_value_is_ignored(): void
    {
        $params = $this->urls->parse_path('category/shoes/price');

        $this->assertSame('shoes', $params['cscf_cat']);
        $this->assertArrayNotHasKey('min_price', $params);
    }

    public function test_empty_path_yields_no_parameters(): void
    {
        $this->assertSame(array(), $this->urls->parse_path(''));
        $this->assertSame(array(), $this->urls->parse_path('///'));
    }

    public function test_build_url_produces_the_documented_shape(): void
    {
        $url = SEOFriendlyURLs::build_url(
            array(
                'cscf_cat'  => 'shoes',
                'min_price' => '50',
                'max_price' => '200',
            )
        );

        $this->assertSame('https://example.test/shop/category/shoes/price/50-200/', $url);
    }

    public function test_build_url_round_trips_through_parse_path(): void
    {
        $params = array(
            'cscf_cat'   => 'shoes',
            'cscf_color' => array('red', 'blue'),
        );

        $url  = SEOFriendlyURLs::build_url($params);
        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        $path = preg_replace('#^shop/#', '', $path);

        $parsed = $this->urls->parse_path((string) $path);

        $this->assertSame('shoes', $parsed['cscf_cat']);
        $this->assertSame(array('red', 'blue'), $parsed['cscf_color']);
    }

    public function test_build_url_without_filters_returns_the_shop_url(): void
    {
        $this->assertSame('https://example.test/shop/', SEOFriendlyURLs::build_url(array()));
    }
}
