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

/**
 * Records a day-by-day filter usage series and reports on it.
 *
 * The free plugin only stores lifetime totals, so this class keeps its own
 * dated series to make date-range reporting and trend charts possible.
 */
class AdvancedAnalytics {

	/**
	 * Option holding the daily series.
	 */
	private const OPTION_DAILY = 'cscfp_analytics_daily';

	/**
	 * Transient holding unflushed events.
	 */
	private const BUFFER_KEY = 'cscfp_analytics_buffer';

	/**
	 * Cron hook used to flush the buffer.
	 */
	private const FLUSH_HOOK = 'cscfp_flush_daily_analytics';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-advanced-analytics';

	/**
	 * Days of history retained.
	 */
	private const RETENTION_DAYS = 365;

	/**
	 * Buffered events before a flush is forced.
	 */
	private const BUFFER_LIMIT = 10;

	/**
	 * Distinct values kept per filter type per day.
	 */
	private const MAX_VALUES_PER_TYPE = 100;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'pre_get_posts', array( $this, 'track_request' ), 31 );
		add_action( self::FLUSH_HOOK, array( $this, 'flush_buffer' ) );
		add_action( 'admin_post_cscf_export_analytics_csv', array( $this, 'export_csv' ) );
	}

	/**
	 * Register the reporting submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Advanced Analytics', 'clandevs-smart-catalog-filters' ),
			__( 'Advanced Analytics', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/*
	 * Collection.
	 */

	/**
	 * Record filter usage for the current catalog request.
	 *
	 * @param \WP_Query $query Query being prepared.
	 * @return void
	 */
	public function track_request( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || wp_doing_ajax() ) {
			return;
		}

		// Conditional tags are not reliable this early, so inspect the query itself.
		if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		$filters = $this->get_request_filters();

		if ( empty( $filters ) ) {
			return;
		}

		$buffer = get_transient( self::BUFFER_KEY );

		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}

		$buffer[] = array(
			'date'    => gmdate( 'Y-m-d' ),
			'filters' => $filters,
		);

		if ( count( $buffer ) >= self::BUFFER_LIMIT ) {
			delete_transient( self::BUFFER_KEY );
			$this->flush_buffer( $buffer );
			return;
		}

		set_transient( self::BUFFER_KEY, $buffer, 300 );

		if ( ! wp_next_scheduled( self::FLUSH_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::FLUSH_HOOK );
		}
	}

	/**
	 * Fold buffered events into the stored daily series.
	 *
	 * @param array|null $buffer Events to flush, or null to read the transient.
	 * @return void
	 */
	public function flush_buffer( ?array $buffer = null ): void {
		if ( null === $buffer ) {
			$buffer = get_transient( self::BUFFER_KEY );

			if ( ! is_array( $buffer ) || empty( $buffer ) ) {
				return;
			}

			delete_transient( self::BUFFER_KEY );
		}

		$series = $this->get_series();

		foreach ( $buffer as $event ) {
			if ( ! isset( $event['date'], $event['filters'] ) || ! is_array( $event['filters'] ) ) {
				continue;
			}

			$date = $this->normalize_date( (string) $event['date'] );

			if ( '' === $date ) {
				continue;
			}

			if ( ! isset( $series[ $date ] ) || ! is_array( $series[ $date ] ) ) {
				$series[ $date ] = array(
					'events'  => 0,
					'filters' => array(),
				);
			}

			++$series[ $date ]['events'];

			foreach ( $event['filters'] as $type => $values ) {
				$type_key = sanitize_key( (string) $type );

				if ( '' === $type_key ) {
					continue;
				}

				if ( ! isset( $series[ $date ]['filters'][ $type_key ] ) ) {
					$series[ $date ]['filters'][ $type_key ] = array();
				}

				foreach ( (array) $values as $value ) {
					$value_key = sanitize_title( (string) $value );

					if ( '' === $value_key ) {
						continue;
					}

					$current = isset( $series[ $date ]['filters'][ $type_key ][ $value_key ] )
						? absint( $series[ $date ]['filters'][ $type_key ][ $value_key ] )
						: 0;

					$series[ $date ]['filters'][ $type_key ][ $value_key ] = $current + 1;
				}

				if ( count( $series[ $date ]['filters'][ $type_key ] ) > self::MAX_VALUES_PER_TYPE ) {
					arsort( $series[ $date ]['filters'][ $type_key ] );
					$series[ $date ]['filters'][ $type_key ] = array_slice(
						$series[ $date ]['filters'][ $type_key ],
						0,
						self::MAX_VALUES_PER_TYPE,
						true
					);
				}
			}
		}

		update_option( self::OPTION_DAILY, $this->prune( $series ), false );
	}

	/**
	 * Extract the filter selections present in the current request.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function get_request_filters(): array {
		$simple = array(
			'cscf_cat'      => 'category',
			'cscf_brand'    => 'brand',
			'cscf_color'    => 'color',
			'cscf_in_stock' => 'in_stock',
			'cscf_on_sale'  => 'on_sale',
			'min_price'     => 'min_price',
			'max_price'     => 'max_price',
			'rating_filter' => 'rating',
		);

		$filters = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		$request = wp_unslash( $_GET );

		foreach ( $simple as $key => $label ) {
			if ( ! isset( $request[ $key ] ) ) {
				continue;
			}

			$values = $this->flatten_request_value( $request[ $key ] );

			if ( ! empty( $values ) ) {
				$filters[ $label ] = $values;
			}
		}

		foreach ( $request as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( 0 !== strpos( $key, 'cscf_attr_' ) ) {
				continue;
			}

			$values = $this->flatten_request_value( $value );

			if ( ! empty( $values ) ) {
				$filters[ $key ] = $values;
			}
		}

		return $filters;
	}

	/**
	 * Reduce a request value to a list of clean scalar strings.
	 *
	 * @param mixed $value Raw request value.
	 * @return array<int, string>
	 */
	private function flatten_request_value( $value ): array {
		$values = array();

		foreach ( (array) $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$item = sanitize_text_field( (string) $item );

			if ( '' !== $item ) {
				$values[] = $item;
			}
		}

		return array_values( array_unique( $values ) );
	}

	/*
	 * Reporting.
	 */

	/**
	 * Render the reporting page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$range   = $this->get_requested_range();
		$series  = $this->get_series_for_range( $range['from'], $range['to'] );
		$totals  = $this->summarize( $series );
		$daily   = $this->fill_daily_totals( $series, $range['from'], $range['to'] );
		$max_day = $daily ? max( $daily ) : 0;

		echo '<div class="wrap cscfp-analytics">';
		echo '<h1>' . esc_html__( 'Advanced Analytics', 'clandevs-smart-catalog-filters' ) . '</h1>';

		echo '<form method="get" class="cscfp-range-form">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		echo '<label for="cscf_preset">' . esc_html__( 'Range', 'clandevs-smart-catalog-filters' ) . '</label> ';
		echo '<select id="cscf_preset" name="cscf_preset">';

		foreach ( self::get_range_presets() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $this->get_requested_preset(), $key, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select> ';
		echo '<label for="cscf_from">' . esc_html__( 'From', 'clandevs-smart-catalog-filters' ) . '</label> ';
		echo '<input type="date" id="cscf_from" name="cscf_from" value="' . esc_attr( $range['from'] ) . '" /> ';
		echo '<label for="cscf_to">' . esc_html__( 'To', 'clandevs-smart-catalog-filters' ) . '</label> ';
		echo '<input type="date" id="cscf_to" name="cscf_to" value="' . esc_attr( $range['to'] ) . '" /> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Apply', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</form>';

		echo '<p class="cscfp-summary">';
		printf(
			/* translators: 1: number of filter events, 2: start date, 3: end date. */
			esc_html__( '%1$s filter events between %2$s and %3$s.', 'clandevs-smart-catalog-filters' ),
			esc_html( number_format_i18n( $totals['events'] ) ),
			esc_html( $range['from'] ),
			esc_html( $range['to'] )
		);
		echo '</p>';

		echo '<h2>' . esc_html__( 'Period Comparison', 'clandevs-smart-catalog-filters' ) . '</h2>';
		$this->render_period_comparison( $range, $totals['events'] );

		echo '<h2>' . esc_html__( 'Filter Usage Trend', 'clandevs-smart-catalog-filters' ) . '</h2>';
		$this->render_trend_chart( $daily, $max_day );

		echo '<h2>' . esc_html__( 'Top Filters', 'clandevs-smart-catalog-filters' ) . '</h2>';
		$this->render_top_filters_table( $totals['filters'] );

		echo '<h2>' . esc_html__( 'Export', 'clandevs-smart-catalog-filters' ) . '</h2>';
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'cscf_export_analytics_csv',
					'cscf_from' => $range['from'],
					'cscf_to'   => $range['to'],
				),
				admin_url( 'admin-post.php' )
			),
			'cscf_export_analytics'
		);
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button button-primary">' . esc_html__( 'Download CSV', 'clandevs-smart-catalog-filters' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Available quick ranges for the report.
	 *
	 * @return array<string, string>
	 */
	public static function get_range_presets(): array {
		return array(
			'last_7'  => __( 'Last 7 days', 'clandevs-smart-catalog-filters' ),
			'last_30' => __( 'Last 30 days', 'clandevs-smart-catalog-filters' ),
			'last_90' => __( 'Last 90 days', 'clandevs-smart-catalog-filters' ),
			'custom'  => __( 'Custom range', 'clandevs-smart-catalog-filters' ),
		);
	}

	/**
	 * Compute the period immediately preceding a range, of the same length.
	 *
	 * @param string $from Start date, Y-m-d.
	 * @param string $to   End date, Y-m-d.
	 * @return array{from:string,to:string} Empty strings when the range is invalid.
	 */
	public static function get_previous_period( string $from, string $to ): array {
		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', $from );
		$end   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $to );

		if ( false === $start || false === $end || $start > $end ) {
			return array(
				'from' => '',
				'to'   => '',
			);
		}

		$length        = (int) $start->diff( $end )->days + 1;
		$previous_end  = $start->modify( '-1 day' );
		$previous_from = $previous_end->modify( '-' . ( $length - 1 ) . ' days' );

		return array(
			'from' => $previous_from->format( 'Y-m-d' ),
			'to'   => $previous_end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Percentage change between two totals.
	 *
	 * @param int $current  Current period total.
	 * @param int $previous Previous period total.
	 * @return float|null Null when there is no baseline to compare against.
	 */
	public static function get_percentage_change( int $current, int $previous ): ?float {
		if ( $previous <= 0 ) {
			return null;
		}

		return ( ( $current - $previous ) / $previous ) * 100;
	}

	/**
	 * Render the current period against the one before it.
	 *
	 * @param array{from:string,to:string} $range   Current range.
	 * @param int                          $current Current period event total.
	 * @return void
	 */
	private function render_period_comparison( array $range, int $current ): void {
		$previous = self::get_previous_period( $range['from'], $range['to'] );

		if ( '' === $previous['from'] ) {
			return;
		}

		$previous_total = $this->summarize( $this->get_series_for_range( $previous['from'], $previous['to'] ) )['events'];
		$change         = self::get_percentage_change( $current, $previous_total );

		echo '<table class="widefat striped cscfp-comparison"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'This period', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td>' . esc_html( $range['from'] . ' – ' . $range['to'] ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( $current ) ) . '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Previous period', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td>' . esc_html( $previous['from'] . ' – ' . $previous['to'] ) . '</td>';
		echo '<td>' . esc_html( number_format_i18n( $previous_total ) ) . '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Change', 'clandevs-smart-catalog-filters' ) . '</th><td></td><td>';

		if ( null === $change ) {
			echo '&mdash;';
		} else {
			$class = $change >= 0 ? 'cscfp-up' : 'cscfp-down';
			echo '<span class="' . esc_attr( $class ) . '">' . esc_html( sprintf( '%+.1f%%', $change ) ) . '</span>';
		}

		echo '</td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Render the daily trend as an inline SVG bar chart.
	 *
	 * No charting library is loaded; the markup is self-contained.
	 *
	 * @param array<string, int> $daily   Event count keyed by date.
	 * @param int                $max_day Highest single-day count.
	 * @return void
	 */
	private function render_trend_chart( array $daily, int $max_day ): void {
		if ( empty( $daily ) || $max_day <= 0 ) {
			echo '<p>' . esc_html__( 'No filter activity recorded in this range yet.', 'clandevs-smart-catalog-filters' ) . '</p>';
			return;
		}

		$width     = 720;
		$height    = 180;
		$count     = count( $daily );
		$slot      = $width / max( 1, $count );
		$bar_width = max( 1.0, $slot * 0.7 );
		$index     = 0;

		echo '<div class="cscfp-chart">';
		echo '<svg viewBox="0 0 ' . esc_attr( (string) $width ) . ' ' . esc_attr( (string) $height ) . '" role="img" preserveAspectRatio="none" aria-label="' . esc_attr__( 'Daily filter usage', 'clandevs-smart-catalog-filters' ) . '">';

		foreach ( $daily as $date => $value ) {
			$bar_height = $value > 0 ? ( $value / $max_day ) * ( $height - 20 ) : 0;
			$x          = ( $index * $slot ) + ( ( $slot - $bar_width ) / 2 );
			$y          = $height - $bar_height;

			echo '<rect class="cscfp-bar" x="' . esc_attr( (string) round( $x, 2 ) ) . '" y="' . esc_attr( (string) round( $y, 2 ) ) . '" width="' . esc_attr( (string) round( $bar_width, 2 ) ) . '" height="' . esc_attr( (string) round( $bar_height, 2 ) ) . '">';
			echo '<title>' . esc_html( $date . ' — ' . number_format_i18n( $value ) ) . '</title>';
			echo '</rect>';

			++$index;
		}

		echo '</svg>';
		echo '<p class="cscfp-chart-axis"><span>' . esc_html( (string) array_key_first( $daily ) ) . '</span><span>' . esc_html( (string) array_key_last( $daily ) ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * Render the aggregated top filters table.
	 *
	 * @param array<string, array<string, int>> $filters Counts keyed by type then value.
	 * @return void
	 */
	private function render_top_filters_table( array $filters ): void {
		$rows = $this->flatten_filter_counts( $filters );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No filter selections recorded in this range yet.', 'clandevs-smart-catalog-filters' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped cscfp-top-filters">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Filter', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<th>' . esc_html__( 'Value', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<th>' . esc_html__( 'Uses', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 50 ) as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $this->humanize( $row['type'] ) ) . '</td>';
			echo '<td>' . esc_html( $this->humanize( $row['value'] ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row['count'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Stream the selected range as CSV.
	 *
	 * @return void
	 */
	public function export_csv(): void {
		check_admin_referer( 'cscf_export_analytics' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to export analytics.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		$range  = $this->get_requested_range();
		$series = $this->get_series_for_range( $range['from'], $range['to'] );
		$totals = $this->summarize( $series );
		$daily  = $this->fill_daily_totals( $series, $range['from'], $range['to'] );

		$lines   = array();
		$lines[] = array( 'Section', 'Key', 'Value', 'Count' );

		foreach ( $daily as $date => $events ) {
			$lines[] = array( 'daily', $date, '', (string) $events );
		}

		foreach ( $this->flatten_filter_counts( $totals['filters'] ) as $row ) {
			$lines[] = array( 'filter', $row['type'], $row['value'], (string) $row['count'] );
		}

		$csv = '';

		foreach ( $lines as $line ) {
			$csv .= implode( ',', array_map( array( $this, 'escape_csv_field' ), $line ) ) . "\r\n";
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cscf-analytics-' . $range['from'] . '-to-' . $range['to'] . '.csv' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, each field escaped by escape_csv_field().
		exit;
	}

	/**
	 * Quote a CSV field and neutralize spreadsheet formula injection.
	 *
	 * @param string $value Raw field value.
	 * @return string
	 */
	private function escape_csv_field( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}

		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/*
	 * Data helpers.
	 */

	/**
	 * Read the stored daily series.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_series(): array {
		$series = get_option( self::OPTION_DAILY, array() );

		return is_array( $series ) ? $series : array();
	}

	/**
	 * Read the stored series limited to a date range.
	 *
	 * @param string $from Start date, Y-m-d.
	 * @param string $to   End date, Y-m-d.
	 * @return array<string, array<string, mixed>>
	 */
	private function get_series_for_range( string $from, string $to ): array {
		$series = array();

		foreach ( $this->get_series() as $date => $entry ) {
			$date = $this->normalize_date( (string) $date );

			if ( '' === $date || $date < $from || $date > $to || ! is_array( $entry ) ) {
				continue;
			}

			$series[ $date ] = $entry;
		}

		ksort( $series );

		return $series;
	}

	/**
	 * Total events and filter counts across a series.
	 *
	 * @param array<string, array<string, mixed>> $series Daily series.
	 * @return array{events:int,filters:array<string, array<string, int>>}
	 */
	private function summarize( array $series ): array {
		$events  = 0;
		$filters = array();

		foreach ( $series as $entry ) {
			$events += isset( $entry['events'] ) ? absint( $entry['events'] ) : 0;

			if ( ! isset( $entry['filters'] ) || ! is_array( $entry['filters'] ) ) {
				continue;
			}

			foreach ( $entry['filters'] as $type => $values ) {
				foreach ( (array) $values as $value => $count ) {
					$current                    = isset( $filters[ $type ][ $value ] ) ? $filters[ $type ][ $value ] : 0;
					$filters[ $type ][ $value ] = $current + absint( $count );
				}
			}
		}

		return array(
			'events'  => $events,
			'filters' => $filters,
		);
	}

	/**
	 * Build a continuous date => count map across the range.
	 *
	 * @param array<string, array<string, mixed>> $series Daily series.
	 * @param string                              $from   Start date, Y-m-d.
	 * @param string                              $to     End date, Y-m-d.
	 * @return array<string, int>
	 */
	private function fill_daily_totals( array $series, string $from, string $to ): array {
		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', $from );
		$end   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $to );

		if ( false === $start || false === $end || $start > $end ) {
			return array();
		}

		// Guard against an absurd range producing a huge chart.
		if ( $start->diff( $end )->days > self::RETENTION_DAYS ) {
			$start = $end->modify( '-' . self::RETENTION_DAYS . ' days' );
		}

		$daily  = array();
		$cursor = $start;

		while ( $cursor <= $end ) {
			$key           = $cursor->format( 'Y-m-d' );
			$daily[ $key ] = isset( $series[ $key ]['events'] ) ? absint( $series[ $key ]['events'] ) : 0;
			$cursor        = $cursor->modify( '+1 day' );
		}

		return $daily;
	}

	/**
	 * Flatten nested filter counts into sorted rows.
	 *
	 * @param array<string, array<string, int>> $filters Counts keyed by type then value.
	 * @return array<int, array{type:string,value:string,count:int}>
	 */
	private function flatten_filter_counts( array $filters ): array {
		$rows = array();

		foreach ( $filters as $type => $values ) {
			foreach ( (array) $values as $value => $count ) {
				$rows[] = array(
					'type'  => (string) $type,
					'value' => (string) $value,
					'count' => absint( $count ),
				);
			}
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return $right['count'] <=> $left['count'];
			}
		);

		return $rows;
	}

	/**
	 * Drop days older than the retention window.
	 *
	 * @param array<string, array<string, mixed>> $series Daily series.
	 * @return array<string, array<string, mixed>>
	 */
	private function prune( array $series ): array {
		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

		foreach ( array_keys( $series ) as $date ) {
			if ( (string) $date < $cutoff ) {
				unset( $series[ $date ] );
			}
		}

		ksort( $series );

		return $series;
	}

	/**
	 * Resolve the requested reporting range, defaulting to the last 30 days.
	 *
	 * @return array{from:string,to:string}
	 */
	private function get_requested_range(): array {
		$default_from = gmdate( 'Y-m-d', strtotime( '-29 days' ) );
		$default_to   = gmdate( 'Y-m-d' );

		$preset = $this->get_requested_preset();

		if ( 'custom' !== $preset ) {
			$days = array(
				'last_7'  => 6,
				'last_30' => 29,
				'last_90' => 89,
			);

			if ( isset( $days[ $preset ] ) ) {
				return array(
					'from' => gmdate( 'Y-m-d', strtotime( '-' . $days[ $preset ] . ' days' ) ),
					'to'   => $default_to,
				);
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
		$from = isset( $_GET['cscf_from'] ) ? $this->normalize_date( sanitize_text_field( wp_unslash( (string) $_GET['cscf_from'] ) ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
		$to = isset( $_GET['cscf_to'] ) ? $this->normalize_date( sanitize_text_field( wp_unslash( (string) $_GET['cscf_to'] ) ) ) : '';

		$from = '' !== $from ? $from : $default_from;
		$to   = '' !== $to ? $to : $default_to;

		if ( $from > $to ) {
			$swap = $from;
			$from = $to;
			$to   = $swap;
		}

		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Read the requested quick range, defaulting to the last 30 days.
	 *
	 * @return string
	 */
	private function get_requested_preset(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
		if ( ! isset( $_GET['cscf_preset'] ) || ! is_scalar( $_GET['cscf_preset'] ) ) {
			// An explicit custom range in the URL implies the custom preset.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
			return ( isset( $_GET['cscf_from'] ) || isset( $_GET['cscf_to'] ) ) ? 'custom' : 'last_30';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filtering.
		$preset = sanitize_key( wp_unslash( (string) $_GET['cscf_preset'] ) );

		return array_key_exists( $preset, self::get_range_presets() ) ? $preset : 'last_30';
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $value Raw date.
	 * @return string Empty string when malformed.
	 */
	private function normalize_date( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $value;
	}

	/**
	 * Turn a slug into a readable label.
	 *
	 * @param string $value Slug.
	 * @return string
	 */
	private function humanize( string $value ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $value ) );
	}
}
