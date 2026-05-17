<?php
/**
 * Filter presets — save and load filter combinations.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Presets {
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cscf_save_preset', array( $this, 'ajax_save_preset' ) );
		add_action( 'wp_ajax_cscf_load_preset', array( $this, 'ajax_load_preset' ) );
		add_action( 'wp_ajax_cscf_delete_preset', array( $this, 'ajax_delete_preset' ) );
	}

	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Filter Presets', 'clandevs-smart-catalog-filters-pro' ),
			__( 'Presets', 'clandevs-smart-catalog-filters-pro' ),
			'manage_woocommerce',
			'cscf-presets',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'clandevs-smart-catalog-filters_page_cscf-presets' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/css/pro-admin.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-presets', CSCFP_PLUGIN_URL . 'assets/js/presets.js', array( 'jquery' ), CSCFP_VERSION, true );
		wp_localize_script( 'cscfp-presets', 'cscfpPresets', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}

	public function render_page(): void {
		$presets = $this->get_presets();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Filter Presets', 'clandevs-smart-catalog-filters-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Save and load filter combinations for quick access.', 'clandevs-smart-catalog-filters-pro' ) . '</p>';
		echo '<div id="cscf-presets-list">';
		foreach ( $presets as $id => $preset ) {
			echo '<div class="cscf-preset-item" data-id="' . esc_attr( $id ) . '">';
			echo '<strong>' . esc_html( $preset['name'] ) . '</strong>';
			echo '<button class="button cscf-load-preset">' . esc_html__( 'Load', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
			echo '<button class="button cscf-delete-preset">' . esc_html__( 'Delete', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
			echo '</div>';
		}
		echo '</div>';
		echo '</div>';
	}

	public function ajax_save_preset(): void {
		check_ajax_referer( 'cscf_preset', '_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$name   = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$filters = array_map( 'sanitize_text_field', (array) ( $_POST['filters'] ?? array() ) );
		if ( '' === $name ) {
			wp_send_json_error( 'Name required' );
		}
		$presets           = $this->get_presets();
		$id                = wp_generate_password( 8, false );
		$presets[ $id ]    = array( 'name' => $name, 'filters' => $filters );
		update_option( 'cscf_presets', $presets );
		wp_send_json_success( array( 'id' => $id ) );
	}

	public function ajax_load_preset(): void {
		check_ajax_referer( 'cscf_preset', '_nonce' );
		$id      = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$presets = $this->get_presets();
		if ( ! isset( $presets[ $id ] ) ) {
			wp_send_json_error( 'Not found' );
		}
		wp_send_json_success( $presets[ $id ] );
	}

	public function ajax_delete_preset(): void {
		check_ajax_referer( 'cscf_preset', '_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$id      = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$presets = $this->get_presets();
		unset( $presets[ $id ] );
		update_option( 'cscf_presets', $presets );
		wp_send_json_success();
	}

	private function get_presets(): array {
		$presets = get_option( 'cscf_presets', array() );
		return is_array( $presets ) ? $presets : array();
	}
}
