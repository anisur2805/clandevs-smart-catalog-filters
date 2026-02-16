<?php
/**
 * Admin top-level menu controller.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the primary Woo Filters admin menu.
 */
final class AdminMenu {
	/** @var string */
	private const MENU_SLUG = 'wf-main';

	/** @var string */
	private const SETTINGS_SLUG = 'wf-filter-settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'remove_default_submenu' ), 999 );
	}

	/**
	 * Get parent menu slug used by child pages.
	 *
	 * @return string
	 */
	public static function get_menu_slug(): string {
		return self::MENU_SLUG;
	}

	/**
	 * Register top-level menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Woo Filters', 'woo-filters' ),
			__( 'Woo Filters', 'woo-filters' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_root_page' ),
			'dashicons-filter',
			56
		);
	}

	/**
	 * Remove auto-generated duplicate submenu item.
	 *
	 * @return void
	 */
	public function remove_default_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Redirect root menu URL to the settings submenu.
	 *
	 * @return void
	 */
	public function render_root_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::SETTINGS_SLUG,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
