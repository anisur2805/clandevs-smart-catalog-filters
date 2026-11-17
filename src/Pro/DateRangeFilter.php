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

/**
 * Filters the catalog by product publish date.
 */
class DateRangeFilter {

	/**
	 * Option holding the enabled flag.
	 */
	private const OPTION_ENABLED = 'cscf_date_range_enabled';

	/**
	 * Request key for the start of the range.
	 */
	private const KEY_FROM = 'cscf_date_from';

	/**
	 * Request key for the end of the range.
	 */
	private const KEY_TO = 'cscf_date_to';

	/**
	 * Request key for the quick range preset.
	 */
	private const KEY_PRESET = 'cscf_date_preset';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-date-range';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_cscf_save_date_range', array( $this, 'handle_save' ) );
		add_filter( 'cscf_filter_sections', array( $this, 'add_filter_section' ), 10, 2 );
		add_filter( 'cscf_preserved_excluded_keys', array( $this, 'exclude_preserved_keys' ) );
		add_filter( 'cscf_query_args', array( $this, 'modify_query' ), 10, 2 );
	}

	/**
	 * Register the settings submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Date Range Filter', 'clandevs-smart-catalog-filters' ),
			__( 'Date Range Filter', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$enabled = $this->is_enabled();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Date Range Filter', 'clandevs-smart-catalog-filters' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag rendered after a nonce-checked redirect.
		if ( isset( $_GET['cscf-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
		}

		echo '<p>' . esc_html__( 'Let shoppers narrow the catalog to products published within a date range.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_save_date_range' );
		echo '<input type="hidden" name="action" value="cscf_save_date_range" />';
		echo '<table class="form-table"><tbody><tr>';
		echo '<th scope="row">' . esc_html__( 'Availability', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td><label><input type="checkbox" name="enabled" value="yes" ' . checked( $enabled, true, false ) . ' /> ';
		echo esc_html__( 'Show the date range filter in the filter sidebar', 'clandevs-smart-catalog-filters' ) . '</label></td>';
		echo '</tr></tbody></table>';
		submit_button( __( 'Save Changes', 'clandevs-smart-catalog-filters' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Persist the settings form.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( 'cscf_save_date_range' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		update_option( self::OPTION_ENABLED, isset( $_POST['enabled'] ) ? 'yes' : 'no' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'cscf-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Inject the date range block into the filter form.
	 *
	 * @param array $sections       Section renderers keyed by section name.
	 * @param array $filter_options Current filter visibility options.
	 * @return array
	 */
	public function add_filter_section( array $sections, array $filter_options ): array {
		unset( $filter_options );

		if ( ! $this->is_enabled() ) {
			return $sections;
		}

		$sections['date_range'] = function () {
			$from   = $this->get_request_date( self::KEY_FROM );
			$to     = $this->get_request_date( self::KEY_TO );
			$preset = $this->get_request_preset();

			echo '<div class="wf-filter-block cscf-date-range-block">';
			echo '<h4>' . esc_html__( 'Date Added', 'clandevs-smart-catalog-filters' ) . '</h4>';

			echo '<label class="cscf-date-field cscf-date-preset-field"><span class="screen-reader-text">';
			echo esc_html__( 'Quick range', 'clandevs-smart-catalog-filters' ) . '</span>';
			echo '<select name="' . esc_attr( self::KEY_PRESET ) . '" class="cscf-date-preset">';

			foreach ( self::get_presets() as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $preset, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}

			echo '</select></label>';

			$hide_custom = ( '' !== $preset && 'custom' !== $preset );

			echo '<div class="cscf-date-custom"' . ( $hide_custom ? ' hidden' : '' ) . '>';
			echo '<label class="cscf-date-field"><span>' . esc_html__( 'From', 'clandevs-smart-catalog-filters' ) . '</span>';
			echo '<input type="date" name="' . esc_attr( self::KEY_FROM ) . '" value="' . esc_attr( $from ) . '" /></label>';
			echo '<label class="cscf-date-field"><span>' . esc_html__( 'To', 'clandevs-smart-catalog-filters' ) . '</span>';
			echo '<input type="date" name="' . esc_attr( self::KEY_TO ) . '" value="' . esc_attr( $to ) . '" /></label>';
			echo '</div>';

			echo '</div>';
		};

		return $sections;
	}

	/**
	 * Keep the date keys out of the preserved hidden fields.
	 *
	 * @param array $keys Excluded request keys.
	 * @return array
	 */
	public function exclude_preserved_keys( array $keys ): array {
		$keys[] = self::KEY_FROM;
		$keys[] = self::KEY_TO;
		$keys[] = self::KEY_PRESET;

		return $keys;
	}

	/**
	 * Available quick ranges.
	 *
	 * @return array<string, string>
	 */
	public static function get_presets(): array {
		return array(
			''          => __( 'Any time', 'clandevs-smart-catalog-filters' ),
			'last_7'    => __( 'Last 7 days', 'clandevs-smart-catalog-filters' ),
			'last_30'   => __( 'Last 30 days', 'clandevs-smart-catalog-filters' ),
			'last_90'   => __( 'Last 90 days', 'clandevs-smart-catalog-filters' ),
			'this_year' => __( 'This year', 'clandevs-smart-catalog-filters' ),
			'custom'    => __( 'Custom range', 'clandevs-smart-catalog-filters' ),
		);
	}

	/**
	 * Translate a quick range into concrete dates.
	 *
	 * @param string $preset Preset key.
	 * @param string $now    Reference date, Y-m-d. Defaults to today (UTC).
	 * @return array{from:string,to:string} Empty strings when the preset is not a fixed range.
	 */
	public static function resolve_preset( string $preset, string $now = '' ): array {
		$empty = array(
			'from' => '',
			'to'   => '',
		);

		$now   = '' !== $now ? $now : gmdate( 'Y-m-d' );
		$today = \DateTimeImmutable::createFromFormat( '!Y-m-d', $now );

		if ( false === $today ) {
			return $empty;
		}

		switch ( $preset ) {
			case 'last_7':
				$days = 6;
				break;
			case 'last_30':
				$days = 29;
				break;
			case 'last_90':
				$days = 89;
				break;
			case 'this_year':
				return array(
					'from' => $today->format( 'Y' ) . '-01-01',
					'to'   => $today->format( 'Y-m-d' ),
				);
			default:
				return $empty;
		}

		return array(
			'from' => $today->modify( '-' . $days . ' days' )->format( 'Y-m-d' ),
			'to'   => $today->format( 'Y-m-d' ),
		);
	}

	/**
	 * Read the selected quick range from the request.
	 *
	 * @return string
	 */
	private function get_request_preset(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		if ( ! isset( $_GET[ self::KEY_PRESET ] ) || ! is_scalar( $_GET[ self::KEY_PRESET ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		$preset = sanitize_key( wp_unslash( (string) $_GET[ self::KEY_PRESET ] ) );

		return array_key_exists( $preset, self::get_presets() ) ? $preset : '';
	}

	/**
	 * Resolve the effective range, letting a quick range win over raw dates.
	 *
	 * @return array{from:string,to:string}
	 */
	private function get_effective_range(): array {
		$preset = $this->get_request_preset();

		if ( '' !== $preset && 'custom' !== $preset ) {
			return self::resolve_preset( $preset );
		}

		return array(
			'from' => $this->get_request_date( self::KEY_FROM ),
			'to'   => $this->get_request_date( self::KEY_TO ),
		);
	}

	/**
	 * Add the date clause to the catalog query.
	 *
	 * @param array $query_args     Query args with tax_query, meta_query, post__in.
	 * @param array $filter_options Current filter visibility options.
	 * @return array
	 */
	public function modify_query( array $query_args, array $filter_options ): array {
		unset( $filter_options );

		if ( ! $this->is_enabled() ) {
			return $query_args;
		}

		$range = $this->get_effective_range();
		$from  = $range['from'];
		$to    = $range['to'];

		if ( '' === $from && '' === $to ) {
			return $query_args;
		}

		if ( '' !== $from && '' !== $to && $from > $to ) {
			$swap = $from;
			$from = $to;
			$to   = $swap;
		}

		$clause = array( 'inclusive' => true );

		if ( '' !== $from ) {
			$clause['after'] = $from . ' 00:00:00';
		}

		if ( '' !== $to ) {
			$clause['before'] = $to . ' 23:59:59';
		}

		$existing   = isset( $query_args['date_query'] ) && is_array( $query_args['date_query'] ) ? $query_args['date_query'] : array();
		$existing[] = $clause;

		$query_args['date_query'] = $existing;

		return $query_args;
	}

	/**
	 * Whether the date range filter is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Read a strictly validated Y-m-d date from the request.
	 *
	 * @param string $key Request key.
	 * @return string Empty string when absent or malformed.
	 */
	private function get_request_date( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		$value = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $value;
	}
}
