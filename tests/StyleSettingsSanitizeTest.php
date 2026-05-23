<?php
/**
 * Unit tests for StyleSettings sanitization.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ClandevsSmartCatalogFilters\StyleSettings;

final class StyleSettingsSanitizeTest extends TestCase {
	/** @var StyleSettings */
	private $subject;

	protected function setUp(): void {
		parent::setUp();
		$this->subject = new StyleSettings();
	}

	public function test_custom_css_is_rejected(): void {
		$raw = array(
			'custom_css' => '.wf-sidebar { color: red; } </style><script>alert(1)</script>',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertArrayNotHasKey( 'custom_css', $result );
	}

	public function test_font_family_strips_quotes(): void {
		$raw = array(
			'font_family' => "'; }body{background:red}/*",
		);

		$result = $this->subject->sanitize_settings( $raw );

		if ( isset( $result['font_family'] ) ) {
			self::assertStringNotContainsString( "'", $result['font_family'] );
			self::assertStringNotContainsString( '{', $result['font_family'] );
			self::assertStringNotContainsString( '}', $result['font_family'] );
		}
	}

	public function test_font_family_allows_valid_values(): void {
		$raw = array(
			'font_family' => 'Inter, sans-serif',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertSame( 'Inter, sans-serif', $result['font_family'] );
	}

	public function test_sanitize_hex_color_rejects_invalid(): void {
		$raw = array(
			'accent_color' => 'not-a-color',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertArrayNotHasKey( 'accent_color', $result );
	}

	public function test_sanitize_hex_color_accepts_valid(): void {
		$raw = array(
			'accent_color' => '#ff5500',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertSame( '#ff5500', $result['accent_color'] );
	}

	public function test_numeric_values_respect_range(): void {
		$raw = array(
			'font_size' => '99',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertArrayNotHasKey( 'font_size', $result );
	}

	public function test_numeric_values_within_range(): void {
		$raw = array(
			'font_size' => '16',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertSame( '16', $result['font_size'] );
	}

	public function test_numeric_values_accept_zero_minimum(): void {
		$raw = array(
			'sidebar_radius' => '0',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertSame( '0', $result['sidebar_radius'] );
	}

	public function test_numeric_values_accept_high_values(): void {
		$raw = array(
			'button_radius' => '50',
		);

		$result = $this->subject->sanitize_settings( $raw );

		self::assertSame( '50', $result['button_radius'] );
	}
}
