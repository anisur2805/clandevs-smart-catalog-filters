<?php
/**
 * Drag-and-drop filter ordering in admin.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drag-and-drop filter ordering in admin.
 */
class FilterOrdering {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cscf_save_order', array( $this, 'ajax_save_order' ) );
	}

	/**
	 * Add the Filter Order submenu page.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Filter Order', 'clandevs-smart-catalog-filters' ),
			__( 'Filter Order', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			'cscf-filter-order',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets on the Filter Order screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'clandevs-smart-catalog-filters_page_cscf-filter-order' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cscf-admin', CSCF_PLUGIN_URL . 'assets/css/wf-admin.css', array(), CSCF_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'cscf-drag-drop', CSCF_PLUGIN_URL . 'assets/js/drag-drop.js', array( 'jquery', 'jquery-ui-sortable' ), CSCF_VERSION, true );
		wp_localize_script(
			'cscf-drag-drop',
			'cscfOrder',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cscf_save_order' ),
			)
		);
	}

	/**
	 * Render the Filter Order admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$order   = $this->get_filter_order();
		$filters = array(
			'categories' => __( 'Categories', 'clandevs-smart-catalog-filters' ),
			'brands'     => __( 'Brands', 'clandevs-smart-catalog-filters' ),
			'logic'      => __( 'Multi-select Logic', 'clandevs-smart-catalog-filters' ),
			'price'      => __( 'Price', 'clandevs-smart-catalog-filters' ),
			'rating'     => __( 'Customer Rating', 'clandevs-smart-catalog-filters' ),
			'colors'     => __( 'Colors', 'clandevs-smart-catalog-filters' ),
			'tags'       => __( 'Tags', 'clandevs-smart-catalog-filters' ),
			'attributes' => __( 'Attributes', 'clandevs-smart-catalog-filters' ),
		);
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Filter Order', 'clandevs-smart-catalog-filters' ) . '</h1>';
		echo '<p>' . esc_html__( 'Drag and drop to reorder filters.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<ul id="cscf-filter-sortable">';
		foreach ( $order as $key ) {
			$label = isset( $filters[ $key ] ) ? $filters[ $key ] : $key;
			echo '<li class="cscf-sortable-item" data-key="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * AJAX handler that saves the custom filter order.
	 *
	 * @return void
	 */
	public function ajax_save_order(): void {
		check_ajax_referer( 'cscf_save_order', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$order = array_map( 'sanitize_key', (array) ( $_POST['order'] ?? array() ) );
		update_option( 'cscf_filter_order', self::normalize_order( $order ) );
		wp_send_json_success();
	}

	/**
	 * Get the saved filter order, normalized against defaults.
	 *
	 * @return array<int, string>
	 */
	public static function get_filter_order(): array {
		$default = self::get_default_order();
		$order   = get_option( 'cscf_filter_order', $default );
		return self::normalize_order( is_array( $order ) ? $order : $default );
	}

	/**
	 * Get default filter ordering.
	 *
	 * @return array<int, string>
	 */
	public static function get_default_order(): array {
		return array( 'categories', 'brands', 'logic', 'price', 'rating', 'colors', 'tags', 'attributes' );
	}

	/**
	 * Normalize ordering to known keys, preserving user order.
	 *
	 * @param array<int, string> $order Saved order.
	 * @return array<int, string>
	 */
	private static function normalize_order( array $order ): array {
		$allowed = self::get_default_order();
		$clean   = array();

		foreach ( $order as $key ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! in_array( $key, $allowed, true ) || in_array( $key, $clean, true ) ) {
				continue;
			}

			$clean[] = $key;
		}

		foreach ( $allowed as $key ) {
			if ( ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}

		return $clean;
	}
}
