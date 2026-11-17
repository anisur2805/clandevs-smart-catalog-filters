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
		add_action( 'admin_post_cscf_reset_filter_order', array( $this, 'handle_reset' ) );
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
		wp_enqueue_style( 'cscfp-admin', CSCF_PLUGIN_URL . 'assets/css/pro-admin.css', array( 'cscf-admin' ), CSCF_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'cscf-drag-drop', CSCF_PLUGIN_URL . 'assets/js/drag-drop.js', array( 'jquery', 'jquery-ui-sortable' ), CSCF_VERSION, true );
		wp_localize_script(
			'cscf-drag-drop',
			'cscfOrder',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cscf_save_order' ),
				'i18n'    => array(
					'saved' => __( 'Filter order saved.', 'clandevs-smart-catalog-filters' ),
					'error' => __( 'The filter order could not be saved.', 'clandevs-smart-catalog-filters' ),
				),
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
		$filters = self::get_labels();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Filter Order', 'clandevs-smart-catalog-filters' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag rendered after a nonce-checked redirect.
		if ( isset( $_GET['cscf-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Filter order reset to the default.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Drag the handles to choose the order filters appear in on the storefront. Changes save automatically.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<ul id="cscf-filter-sortable" class="cscfp-sortable">';
		foreach ( $order as $key ) {
			$label = isset( $filters[ $key ] ) ? $filters[ $key ] : ucwords( str_replace( '_', ' ', $key ) );
			echo '<li class="cscfp-sortable-item" data-key="' . esc_attr( $key ) . '">';
			echo '<span class="cscfp-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>';
			echo '<span class="cscfp-sortable-label">' . esc_html( $label ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';

		echo '<p class="cscfp-order-status" role="status" aria-live="polite"></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_reset_filter_order' );
		echo '<input type="hidden" name="action" value="cscf_reset_filter_order" />';
		echo '<button type="submit" class="button">' . esc_html__( 'Reset to default order', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Human readable labels for the orderable sections.
	 *
	 * @return array<string, string>
	 */
	public static function get_labels(): array {
		return array(
			'categories' => __( 'Categories', 'clandevs-smart-catalog-filters' ),
			'brands'     => __( 'Brands', 'clandevs-smart-catalog-filters' ),
			'logic'      => __( 'Multi-select Logic', 'clandevs-smart-catalog-filters' ),
			'price'      => __( 'Price', 'clandevs-smart-catalog-filters' ),
			'rating'     => __( 'Customer Rating', 'clandevs-smart-catalog-filters' ),
			'colors'     => __( 'Colors', 'clandevs-smart-catalog-filters' ),
			'tags'       => __( 'Tags', 'clandevs-smart-catalog-filters' ),
			'attributes' => __( 'Attributes', 'clandevs-smart-catalog-filters' ),
		);
	}

	/**
	 * Restore the default ordering.
	 *
	 * @return void
	 */
	public function handle_reset(): void {
		check_admin_referer( 'cscf_reset_filter_order' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change the filter order.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		delete_option( 'cscf_filter_order' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'cscf-filter-order',
					'cscf-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
