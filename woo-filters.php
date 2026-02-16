<?php
/**
 * Plugin Name: Woo Filters
 * Description: Shop/archive filters for WooCommerce.
 * Version: 0.11.0
 * Author: Anisur Rahman
 * Author URI: https://github.com/anisur2805
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 10.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';

\WooFilters\Autoloader::register( __DIR__ . '/src' );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		\WooFilters\Plugin::instance( __FILE__ )->boot();
	}
);
