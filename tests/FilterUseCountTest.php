<?php
/**
 * Unit tests for the filter-use counter wiring in the analytics flush path.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ClandevsSmartCatalogFilters\Analytics;
use ClandevsSmartCatalogFilters\ReviewPrompt;

final class FilterUseCountTest extends TestCase {
	/** @var Analytics */
	private $subject;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__cscf_test_options'] = array();

		$this->subject = new Analytics();
	}

	protected function tearDown(): void {
		$GLOBALS['__cscf_test_options'] = array();
		parent::tearDown();
	}

	public function test_flush_increments_use_count_once_per_batch(): void {
		$buffer = array(
			array( 'filters' => array( 'category' => array( 'shoes' ) ), 'timestamp' => '2026-10-24 00:00:00' ),
			array( 'filters' => array( 'brand' => array( 'acme' ) ), 'timestamp' => '2026-10-24 00:00:01' ),
			array( 'filters' => array( 'rating' => array( '4' ) ), 'timestamp' => '2026-10-24 00:00:02' ),
		);

		$this->subject->flush_analytics_buffer( $buffer );

		self::assertSame( 1, (int) get_option( 'cscf_filter_use_count', 0 ) );
		self::assertSame( 3, (int) get_option( 'cscf_analytics_data' )['total_events'] );
	}

	public function test_flush_reads_and_clears_buffered_transient(): void {
		set_transient(
			'cscf_analytics_buffer',
			array(
				array( 'filters' => array( 'category' => array( 'shoes' ) ), 'timestamp' => '2026-10-24 00:00:00' ),
			),
			300
		);

		$this->subject->flush_analytics_buffer();

		self::assertSame( 1, (int) get_option( 'cscf_filter_use_count', 0 ) );
		self::assertFalse( get_transient( 'cscf_analytics_buffer' ) );
	}

	public function test_flush_without_buffered_events_does_not_touch_use_count(): void {
		$this->subject->flush_analytics_buffer();

		self::assertFalse( get_option( 'cscf_filter_use_count', false ) );
	}

	public function test_record_filter_use_increments_existing_count(): void {
		update_option( 'cscf_filter_use_count', 41, false );

		ReviewPrompt::record_filter_use();
		ReviewPrompt::record_filter_use();

		self::assertSame( 43, (int) get_option( 'cscf_filter_use_count', 0 ) );
	}
}
