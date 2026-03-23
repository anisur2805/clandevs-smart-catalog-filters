<?php
/**
 * Central feature gating for free/pro tiers.
 *
 * @package AdvancedProductFilter
 */

namespace AdvancedProductFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether a given feature is available on the current plan.
 */
final class License {
	/**
	 * Features that require a paid plan.
	 *
	 * @var array<int, string>
	 */
	private static $pro_features = array(
		'brand_filter',
		'rating_filter',
		'color_filter',
		'custom_attributes',
		'analytics',
		'full_styling',
		'custom_css',
		'or_and_logic',
		'extra_skins',
	);

	/**
	 * Check whether the current site can use a feature.
	 *
	 * Free features always return true. Pro features return true only
	 * when the user has an active paid license via Freemius.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function can( string $feature ): bool {
		if ( ! in_array( $feature, self::$pro_features, true ) ) {
			return true;
		}

		return self::is_paying();
	}

	/**
	 * Whether the current site has an active paid plan.
	 *
	 * @return bool
	 */
	public static function is_paying(): bool {
		if ( ! function_exists( 'wfs_fs' ) ) {
			return false;
		}

		$fs = wfs_fs();

		return $fs->is_paying() || $fs->is_trial();
	}

	/**
	 * Get upgrade URL. Returns empty string when Freemius is unavailable.
	 *
	 * @return string
	 */
	public static function get_upgrade_url(): string {
		if ( ! function_exists( 'wfs_fs' ) ) {
			return '';
		}

		return wfs_fs()->get_upgrade_url();
	}

	/**
	 * Get the list of pro feature keys.
	 *
	 * @return array<int, string>
	 */
	public static function get_pro_features(): array {
		return self::$pro_features;
	}
}
