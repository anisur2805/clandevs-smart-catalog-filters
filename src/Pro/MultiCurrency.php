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

/**
 * Keeps the price filter correct when a currency switcher is active.
 *
 * Currency switchers keep the `_price` meta in the store's base currency and
 * convert on display, so two conversions are needed: the slider bounds are
 * converted base to active for display, and the shopper's submitted range is
 * converted active back to base before it reaches the query.
 */
class MultiCurrency {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'cscf_price_bounds', array( $this, 'convert_price_bounds' ), 10, 2 );
		add_filter( 'cscf_query_args', array( $this, 'convert_price_clause' ), 5, 2 );
	}

	/**
	 * Convert slider bounds into the currency the shopper is browsing in.
	 *
	 * @param array  $bounds  Price bounds with keys min and max.
	 * @param string $context Where the bounds are used.
	 * @return array
	 */
	public function convert_price_bounds( array $bounds, string $context ): array {
		unset( $context );

		if ( ! $this->is_switching_currency() ) {
			return $bounds;
		}

		if ( isset( $bounds['min'] ) ) {
			$bounds['min'] = $this->to_active_currency( (float) $bounds['min'] );
		}

		if ( isset( $bounds['max'] ) ) {
			$bounds['max'] = $this->to_active_currency( (float) $bounds['max'] );
		}

		return $bounds;
	}

	/**
	 * Rescale the `_price` clause back into the store's base currency.
	 *
	 * @param array $query_args     Query args with tax_query, meta_query, post__in.
	 * @param array $filter_options Current filter visibility options.
	 * @return array
	 */
	public function convert_price_clause( array $query_args, array $filter_options ): array {
		unset( $filter_options );

		if ( ! $this->is_switching_currency() || empty( $query_args['meta_query'] ) || ! is_array( $query_args['meta_query'] ) ) {
			return $query_args;
		}

		foreach ( $query_args['meta_query'] as $index => $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) || '_price' !== $clause['key'] || ! isset( $clause['value'] ) ) {
				continue;
			}

			if ( is_array( $clause['value'] ) ) {
				$query_args['meta_query'][ $index ]['value'] = array_map(
					array( $this, 'to_base_currency' ),
					array_map( 'floatval', $clause['value'] )
				);
				continue;
			}

			$query_args['meta_query'][ $index ]['value'] = $this->to_base_currency( (float) $clause['value'] );
		}

		return $query_args;
	}

	/**
	 * Convert an amount from the base currency into the active currency.
	 *
	 * @param float $amount Amount in base currency.
	 * @return float
	 */
	private function to_active_currency( float $amount ): float {
		if ( $amount <= 0 ) {
			return $amount;
		}

		// WooCommerce Payments multi-currency.
		$wcpay = $this->get_wcpay_multi_currency();

		if ( $wcpay && method_exists( $wcpay, 'get_price' ) ) {
			return (float) $wcpay->get_price( $amount, 'product' );
		}

		// WooCommerce Multilingual / WPML Multi-currency.
		if ( function_exists( 'wcml_convert_price' ) ) {
			return (float) wcml_convert_price( $amount, $this->get_active_currency() );
		}

		// WooCommerce Multi Currency (VillaTheme / CURCY).
		if ( function_exists( 'wmc_get_price' ) ) {
			return (float) wmc_get_price( $amount, $this->get_active_currency() );
		}

		// Aelia Currency Switcher.
		if ( has_filter( 'wc_aelia_cs_convert' ) ) {
			/** This filter is documented by the Aelia Currency Switcher plugin. */
			return (float) apply_filters( 'wc_aelia_cs_convert', $amount, $this->get_base_currency(), $this->get_active_currency() );
		}

		$rate = $this->get_rate();

		/**
		 * Filters an amount converted from the base into the active currency.
		 *
		 * Use this to support a currency switcher that is not detected here.
		 *
		 * @param float  $converted Converted amount.
		 * @param float  $amount    Original amount in base currency.
		 * @param string $currency  Active currency code.
		 */
		return (float) apply_filters( 'cscfp_to_active_currency', $amount * $rate, $amount, $this->get_active_currency() );
	}

	/**
	 * Convert an amount from the active currency back into the base currency.
	 *
	 * @param float $amount Amount in the active currency.
	 * @return float
	 */
	public function to_base_currency( float $amount ): float {
		if ( $amount <= 0 ) {
			return $amount;
		}

		if ( has_filter( 'wc_aelia_cs_convert' ) ) {
			/** This filter is documented by the Aelia Currency Switcher plugin. */
			return (float) apply_filters( 'wc_aelia_cs_convert', $amount, $this->get_active_currency(), $this->get_base_currency() );
		}

		$rate = $this->get_rate();

		$converted = $rate > 0 ? $amount / $rate : $amount;

		/**
		 * Filters an amount converted from the active back into the base currency.
		 *
		 * @param float  $converted Converted amount.
		 * @param float  $amount    Original amount in the active currency.
		 * @param string $currency  Active currency code.
		 */
		return (float) apply_filters( 'cscfp_to_base_currency', $converted, $amount, $this->get_active_currency() );
	}

	/**
	 * Effective base to active exchange rate.
	 *
	 * Derived by converting a known amount so it works with any switcher that
	 * exposes only a conversion function.
	 *
	 * @return float
	 */
	private function get_rate(): float {
		static $rate = null;

		if ( null !== $rate ) {
			return $rate;
		}

		$rate = 1.0;

		// Derived by converting a known amount, so it works with any switcher
		// that only exposes a one-way conversion call.
		$wcpay = $this->get_wcpay_multi_currency();

		if ( $wcpay && method_exists( $wcpay, 'get_price' ) ) {
			$rate = (float) $wcpay->get_price( 100, 'product' ) / 100;
		} elseif ( function_exists( 'wcml_convert_price' ) ) {
			$rate = (float) wcml_convert_price( 100, $this->get_active_currency() ) / 100;
		} elseif ( function_exists( 'wmc_get_price' ) ) {
			$rate = (float) wmc_get_price( 100, $this->get_active_currency() ) / 100;
		}

		if ( $rate <= 0 ) {
			$rate = 1.0;
		}

		return $rate;
	}

	/**
	 * Resolve the WooCommerce Payments multi-currency instance, if present.
	 *
	 * Every call site guards with method_exists(), so an API change in
	 * WooPayments degrades to the generic conversion path rather than fataling.
	 *
	 * @return object|null
	 */
	private function get_wcpay_multi_currency() {
		if ( function_exists( 'WC_Payments_Multi_Currency' ) ) {
			$instance = WC_Payments_Multi_Currency();

			return is_object( $instance ) ? $instance : null;
		}

		if ( class_exists( '\WCPay\MultiCurrency\MultiCurrency' ) && method_exists( '\WCPay\MultiCurrency\MultiCurrency', 'instance' ) ) {
			$instance = \WCPay\MultiCurrency\MultiCurrency::instance();

			return is_object( $instance ) ? $instance : null;
		}

		return null;
	}

	/**
	 * Whether the shopper is browsing in a currency other than the base one.
	 *
	 * @return bool
	 */
	private function is_switching_currency(): bool {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return false;
		}

		return $this->get_active_currency() !== $this->get_base_currency();
	}

	/**
	 * Currency the shopper is browsing in.
	 *
	 * @return string
	 */
	private function get_active_currency(): string {
		return function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
	}

	/**
	 * Store base currency, i.e. the currency prices are stored in.
	 *
	 * @return string
	 */
	private function get_base_currency(): string {
		$base = get_option( 'woocommerce_currency', '' );

		return is_string( $base ) ? $base : '';
	}
}
