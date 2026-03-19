<?php
/**
 * Admin menu controller.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers primary Woo Filter Studio admin menu.
 */
final class AdminMenu {
	/** @var string */
	private const MENU_SLUG = 'woo-filter-studio';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'cleanup_default_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'redirect_root_page' ) );
	}

	/**
	 * Get main menu slug.
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
			__( 'Woo Filter Studio', 'woo-filter-studio' ),
			__( 'Woo Filter Studio', 'woo-filter-studio' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_root_page' ),
			'dashicons-filter',
			56
		);
	}

	/**
	 * Remove duplicate submenu entry created by add_menu_page.
	 *
	 * @return void
	 */
	public function cleanup_default_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Redirect top-level menu page to filter settings before headers are sent.
	 *
	 * @return void
	 */
	public function redirect_root_page(): void {
		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wf-filter-settings',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render root page placeholder (redirect happens in admin_init).
	 *
	 * @return void
	 */
	public function render_root_page(): void {
		// Redirect is handled in redirect_root_page() via admin_init.
	}
}
