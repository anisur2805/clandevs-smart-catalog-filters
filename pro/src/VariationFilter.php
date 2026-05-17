<?php
/**
 * Variation-level filtering (size, color per variation).
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VariationFilter {
	public function register_hooks(): void {
		add_filter( 'cscf_query_args', array( $this, 'modify_query' ), 20, 2 );
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
	}

	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Variation Filtering', 'clandevs-smart-catalog-filters-pro' ),
			__( 'Variation Filtering', 'clandevs-smart-catalog-filters-pro' ),
			'manage_woocommerce',
			'cscf-variation-filter',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		$enabled = get_option( 'cscf_variation_filter_enabled', 'no' );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Variation Filtering', 'clandevs-smart-catalog-filters-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Allow filtering by variation attributes (e.g., size, color per variation).', 'clandevs-smart-catalog-filters-pro' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'cscf_variation_settings' );
		echo '<label><input type="checkbox" name="enabled" value="yes" ' . checked( $enabled, 'yes', false ) . ' /> ' . esc_html__( 'Enable variation-level filtering', 'clandevs-smart-catalog-filters-pro' ) . '</label>';
		echo ' <button class="button button-primary">' . esc_html__( 'Save', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
		echo '</form>';
		echo '</div>';

		if ( isset( $_POST['_wpnonce'] ) && check_admin_referer( 'cscf_variation_settings' ) ) {
			$enabled = isset( $_POST['enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) ? 'yes' : 'no';
			update_option( 'cscf_variation_filter_enabled', $enabled );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'clandevs-smart-catalog-filters-pro' ) . '</p></div>';
		}
	}

	public function modify_query( array $query_args, array $filter_options ): array {
		if ( 'yes' !== get_option( 'cscf_variation_filter_enabled', 'no' ) ) {
			return $query_args;
		}
		// Include products that match via variation attributes.
		if ( ! isset( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = array( 'relation' => 'OR' );
		}
		return $query_args;
	}
}
