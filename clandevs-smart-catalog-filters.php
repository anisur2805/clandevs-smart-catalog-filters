<?php
/**
 * Plugin Name: Clandevs — WooCommerce Product Filter
 * Plugin URI: https://github.com/anisur2805/clandevs-smart-catalog-filters/
 * Description: Filter WooCommerce products by category, price, availability, brand, color, rating, and custom attributes. AJAX-powered with analytics, full styling controls, and multiple skins.
 * Version: 2.0.6
 * Author: Anisur Rahman
 * Author URI: https://portfolio.clandevs.com
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clandevs-smart-catalog-filters
 * Requires Plugins: woocommerce
 * Domain Path: /languages
 *
 * @package ClandevsSmartCatalogFilters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'CSCF_VERSION' ) ) {
	// Bail if another instance of this plugin is already loaded (e.g. free + pro).
	return;
}

define( 'CSCF_VERSION', '2.0.6' );
define( 'CSCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Autoloader.php';
\ClandevsSmartCatalogFilters\Autoloader::register( __DIR__ . '/src' );

register_activation_hook( __FILE__, 'cscf_activate' );
register_deactivation_hook( __FILE__, 'cscf_deactivate' );

/**
 * Initialize plugin data on activation.
 *
 * @return void
 */
function cscf_activate() {
	update_option( 'cscf_activation_time', (string) time(), false );
	update_option( 'cscf_filter_use_count', 0, false );
}

/**
 * Clean up runtime state on deactivation.
 *
 * Plugin settings and analytics are intentionally kept across deactivation;
 * data deletion happens on uninstall and is gated by the
 * "Delete plugin data on uninstall" setting (see uninstall.php).
 *
 * @return void
 */
function cscf_deactivate() {
	wp_clear_scheduled_hook( 'cscf_flush_analytics_buffer' );
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=cscf-style-settings' ) ) . '">' . esc_html__( 'Styling', 'clandevs-smart-catalog-filters' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=cscf-filter-settings' ) ) . '">' . esc_html__( 'Settings', 'clandevs-smart-catalog-filters' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=cscf-filter-analytics' ) ) . '">' . esc_html__( 'Analytics', 'clandevs-smart-catalog-filters' ) . '</a>',
		);

		return array_merge( $custom_links, $links );
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Clandevs Smart Catalog Filters requires WooCommerce to be installed and active.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
				}
			);
			return;
		}

		\ClandevsSmartCatalogFilters\Plugin::instance( __FILE__ )->boot();
	}
);
