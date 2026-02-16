<?php
/**
 * Analytics tracking and reporting.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects filter usage stats and renders admin analytics page.
 */
final class Analytics {
	/** @var string */
	private const OPTION_KEY = 'wf_analytics_data';

	/** @var string */
	private const PAGE_SLUG = 'wf-filter-analytics';

	/** @var string */
	private const RESET_ACTION = 'wf_reset_analytics';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'pre_get_posts', array( $this, 'track_shop_filter_usage' ), 30 );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset_request' ) );
	}

	/**
	 * Register analytics submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Woo Filters Analytics', 'woo-filters' ),
			__( 'Woo Filters Analytics', 'woo-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Track active filter usage from main shop archive queries.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public function track_shop_filter_usage( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $this->is_product_archive_query( $query ) ) {
			return;
		}

		$filters = $this->get_request_filters();
		if ( empty( $filters ) ) {
			return;
		}

		if ( ! $this->is_filter_request_nonce_valid() ) {
			return;
		}

		$this->persist_filter_event( $filters );
	}

	/**
	 * Handle analytics reset request.
	 *
	 * @return void
	 */
	public function handle_reset_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Woo Filters analytics.', 'woo-filters' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		delete_option( self::OPTION_KEY );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render analytics page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$stats              = $this->get_stats();
		$total_events       = isset( $stats['total_events'] ) ? absint( $stats['total_events'] ) : 0;
		$distinct_filters   = $this->count_distinct_filters( $stats );
		$last_event_display = $this->format_timestamp_for_admin( isset( $stats['last_event_gmt'] ) ? (string) $stats['last_event_gmt'] : '' );
		$rows               = $this->get_sorted_filter_rows( $stats );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Woo Filters Analytics', 'woo-filters' ) . '</h1>';

		if ( isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( (string) $_GET['updated'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Analytics data has been reset.', 'woo-filters' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Usage data updates when customers apply filters on the shop archive.', 'woo-filters' ) . '</p>';
		echo '<table class="widefat striped" style="max-width: 820px; margin-bottom: 24px;">';
		echo '<tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Total Filter Events', 'woo-filters' ) . '</th><td>' . esc_html( (string) $total_events ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Distinct Filter Values', 'woo-filters' ) . '</th><td>' . esc_html( (string) $distinct_filters ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Last Event', 'woo-filters' ) . '</th><td>' . esc_html( $last_event_display ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';

		echo '<h2>' . esc_html__( 'Top Used Filters', 'woo-filters' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width: 820px;">';
		echo '<thead><tr><th>' . esc_html__( 'Filter Type', 'woo-filters' ) . '</th><th>' . esc_html__( 'Value', 'woo-filters' ) . '</th><th>' . esc_html__( 'Events', 'woo-filters' ) . '</th></tr></thead>';
		echo '<tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No analytics data yet.', 'woo-filters' ) . '</td></tr>';
		} else {
			foreach ( array_slice( $rows, 0, 100 ) as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( (string) $row['type'] ) . '</td>';
				echo '<td>' . esc_html( (string) $row['value'] ) . '</td>';
				echo '<td>' . esc_html( (string) $row['count'] ) . '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';

		$reset_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::RESET_ACTION,
				),
				admin_url( 'admin-post.php' )
			),
			self::RESET_ACTION
		);

		echo '<p style="margin-top: 16px;">';
		echo '<a href="' . esc_url( $reset_url ) . '" class="button">' . esc_html__( 'Reset Analytics Data', 'woo-filters' ) . '</a>';
		echo '</p>';
		echo '</div>';
	}

	/**
	 * Check whether request targets product archive.
	 *
	 * @param \WP_Query $query Query object.
	 * @return bool
	 */
	private function is_product_archive_query( \WP_Query $query ): bool {
		return (bool) ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) );
	}

	/**
	 * Determine whether current filter request carries a valid nonce.
	 *
	 * @return bool
	 */
	private function is_filter_request_nonce_valid(): bool {
		if ( ! $this->has_filter_parameters() ) {
			return true;
		}

		if ( ! isset( $_GET['wf_nonce'] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['wf_nonce'] ) );

		return (bool) wp_verify_nonce( $nonce, 'wf_filter_request' );
	}

	/**
	 * Check if request has filter-related keys.
	 *
	 * @return bool
	 */
	private function has_filter_parameters(): bool {
		foreach ( array_keys( $_GET ) as $key ) {
			$normalized = sanitize_key( (string) $key );
			if ( in_array( $normalized, $this->get_supported_filter_keys(), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return supported filter key list.
	 *
	 * @return array
	 */
	private function get_supported_filter_keys(): array {
		return array(
			'wf_cat',
			'wf_brand',
			'wf_color',
			'wf_logic',
			'min_price',
			'max_price',
			'rating_filter',
			'wf_in_stock',
			'wf_on_sale',
		);
	}

	/**
	 * Extract normalized active filter payload from request.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function get_request_filters(): array {
		$filters = array();

		$category = $this->get_request_slug( 'wf_cat' );
		if ( '' !== $category ) {
			$filters['category'] = array( $category );
		}

		$brands = $this->get_request_slug_list( 'wf_brand' );
		if ( ! empty( $brands ) ) {
			$filters['brand'] = $brands;
		}

		$colors = $this->get_request_slug_list( 'wf_color' );
		if ( ! empty( $colors ) ) {
			$filters['color'] = $colors;
		}

		$logic = $this->get_request_logic_mode();
		if ( 'and' === $logic ) {
			$filters['logic'] = array( 'and' );
		}

		$rating = $this->get_request_absint( 'rating_filter' );
		if ( $rating > 0 && $rating <= 5 ) {
			$filters['rating'] = array( (string) $rating );
		}

		$min_price = $this->get_request_decimal( 'min_price' );
		if ( null !== $min_price ) {
			$filters['min_price'] = array( $this->format_decimal( $min_price ) );
		}

		$max_price = $this->get_request_decimal( 'max_price' );
		if ( null !== $max_price ) {
			$filters['max_price'] = array( $this->format_decimal( $max_price ) );
		}

		if ( $this->get_request_flag( 'wf_in_stock' ) ) {
			$filters['availability'] = array( 'in_stock' );
		}

		if ( $this->get_request_flag( 'wf_on_sale' ) ) {
			if ( ! isset( $filters['availability'] ) ) {
				$filters['availability'] = array();
			}
			$filters['availability'][] = 'on_sale';
		}

		return $filters;
	}

	/**
	 * Persist one filter event.
	 *
	 * @param array $filters Filter payload.
	 * @return void
	 */
	private function persist_filter_event( array $filters ): void {
		$stats                 = $this->get_stats();
		$stats['total_events'] = isset( $stats['total_events'] ) ? absint( $stats['total_events'] ) + 1 : 1;
		$stats['last_event_gmt'] = gmdate( 'Y-m-d H:i:s' );

		if ( ! isset( $stats['filters'] ) || ! is_array( $stats['filters'] ) ) {
			$stats['filters'] = array();
		}

		foreach ( $filters as $type => $values ) {
			$type_key = sanitize_key( (string) $type );
			if ( '' === $type_key ) {
				continue;
			}

			if ( ! isset( $stats['filters'][ $type_key ] ) || ! is_array( $stats['filters'][ $type_key ] ) ) {
				$stats['filters'][ $type_key ] = array();
			}

			foreach ( $values as $value ) {
				$value_key = sanitize_title( (string) $value );
				if ( '' === $value_key ) {
					continue;
				}

				$current_count                               = isset( $stats['filters'][ $type_key ][ $value_key ] ) ? absint( $stats['filters'][ $type_key ][ $value_key ] ) : 0;
				$stats['filters'][ $type_key ][ $value_key ] = $current_count + 1;
			}
		}

		update_option( self::OPTION_KEY, $stats, false );
	}

	/**
	 * Get analytics data with defaults.
	 *
	 * @return array
	 */
	private function get_stats(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = array(
			'total_events'   => 0,
			'last_event_gmt' => '',
			'filters'        => array(),
		);

		return array_merge( $defaults, $stored );
	}

	/**
	 * Count number of distinct filter values.
	 *
	 * @param array $stats Analytics stats.
	 * @return int
	 */
	private function count_distinct_filters( array $stats ): int {
		if ( ! isset( $stats['filters'] ) || ! is_array( $stats['filters'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $stats['filters'] as $values ) {
			if ( is_array( $values ) ) {
				$count += count( $values );
			}
		}

		return $count;
	}

	/**
	 * Flatten and sort filter rows by usage.
	 *
	 * @param array $stats Analytics stats.
	 * @return array
	 */
	private function get_sorted_filter_rows( array $stats ): array {
		if ( ! isset( $stats['filters'] ) || ! is_array( $stats['filters'] ) ) {
			return array();
		}

		$rows = array();
		foreach ( $stats['filters'] as $type => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			foreach ( $values as $value => $count ) {
				$rows[] = array(
					'type'  => $this->humanize_key( (string) $type ),
					'value' => $this->humanize_key( (string) $value ),
					'count' => absint( $count ),
				);
			}
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return (int) $right['count'] <=> (int) $left['count'];
			}
		);

		return $rows;
	}

	/**
	 * Format timestamp for admin timezone.
	 *
	 * @param string $timestamp_gmt Timestamp in GMT.
	 * @return string
	 */
	private function format_timestamp_for_admin( string $timestamp_gmt ): string {
		if ( '' === $timestamp_gmt ) {
			return __( 'No data yet', 'woo-filters' );
		}

		$unix = strtotime( $timestamp_gmt . ' UTC' );
		if ( false === $unix ) {
			return __( 'Unknown', 'woo-filters' );
		}

		return wp_date(
			sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
			$unix
		);
	}

	/**
	 * Humanize key for admin table output.
	 *
	 * @param string $value Raw key.
	 * @return string
	 */
	private function humanize_key( string $value ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
	}

	/**
	 * Parse request slug.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private function get_request_slug( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_title( wp_unslash( (string) $_GET[ $key ] ) );
	}

	/**
	 * Parse request values as slug list.
	 *
	 * @param string $key Query key.
	 * @return array
	 */
	private function get_request_slug_list( string $key ): array {
		if ( ! isset( $_GET[ $key ] ) ) {
			return array();
		}

		$raw = $_GET[ $key ];
		if ( is_array( $raw ) ) {
			$values = array_map(
				static function ( $item ) {
					return sanitize_title( wp_unslash( (string) $item ) );
				},
				$raw
			);
		} else {
			$values = array_map(
				'sanitize_title',
				explode( ',', sanitize_text_field( wp_unslash( (string) $raw ) ) )
			);
		}

		return array_values(
			array_unique(
				array_filter(
					$values,
					static function ( string $value ): bool {
						return '' !== $value;
					}
				)
			)
		);
	}

	/**
	 * Parse integer from request.
	 *
	 * @param string $key Query key.
	 * @return int
	 */
	private function get_request_absint( string $key ): int {
		if ( ! isset( $_GET[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( (string) $_GET[ $key ] ) );
	}

	/**
	 * Parse boolean flag from request.
	 *
	 * @param string $key Query key.
	 * @return bool
	 */
	private function get_request_flag( string $key ): bool {
		return 1 === $this->get_request_absint( $key );
	}

	/**
	 * Parse multi-select logic mode from request.
	 *
	 * @return string
	 */
	private function get_request_logic_mode(): string {
		if ( ! isset( $_GET['wf_logic'] ) ) {
			return 'or';
		}

		$value = sanitize_key( wp_unslash( (string) $_GET['wf_logic'] ) );

		return 'and' === $value ? 'and' : 'or';
	}

	/**
	 * Parse decimal request value.
	 *
	 * @param string $key Query key.
	 * @return float|null
	 */
	private function get_request_decimal( string $key ): ?float {
		if ( ! isset( $_GET[ $key ] ) ) {
			return null;
		}

		$raw = wc_format_decimal( wp_unslash( (string) $_GET[ $key ] ) );
		if ( '' === (string) $raw ) {
			return null;
		}

		$value = (float) $raw;
		if ( $value < 0 ) {
			return 0.0;
		}

		return $value;
	}

	/**
	 * Format decimal for stable analytics keys.
	 *
	 * @param float $value Decimal value.
	 * @return string
	 */
	private function format_decimal( float $value ): string {
		$formatted = rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );

		return '' !== $formatted ? $formatted : '0';
	}
}
