<?php
/**
 * Date range filter for products.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DateRangeFilter {
	public function register_hooks(): void {
		add_action( 'cscf_before_filter_render', array( $this, 'render_filter' ) );
		add_filter( 'cscf_query_args', array( $this, 'modify_query' ), 10, 2 );
	}

	public function render_filter( array $filter_options ): void {
		if ( ! isset( $filter_options['show_date_range'] ) || 'yes' !== $filter_options['show_date_range'] ) {
			return;
		}
		$from = isset( $_GET['cscf_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_date_from'] ) ) : '';
		$to   = isset( $_GET['cscf_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_date_to'] ) ) : '';
		echo '<div class="wf-filter-block cscf-date-range">';
		echo '<h4>' . esc_html__( 'Date Range', 'clandevs-smart-catalog-filters-pro' ) . '</h4>';
		echo '<label><span>' . esc_html__( 'From', 'clandevs-smart-catalog-filters-pro' ) . '</span><input type="date" name="cscf_date_from" value="' . esc_attr( $from ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'To', 'clandevs-smart-catalog-filters-pro' ) . '</span><input type="date" name="cscf_date_to" value="' . esc_attr( $to ) . '" /></label>';
		echo '</div>';
	}

	public function modify_query( array $query_args, array $filter_options ): array {
		$from = isset( $_GET['cscf_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_date_from'] ) ) : '';
		$to   = isset( $_GET['cscf_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cscf_date_to'] ) ) : '';
		if ( '' === $from && '' === $to ) {
			return $query_args;
		}
		$date_query = array();
		if ( '' !== $from ) {
			$date_query['after'] = $from;
		}
		if ( '' !== $to ) {
			$date_query['before'] = $to;
		}
		$query_args['date_query'] = array( $date_query );
		return $query_args;
	}
}
