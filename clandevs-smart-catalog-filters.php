<?php
/**
 * Plugin Name: Clandevs — WooCommerce Product Filter
 * Plugin URI: https://github.com/anisur2805/clandevs-smart-catalog-filters/
 * Description: Filter WooCommerce products by category, price, brand, color, rating, attributes, dates, and variations. Presets, comparison, SEO URLs, analytics.
 * Version: 3.0.0
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
	// Bail if another copy of THIS plugin is already loaded. The retired Pro
	// add-on only defines CSCFP_VERSION; its copy of these classes is kept
	// inert by the ProPlugin boot guard and the retire routine below.
	return;
}

define( 'CSCF_VERSION', '3.0.0' );
define( 'CSCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * The former Pro add-on's feature set now ships with this plugin. The merged
 * code keeps its historical constants and namespace; both are defined here so
 * sites that ran the add-on keep working without any reconfiguration.
 */
if ( ! defined( 'CSCFP_VERSION' ) ) {
	define( 'CSCFP_VERSION', CSCF_VERSION );
}

if ( ! defined( 'CSCFP_PLUGIN_URL' ) ) {
	define( 'CSCFP_PLUGIN_URL', CSCF_PLUGIN_URL );
}

if ( ! defined( 'CSCFP_PLUGIN_FILE' ) ) {
	define( 'CSCFP_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/src/Autoloader.php';
\ClandevsSmartCatalogFilters\Autoloader::register( __DIR__ . '/src' );
\ClandevsSmartCatalogFilters\Autoloader::register( __DIR__ . '/src/Pro', 'ClandevsSmartCatalogFiltersPro\\' );

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

	// Pretty filter URL rewrite rules (SEO Filter URLs feature).
	if ( class_exists( '\ClandevsSmartCatalogFiltersPro\SEOFriendlyURLs' ) ) {
		\ClandevsSmartCatalogFiltersPro\SEOFriendlyURLs::register_rewrite_rules();
	}

	flush_rewrite_rules();
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
	wp_clear_scheduled_hook( 'cscfp_flush_daily_analytics' );
	flush_rewrite_rules();
}

/**
 * Retire the separate Pro add-on once its features were merged into this
 * plugin.
 *
 * Keeping both active would run legacy Pro copies of the merged classes, so
 * the add-on is deactivated (never deleted) on the next admin request and the
 * store owner is told why. Settings and data are untouched: the merged plugin
 * reads the same options.
 *
 * @return void
 */
function cscf_retire_pro_addon() {
	$basename = 'clandevs-smart-catalog-filters-pro/clandevs-smart-catalog-filters-pro.php';

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// A network-activated add-on can only be retired from the network admin;
	// leave it (and any notice) alone from a subsite request.
	if ( is_plugin_active_for_network( $basename ) && ! is_network_admin() ) {
		return;
	}

	if ( ! is_plugin_active( $basename ) ) {
		return;
	}

	deactivate_plugins( $basename, true );
	set_transient( 'cscf_pro_merged_notice', 1, HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'cscf_retire_pro_addon', 1 );

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! get_transient( 'cscf_pro_merged_notice' ) ) {
			return;
		}

		delete_transient( 'cscf_pro_merged_notice' );

		echo '<div class="notice notice-info is-dismissible"><p>';
		echo esc_html__( 'The Smart Catalog Filters Pro add-on was deactivated: every Pro feature is now included in Clandevs Smart Catalog Filters itself. Your settings and data were kept, and no license key is needed anymore.', 'clandevs-smart-catalog-filters' );
		echo '</p></div>';
	}
);

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
	'init',
	static function () {
		load_plugin_textdomain(
			'clandevs-smart-catalog-filters',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
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
