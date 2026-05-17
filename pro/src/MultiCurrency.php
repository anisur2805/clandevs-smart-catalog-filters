<?php
/**
 * Multi-currency support for price filter.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiCurrency {
	public function register_hooks(): void {
		add_filter( 'cscf_price_bounds', array( $this, 'convert_price_bounds' ), 10, 2 );
	}

	public function convert_price_bounds( array $bounds, string $context ): array {
		if ( ! function_exists( 'get_woocommerce_currencies' ) ) {
			return $bounds;
		}
		// If a currency switcher plugin is active, convert prices.
		$currency = get_woocommerce_currency();
		if ( 'USD' === $currency ) {
			return $bounds;
		}
		// Hook into popular multi-currency plugins.
		if ( function_exists( 'wmc_get_price' ) ) {
			$bounds['min'] = (float) wmc_get_price( $bounds['min'], $currency );
			$bounds['max'] = (float) wmc_get_price( $bounds['max'], $currency );
		}
		return $bounds;
	}
}
