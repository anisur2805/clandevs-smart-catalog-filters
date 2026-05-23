<?php
/**
 * Unit tests for analytics request normalization.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ClandevsSmartCatalogFilters\Analytics;

final class AnalyticsRequestTest extends TestCase {
	/** @var Analytics */
	private $subject;

	protected function setUp(): void {
		parent::setUp();
		$_GET = array();

		$this->subject = new Analytics();
	}

	protected function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	public function test_has_filter_parameters_detects_dynamic_attribute_key(): void {
		$_GET['cscf_attr_pa_size'] = array( 'small' );

		$has_filters = $this->invokePrivate( 'has_filter_parameters' );

		self::assertTrue( $has_filters );
	}

	public function test_request_filters_include_dynamic_attribute_values(): void {
		$_GET['cscf_attr_pa_size'] = array( 'Large', 'large', '' );

		$filters = $this->invokePrivate( 'get_request_filters' );

		self::assertArrayHasKey( 'attr_pa_size', $filters );
		self::assertSame( array( 'large' ), $filters['attr_pa_size'] );
	}

	public function test_translated_filter_type_humanizes_attribute_labels(): void {
		$label = $this->invokePrivate( 'get_translated_filter_type_label', 'attr_pa_size' );

		self::assertSame( 'Attribute: Size', $label );
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
