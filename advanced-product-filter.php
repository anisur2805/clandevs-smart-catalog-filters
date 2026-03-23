<?php
/**
 * Plugin Name: Advanced Product Filter
 * Plugin URI: https://clandevs.com/advancedproductfilter/
 * Description: Filter WooCommerce products by category, price, and availability for free. Upgrade to Pro for brand, color, rating, analytics, and full styling.
 * Version: 1.0.0
 * Author: Anisur Rahman
 * Author URI: https://portfolio.clandevs.com
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-product-filter
 * Requires Plugins: woocommerce
 * Domain Path: /languages
 *
 * @package AdvancedProductFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'APF_PLUGIN_URL' ) ) {
	define( 'APF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'APF_VERSION' ) ) {
	define( 'APF_VERSION', '1.0.0' );
}

require_once __DIR__ . '/src/Autoloader.php';

\AdvancedProductFilter\Autoloader::register( __DIR__ . '/src' );

if ( ! function_exists( 'wfs_fs' ) ) {
	// Create a helper function for easy SDK access.
	function wfs_fs() {
		global $wfs_fs;

		if ( ! isset( $wfs_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

			$wfs_fs = fs_dynamic_init( array(
				'id'                  => '26209',
				'slug'                => 'advanced-product-filter',
				'type'                => 'plugin',
				'public_key'          => 'pk_c409d5141f9173a5c8ba6cf201103',
				'is_premium'          => false,
				'premium_suffix'      => 'Pro',
				// If your plugin is a serviceware, set this option to false.
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				// Automatically removed in the free version. If you're not using the
				// auto-generated free version, delete this line before uploading to wp.org.
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'trial'               => array(
					'days'               => 14,
					'is_require_payment' => false,
				),
				'menu'                => array(
					'slug'       => 'advanced-product-filter',
					'first-path' => 'admin.php?page=advanced-product-filter',
					'support'    => false,
				),
			) );
		}

		return $wfs_fs;
	}

	// Init Freemius.
	wfs_fs();
	// Signal that SDK was initiated.
	do_action( 'wfs_fs_loaded' );
}

// Freemius uninstall hook — replaces uninstall.php.
wfs_fs()->add_action( 'after_uninstall', 'wfs_fs_uninstall_cleanup' );

/**
 * Clean up plugin data on uninstall via Freemius.
 *
 * @return void
 */
function wfs_fs_uninstall_cleanup() {
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
			'<a href="' . esc_url( admin_url( 'admin.php?page=advanced-product-filter' ) ) . '">' . esc_html__( 'Styling', 'advanced-product-filter' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-settings' ) ) . '">' . esc_html__( 'Settings', 'advanced-product-filter' ) . '</a>',
		);

		if ( \AdvancedProductFilter\License::can( 'analytics' ) ) {
			$custom_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-analytics' ) ) . '">' . esc_html__( 'Analytics', 'advanced-product-filter' ) . '</a>';
		}

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
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Advanced Product Filter requires WooCommerce to be installed and active.', 'advanced-product-filter' ) . '</p></div>';
				}
			);
			return;
		}

		\AdvancedProductFilter\Plugin::instance( __FILE__ )->boot();
	}
);
