<?php
/**
 * Premium license gate.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves whether premium code may run.
 */
final class Gate {

	/**
	 * Cached gate decision for the current request.
	 *
	 * @var bool|null
	 */
	private static $can_use = null;

	/**
	 * Check whether premium features are licensed.
	 *
	 * Falls back to allowing premium code when the Freemius SDK is
	 * unavailable, so a bundled build without the SDK still functions.
	 *
	 * @return bool
	 */
	public static function can_use_pro(): bool {
		if ( null !== self::$can_use ) {
			return self::$can_use;
		}

		$can_use = true;

		if ( function_exists( 'cscfp_fs' ) ) {
			$freemius = cscfp_fs();

			if ( is_object( $freemius ) && method_exists( $freemius, 'can_use_premium_code' ) ) {
				$can_use = (bool) $freemius->can_use_premium_code();
			}
		}

		/**
		 * Filters whether Pro features may run.
		 *
		 * @param bool $can_use Whether premium code is licensed.
		 */
		self::$can_use = (bool) apply_filters( 'cscfp_can_use_pro', $can_use );

		return self::$can_use;
	}

	/**
	 * Reset the cached decision. Intended for tests.
	 *
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$can_use = null;
	}
}
