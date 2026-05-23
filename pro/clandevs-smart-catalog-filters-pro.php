<?php
/**
 * Plugin Name: Clandevs Smart Catalog Filters Pro
 * Plugin URI: https://github.com/anisur2805/clandevs-smart-catalog-filters/
 * Description: Premium add-on for Clandevs Smart Catalog Filters. Adds presets, advanced analytics, comparison, visual swatches, SEO URLs, and more.
 * Version: 2.0.0
 * Author: Anisur Rahman
 * Author URI: https://portfolio.clandevs.com
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clandevs-smart-catalog-filters-pro
 * Domain Path: /languages
 * Requires Plugins: clandevs-smart-catalog-filters
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSCFP_VERSION', '2.0.0' );
define( 'CSCFP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CSCFP_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/src/Autoloader.php';

\ClandevsSmartCatalogFiltersPro\Autoloader::register( __DIR__ . '/src' );

if ( ! function_exists( 'cscfp_fs' ) ) {
	/**
	 * Freemius SDK helper for Pro plugin.
	 *
	 * @return \Freemius
	 */
	function cscfp_fs() {
		global $cscfp_fs;

		if ( ! isset( $cscfp_fs ) ) {
			if ( ! file_exists( __DIR__ . '/vendor/freemius/start.php' ) ) {
				return null;
			}
			require_once __DIR__ . '/vendor/freemius/start.php';

			$cscfp_fs = fs_dynamic_init( array(
				'id'                  => '29823',
				'slug'                => 'clandevs-smart-catalog-filters-pro',
				'type'                => 'plugin',
				'public_key'          => 'pk_653dc3a7f5f02e24d22bf27d57500',
				'is_premium'          => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => false,
				'menu'                => array(
					'slug'    => 'clandevs-smart-catalog-filters',
					'account' => true,
					'support' => false,
				),
			) );
		}

		return $cscfp_fs;
	}

	cscfp_fs();
}

// Boot after free plugin is ready.
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'ClandevsSmartCatalogFilters\Plugin' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'Clandevs Smart Catalog Filters Pro requires the free version to be installed and active.', 'clandevs-smart-catalog-filters-pro' );
					echo '</p></div>';
				}
			);
			return;
		}

		\ClandevsSmartCatalogFiltersPro\ProPlugin::boot();
	},
	20
);
