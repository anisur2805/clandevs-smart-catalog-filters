<?php
/**
 * Plugin Name: Woo Filter Studio
 * Description: Filter WooCommerce products by category, attributes, price, rating, stock, and more with AJAX and shortcode support (Elementor-friendly).
 * Version: 0.1.0
 * Author: Anisur Rahman
 * Author URI: https://github.com/anisur2805
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
	define( 'WF_VERSION', '0.1.0' );
}

require_once __DIR__ . '/src/Autoloader.php';

\WooFilters\Autoloader::register( __DIR__ . '/src' );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-settings' ) ) . '">' . esc_html__( 'Settings', 'woo-filter-studio' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-style-settings' ) ) . '">' . esc_html__( 'Styling', 'woo-filter-studio' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wf-filter-analytics' ) ) . '">' . esc_html__( 'Analytics', 'woo-filter-studio' ) . '</a>',
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
		load_plugin_textdomain( 'woo-filter-studio', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		\WooFilters\Plugin::instance( __FILE__ )->boot();
	}
);
