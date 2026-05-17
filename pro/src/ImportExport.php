<?php
/**
 * Import/export plugin settings.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImportExport {
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
	}

	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Import/Export', 'clandevs-smart-catalog-filters-pro' ),
			__( 'Import/Export', 'clandevs-smart-catalog-filters-pro' ),
			'manage_woocommerce',
			'cscf-import-export',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import/Export Settings', 'clandevs-smart-catalog-filters-pro' ) . '</h1>';

		// Export.
		if ( isset( $_GET['cscf_export'] ) && check_admin_referer( 'cscf_export_settings' ) ) {
			$settings = array(
				'filter'  => \ClandevsSmartCatalogFilters\FilterSettings::get_options(),
				'style'   => \ClandevsSmartCatalogFilters\StyleSettings::get_options(),
				'analytics' => get_option( 'wf_analytics_data', array() ),
				'presets'  => get_option( 'cscf_presets', array() ),
				'swatches' => get_option( 'cscf_visual_swatches', array() ),
				'order'    => get_option( 'cscf_filter_order', array() ),
			);
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=cscf-settings-' . gmdate( 'Y-m-d' ) . '.json' );
			echo wp_json_encode( $settings, JSON_PRETTY_PRINT );
			exit;
		}

		// Import.
		if ( isset( $_POST['cscf_import_nonce'] ) && check_admin_referer( 'cscf_import_settings', 'cscf_import_nonce' ) ) {
			if ( ! empty( $_FILES['cscf_import_file']['tmp_name'] ) ) {
				$json = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['cscf_import_file']['tmp_name'] ) ) );
				$data = json_decode( $json, true );
				if ( is_array( $data ) ) {
					if ( isset( $data['filter'] ) ) {
						update_option( 'wf_filter_options', $data['filter'] );
					}
					if ( isset( $data['style'] ) ) {
						update_option( 'wf_style_options', $data['style'] );
					}
					if ( isset( $data['presets'] ) ) {
						update_option( 'cscf_presets', $data['presets'] );
					}
					if ( isset( $data['swatches'] ) ) {
						update_option( 'cscf_visual_swatches', $data['swatches'] );
					}
					if ( isset( $data['order'] ) ) {
						update_option( 'cscf_filter_order', $data['order'] );
					}
					echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings imported successfully.', 'clandevs-smart-catalog-filters-pro' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid JSON file.', 'clandevs-smart-catalog-filters-pro' ) . '</p></div>';
				}
			}
		}

		echo '<h2>' . esc_html__( 'Export', 'clandevs-smart-catalog-filters-pro' ) . '</h2>';
		echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'cscf_export', '1' ), 'cscf_export_settings' ) ) . '" class="button">' . esc_html__( 'Download Settings JSON', 'clandevs-smart-catalog-filters-pro' ) . '</a>';

		echo '<h2>' . esc_html__( 'Import', 'clandevs-smart-catalog-filters-pro' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'cscf_import_settings', 'cscf_import_nonce' );
		echo '<input type="file" name="cscf_import_file" accept=".json" />';
		echo ' <button class="button button-primary">' . esc_html__( 'Import Settings', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}
}
