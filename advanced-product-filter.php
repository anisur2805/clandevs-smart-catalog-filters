<?php
/**
 * Plugin Name: Clandevs Smart Catalog Filters
 * Plugin URI: https://clandevs.com/smart-catalog-filters/
 * Description: Filter WooCommerce products by category, price, availability, brand, color, rating, and custom attributes. AJAX-powered with analytics, full styling controls, and multiple skins.
 * Version: 2.0.0
 * Author: Anisur Rahman
 * Author URI: https://portfolio.clandevs.com
 * Requires at least: 6.0
 * Tested up to: 6.9
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

define( 'CSCF_VERSION', '2.0.0' );
define( 'CSCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Autoloader.php';
\ClandevsSmartCatalogFilters\Autoloader::register( __DIR__ . '/src' );

if ( ! function_exists( 'cscf_fs' ) ) {
	// Create a helper function for easy SDK access.
	function cscf_fs() {
		global $cscf_fs;

		if ( ! isset( $cscf_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

			$cscf_fs = fs_dynamic_init( array(
				'id'                  => '29823',
				'slug'                => 'clandevs-smart-catalog-filters',
				'premium_slug'        => 'clandevs-smart-catalog-filters-pro',
				'type'                => 'plugin',
				'public_key'          => 'pk_653dc3a7f5f02e24d22bf27d57500',
				'is_premium'          => false,
				'premium_suffix'      => 'Gold',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu'                => array(
					'slug'           => 'clandevs-smart-catalog-filters',
					'first-path'     => 'admin.php?page=clandevs-smart-catalog-filters',
					'support'        => false,
				),
			) );
		}

		return $cscf_fs;
	}

	// Init Freemius.
	cscf_fs();
	// Signal that SDK was initiated.
	do_action( 'cscf_fs_loaded' );
}

// Freemius uninstall hook — replaces uninstall.php.
cscf_fs()->add_action( 'after_uninstall', 'cscf_uninstall_cleanup' );

/**
 * Clean up plugin data on uninstall via Freemius.
 *
 * @return void
 */
function cscf_uninstall_cleanup() {
	$options = get_option( 'wf_filter_options', array() );
	$delete  = is_array( $options ) && isset( $options['delete_data_on_uninstall'] ) && 'yes' === $options['delete_data_on_uninstall'];

	if ( ! $delete ) {
		return;
	}

	delete_option( 'wf_filter_options' );
	delete_option( 'wf_style_options' );
	delete_option( 'wf_analytics_data' );
	delete_option( 'wf_cache_last_changed' );
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=clandevs-smart-catalog-filters' ) ) . '">' . esc_html__( 'Styling', 'clandevs-smart-catalog-filters' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-settings' ) ) . '">' . esc_html__( 'Settings', 'clandevs-smart-catalog-filters' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-analytics' ) ) . '">' . esc_html__( 'Analytics', 'clandevs-smart-catalog-filters' ) . '</a>',
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
