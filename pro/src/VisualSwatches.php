<?php
/**
 * Image/visual swatch for attribute filters.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VisualSwatches {
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cscf_save_swatches', array( $this, 'ajax_save_swatches' ) );
	}

	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Visual Swatches', 'clandevs-smart-catalog-filters-pro' ),
			__( 'Visual Swatches', 'clandevs-smart-catalog-filters-pro' ),
			'manage_woocommerce',
			'cscf-visual-swatches',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'clandevs-smart-catalog-filters_page_cscf-visual-swatches' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/css/pro-admin.css', array(), CSCFP_VERSION );
		wp_enqueue_media();
	}

	public function render_page(): void {
		// Handle form submission.
		if ( isset( $_POST['_cscf_swatches_nonce'] ) && check_admin_referer( 'cscf_save_swatches', '_cscf_swatches_nonce' ) ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$swatches = array_map( 'absint', (array) ( $_POST['swatches'] ?? array() ) );
				update_option( 'cscf_visual_swatches', $swatches );
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Swatches saved.', 'clandevs-smart-catalog-filters-pro' ) . '</p></div>';
			}
		}

		$swatches = $this->get_swatches();
		$taxonomies = wc_get_attribute_taxonomies();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Visual Swatches', 'clandevs-smart-catalog-filters-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Assign images to attribute terms for visual filtering.', 'clandevs-smart-catalog-filters-pro' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'cscf_save_swatches', '_cscf_swatches_nonce' );
		foreach ( $taxonomies as $tax ) {
			$terms = get_terms( array( 'taxonomy' => 'pa_' . $tax->attribute_name, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			echo '<h3>' . esc_html( $tax->attribute_label ) . '</h3>';
			echo '<div class="cscf-swatch-grid">';
			foreach ( $terms as $term ) {
				$image_id = isset( $swatches[ $term->term_id ] ) ? (int) $swatches[ $term->term_id ] : 0;
				$url      = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
				echo '<div class="cscf-swatch-item">';
				echo '<strong>' . esc_html( $term->name ) . '</strong>';
				echo '<img src="' . esc_url( $url ) . '" style="width:40px;height:40px;object-fit:cover;display:block;margin:4px 0;" />';
				echo '<input type="hidden" name="swatches[' . esc_attr( $term->term_id ) . ']" value="' . esc_attr( $image_id ) . '" />';
				echo '<button type="button" class="button cscf-upload-swatch">' . esc_html__( 'Set Image', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save Swatches', 'clandevs-smart-catalog-filters-pro' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	public function ajax_save_swatches(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$swatches = array_map( 'absint', (array) ( $_POST['swatches'] ?? array() ) );
		update_option( 'cscf_visual_swatches', $swatches );
		wp_send_json_success();
	}

	public static function get_swatches(): array {
		$swatches = get_option( 'cscf_visual_swatches', array() );
		return is_array( $swatches ) ? $swatches : array();
	}
}
