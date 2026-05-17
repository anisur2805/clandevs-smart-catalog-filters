<?php
/**
 * Feature availability helper.
 *
 * All features are fully available in the WordPress.org version.
 * This class is retained for backward compatibility with any code
 * that references License::can() or License::get_upgrade_url().
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All features are always available.
 */
final class License {

	/**
	 * Check whether a feature is available. Always returns true.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function can( string $feature ): bool {
		return true;
	}

	/**
	 * Get upgrade URL. Returns empty string (no premium version).
	 *
	 * @return string
	 */
	public static function get_upgrade_url(): string {
		return '';
	}
}
