<?php
/**
 * Unit tests for analytics resilience against malformed request input.
 *
 * Covers the three hardening fixes: non-scalar items in slug-list keys,
 * non-array/numeric-typed filter data in flush batches, and non-finite
 * decimal prices.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ClandevsSmartCatalogFilters\Analytics;

final class AnalyticsMalformedInputTest extends TestCase {
	/** @var Analytics */
	private $subject;

	protected function setUp(): void {
		parent::setUp();
		$_GET = array();

		$this->subject = new Analytics();
	}

	protected function tearDown(): void {
		$_GET = array();
		$GLOBALS['__cscf_test_options'] = array();
		parent::tearDown();
	}

	public function test_nested_array_in_slug_list_key_is_dropped_not_cast(): void {
		$_GET['cscf_brand'] = array( 'x' => array( 'deep' ), 'ok' => 'acme' );

		$filters = $this->invokePrivate( 'get_request_filters' );

		self::assertArrayHasKey( 'brand', $filters );
		self::assertSame( array( 'acme' ), $filters['brand'] );
	}

	public function test_scalar_check_keeps_plain_list_values(): void {
		$_GET['cscf_color'] = array( 'Red', '', 'blue' );

		$filters = $this->invokePrivate( 'get_request_filters' );

		self::assertSame( array( 'red', 'blue' ), $filters['color'] );
	}

	public function test_flush_skips_filter_type_with_non_array_values(): void {
		$this->subject->flush_analytics_buffer(
			array(
				array(
					'filters'   => array( 'brand' => 'NOT_A_LIST' ),
					'timestamp' => '2026-10-24 00:00:00',
				),
			)
		);

		$stats = get_option( 'cscf_analytics_data' );

		self::assertArrayNotHasKey( 'brand', $stats['filters'] );
	}

	public function test_flush_skips_numeric_filter_type_keys(): void {
		$this->subject->flush_analytics_buffer(
			array(
				array(
					'filters'   => array( 999 => array( 'mountains' ) ),
					'timestamp' => '2026-10-24 00:00:00',
				),
			)
		);

		$stats = get_option( 'cscf_analytics_data' );

		self::assertArrayNotHasKey( '999', $stats['filters'] );
		self::assertArrayNotHasKey( 999, $stats['filters'] );
	}

	public function test_flush_skips_non_scalar_values_inside_lists(): void {
		$this->subject->flush_analytics_buffer(
			array(
				array(
					'filters'   => array( 'brand' => array( 'acme', array( 'nested' ) ) ),
					'timestamp' => '2026-10-24 00:00:00',
				),
			)
		);

		$stats = get_option( 'cscf_analytics_data' );

		self::assertSame( array( 'acme' => 1 ), $stats['filters']['brand'] );
	}

	public function test_infinite_price_is_rejected(): void {
		$_GET['min_price'] = '1e999';

		$filters = $this->invokePrivate( 'get_request_filters' );

		self::assertArrayNotHasKey( 'min_price', $filters );
	}

	public function test_finite_price_still_parses(): void {
		$_GET['min_price'] = '12.50';

		$filters = $this->invokePrivate( 'get_request_filters' );

		self::assertSame( array( '12.5' ), $filters['min_price'] );
	}

	/**
	 * @param string $method
	 * @param mixed  ...$args
	 * @return mixed
	 */
	private function invokePrivate( string $method, ...$args ) {
		$reflection = new ReflectionMethod( $this->subject, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $this->subject, $args );
	}
}
