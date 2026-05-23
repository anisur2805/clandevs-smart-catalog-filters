<?php
/**
 * Advanced analytics — date range, CSV export, trend charts.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdvancedAnalytics {
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cscf_export_analytics_csv', array( $this, 'export_csv' ) );
	}

	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Advanced Analytics', 'clandevs-smart-catalog-filters-pro' ),
			__( 'Advanced Analytics', 'clandevs-smart-catalog-filters-pro' ),
			'manage_woocommerce',
			'cscf-advanced-analytics',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'clandevs-smart-catalog-filters_page_cscf-advanced-analytics' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/css/pro-admin.css', array(), CSCFP_VERSION );
	}

	public function render_page(): void {
		$data = get_option( 'cscf_analytics_data', array() );
		$from = isset( $_GET['cscf_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_from'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$to   = isset( $_GET['cscf_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_to'] ) ) : gmdate( 'Y-m-d' );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Advanced Analytics', 'clandevs-smart-catalog-filters-pro' ) . '</h1>';
		echo '<form method="get"><input type="hidden" value="cscf-advanced-analytics" name="page">';
		echo '<input type="date" name="cscf_from" value="' . esc_attr( $from ) . '" />';
		echo '<input type="date" name="cscf_to" value="' . esc_attr( $to ) . '" />';
		echo '<button class="button">' . esc_html__( 'Filter', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
		echo '</form>';
		echo '<h2>' . esc_html__( 'Filter Usage Trends', 'clandevs-smart-catalog-filters-pro' ) . '</h2>';
		echo '<div id="cscf-trend-chart"></div>';
		echo '<h2>' . esc_html__( 'Export', 'clandevs-smart-catalog-filters-pro' ) . '</h2>';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=cscf_export_analytics_csv' ), 'cscf_export' ) ) . '" class="button">' . esc_html__( 'Download CSV', 'clandevs-smart-catalog-filters-pro' ) . '</a>';
		echo '</div>';
	}

	public function export_csv(): void {
		if ( ! check_admin_referer( 'cscf_export' ) ) {
			wp_die( 'Invalid nonce.' );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized.' );
		}
		$data     = get_option( 'cscf_analytics_data', array() );
		$top_data = is_array( $data ) && isset( $data['top_filters'] ) && is_array( $data['top_filters'] ) ? $data['top_filters'] : array();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cscf-analytics-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Rank', 'Filter Label', 'Uses' ) );
		$i = 0;
		foreach ( $top_data as $row ) {
			$i++;
			fputcsv( $output, array( $i, $row['label'] ?? '', $row['count'] ?? 0 ) );
		}
		fclose( $output );
		exit;
	}
}
