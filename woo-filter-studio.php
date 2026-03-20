<?php
/**
 * Plugin Name: Woo Filter Studio
 * Plugin URI: https://woo-filter-studio.clandevs.com/
 * Description: Filter WooCommerce products by category, price, and availability for free. Upgrade to Pro for brand, color, rating, analytics, and full styling.
 * Version: 1.1.1
 * Author: Anisur Rahman
 * Author URI: https://portfolio.clandevs.com
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-filter-studio
 * Requires Plugins: woocommerce
 * Domain Path: /languages
 *
 * @package WooFilters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WF_PLUGIN_URL' ) ) {
	define( 'WF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WF_VERSION' ) ) {
	define( 'WF_VERSION', '1.1.1' );
}

require_once __DIR__ . '/src/Autoloader.php';

\WooFilters\Autoloader::register( __DIR__ . '/src' );

// Freemius SDK integration — free/premium auto-deactivation pattern.
if ( function_exists( 'wfs_fs' ) ) {
	wfs_fs()->set_basename( true, __FILE__ );
} else {
	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 */
	if ( ! function_exists( 'wfs_fs' ) ) {
		/**
		 * Create a helper function for easy Freemius SDK access.
		 *
		 * @return \Freemius
		 */
		function wfs_fs() {
			global $wfs_fs;

			if ( ! isset( $wfs_fs ) ) {
				// Include Freemius SDK.
				require_once __DIR__ . '/vendor/freemius/start.php';

				$wfs_fs = fs_dynamic_init(
					array(
						'id'                  => '26209',
						'slug'                => 'woo-filter-studio',
						'type'                => 'plugin',
						'public_key'          => 'pk_c409d5141f9173a5c8ba6cf201103',
						'is_premium'          => true,
						'premium_suffix'      => 'Pro',
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'is_org_compliant'    => true,
						// Automatically removed in the free version.
						'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
						'trial'               => array(
							'days'               => 14,
							'is_require_payment' => false,
						),
						'menu'                => array(
							'slug'       => 'woo-filter-studio',
							'first-path' => 'admin.php?page=woo-filter-studio',
							'support'    => false,
						),
					)
				);
			}

			return $wfs_fs;
		}

		// Init Freemius.
		wfs_fs();
		// Signal that SDK was initiated.
		do_action( 'wfs_fs_loaded' );

		// Freemius uninstall hook — replaces uninstall.php.
		wfs_fs()->add_action( 'after_uninstall', 'wfs_fs_uninstall_cleanup' );
	}

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
				'<a href="' . esc_url( admin_url( 'admin.php?page=woo-filter-studio' ) ) . '">' . esc_html__( 'Styling', 'woo-filter-studio' ) . '</a>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-settings' ) ) . '">' . esc_html__( 'Settings', 'woo-filter-studio' ) . '</a>',
			);

			if ( \WooFilters\License::can( 'analytics' ) ) {
				$custom_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-analytics' ) ) . '">' . esc_html__( 'Analytics', 'woo-filter-studio' ) . '</a>';
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
			load_plugin_textdomain( 'woo-filter-studio', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action(
					'admin_notices',
					static function () {
						if ( ! current_user_can( 'activate_plugins' ) ) {
							return;
						}
						echo '<div class="notice notice-error"><p>' . esc_html__( 'Woo Filter Studio requires WooCommerce to be installed and active.', 'woo-filter-studio' ) . '</p></div>';
					}
				);
				return;
			}

			\WooFilters\Plugin::instance( __FILE__ )->boot();
		}
	);
}
