<?php
/**
 * Admin menu controller.
 *
 * @package AdvancedProductFilter
 */

namespace AdvancedProductFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers primary Advanced Product Filter admin menu.
 */
final class AdminMenu {
	/** @var string */
	private const MENU_SLUG = 'advanced-product-filter';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
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
	 * The first submenu registered by FilterSettings uses the same slug
	 * as this parent, so WordPress merges them into a single menu entry.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Advanced Product Filter', 'advanced-product-filter' ),
			__( 'Advanced Product Filter', 'advanced-product-filter' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			'__return_null',
			'dashicons-filter',
			56
		);
	}
}
