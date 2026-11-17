<?php
/**
 * SEO-friendly filter URLs with pretty permalinks.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps /shop/category/shoes/price/50-200/ onto the query parameters the free
 * plugin already understands, so every downstream feature (chips, counts,
 * analytics, presets) keeps working unchanged.
 */
class SEOFriendlyURLs {

	/**
	 * Query var carrying the pretty filter path.
	 */
	private const QUERY_VAR = 'cscf_filter';

	/**
	 * Option remembering the base the rules were built for.
	 */
	private const OPTION_BASE = 'cscfp_rewrite_base';

	/**
	 * Option toggling pretty filter URLs.
	 */
	private const OPTION_ENABLED = 'cscfp_pretty_urls_enabled';

	/**
	 * Option toggling noindex on filtered pages.
	 */
	private const OPTION_NOINDEX = 'cscfp_filtered_noindex';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-seo-urls';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_cscf_save_seo_urls', array( $this, 'handle_save' ) );

		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'parse_request', array( $this, 'apply_pretty_filters' ) );

		// Duplicate-content controls for filtered archives.
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );
		add_action( 'wp_head', array( $this, 'render_canonical' ), 1 );
		add_filter( 'wpseo_canonical', array( $this, 'filter_seo_plugin_canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_seo_plugin_canonical' ) );
	}

	/*
	 * Settings.
	 */

	/**
	 * Register the settings submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'SEO Filter URLs', 'clandevs-smart-catalog-filters' ),
			__( 'SEO Filter URLs', 'clandevs-smart-catalog-filters' ),
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

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'SEO Filter URLs', 'clandevs-smart-catalog-filters' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag rendered after a nonce-checked redirect.
		if ( isset( $_GET['cscf-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
		}

		$base = self::get_shop_base();

		echo '<p>' . esc_html__( 'Turn filter query strings into readable paths.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<p><code>' . esc_html( '/' . $base . '/?cscf_cat=shoes&min_price=50&max_price=200' ) . '</code><br />';
		echo '<code>' . esc_html( '/' . $base . '/category/shoes/price/50-200/' ) . '</code></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_save_seo_urls' );
		echo '<input type="hidden" name="action" value="cscf_save_seo_urls" />';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Pretty filter URLs', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td><label><input type="checkbox" name="enabled" value="yes" ' . checked( self::is_enabled(), true, false ) . ' /> ';
		echo esc_html__( 'Enable pretty permalinks for filtered pages', 'clandevs-smart-catalog-filters' ) . '</label></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Duplicate content', 'clandevs-smart-catalog-filters' ) . '</th>';
		echo '<td><label><input type="checkbox" name="noindex" value="yes" ' . checked( self::is_noindex_enabled(), true, false ) . ' /> ';
		echo esc_html__( 'Add noindex to filtered pages', 'clandevs-smart-catalog-filters' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Filtered pages always emit a canonical tag pointing at the unfiltered archive. Yoast SEO and Rank Math canonicals are updated too.', 'clandevs-smart-catalog-filters' ) . '</p></td></tr>';

		echo '</tbody></table>';
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
		check_admin_referer( 'cscf_save_seo_urls' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		update_option( self::OPTION_ENABLED, isset( $_POST['enabled'] ) ? 'yes' : 'no' );
		update_option( self::OPTION_NOINDEX, isset( $_POST['noindex'] ) ? 'yes' : 'no' );

		// The rule set depends on the toggle, so rebuild it now.
		self::register_rewrite_rules();
		flush_rewrite_rules( false );

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
	 * Whether pretty filter URLs are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Whether filtered pages should be noindexed.
	 *
	 * @return bool
	 */
	public static function is_noindex_enabled(): bool {
		return 'yes' === get_option( self::OPTION_NOINDEX, 'no' );
	}

	/*
	 * Routing.
	 */

	/**
	 * Register the rewrite rule for the current shop base.
	 *
	 * @return void
	 */
	public static function register_rewrite_rules(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$base = self::get_shop_base();

		if ( '' === $base ) {
			return;
		}

		add_rewrite_rule(
			'^' . $base . '/(' . implode( '|', self::get_rule_keys() ) . ')/(.+?)/?$',
			'index.php?post_type=product&' . self::QUERY_VAR . '=$matches[1]/$matches[2]',
			'top'
		);
	}

	/**
	 * Path keys the rewrite rule reacts to.
	 *
	 * @return array<int, string>
	 */
	private static function get_rule_keys(): array {
		$keys = array( 'category', 'brand', 'color', 'price', 'rating', 'stock', 'sale' );

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
				$name = sanitize_key( $taxonomy->attribute_name );

				if ( '' !== $name && ! in_array( $name, $keys, true ) ) {
					$keys[] = $name;
				}
			}
		}

		return $keys;
	}

	/**
	 * Flush rules once when the shop base or rule set changes.
	 *
	 * @return void
	 */
	public function maybe_flush_rules(): void {
		$signature = self::get_shop_base() . '|' . ( self::is_enabled() ? '1' : '0' ) . '|' . implode( ',', self::get_rule_keys() );
		$stored    = get_option( self::OPTION_BASE, '' );

		if ( $signature === $stored ) {
			return;
		}

		update_option( self::OPTION_BASE, $signature, false );
		flush_rewrite_rules( false );
	}

	/**
	 * Register the filter query var.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Translate the pretty path into the request parameters the filters read.
	 *
	 * @param \WP $wp Current WordPress environment.
	 * @return void
	 */
	public function apply_pretty_filters( $wp ): void {
		if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}

		$path = (string) $wp->query_vars[ self::QUERY_VAR ];

		if ( '' === $path ) {
			return;
		}

		foreach ( $this->parse_path( $path ) as $key => $value ) {
			// The free plugin reads its filters from the request superglobal;
			// seeding it here keeps one parsing path for pretty and plain URLs.
			$_GET[ $key ]     = $value;
			$_REQUEST[ $key ] = $value;
		}
	}

	/**
	 * Parse a pretty filter path into request parameters.
	 *
	 * The path is a series of key/value segment pairs, matching the documented
	 * form `/shop/category/shoes/price/50-200/`. Multiple values for one key are
	 * comma separated (`color/red,blue`).
	 *
	 * @param string $path Raw path, e.g. "category/shoes/price/50-200".
	 * @return array<string, mixed>
	 */
	public function parse_path( string $path ): array {
		$segments = array_values(
			array_filter(
				explode( '/', trim( $path, '/' ) ),
				static function ( $segment ): bool {
					return '' !== $segment;
				}
			)
		);

		$params = array();
		$count  = count( $segments );

		for ( $i = 0; $i + 1 < $count; $i += 2 ) {
			$key       = sanitize_key( rawurldecode( $segments[ $i ] ) );
			$raw_value = sanitize_text_field( rawurldecode( $segments[ $i + 1 ] ) );

			if ( '' === $key || '' === $raw_value ) {
				continue;
			}

			// sanitize_title() strips the decimal point, so price keeps its own
			// numeric sanitisation to survive the URL round trip.
			$values = 'price' === $key
				? array( (string) preg_replace( '/[^0-9.\-]/', '', $raw_value ) )
				: array_values( array_filter( array_map( 'sanitize_title', explode( ',', $raw_value ) ) ) );

			if ( empty( $values ) ) {
				continue;
			}

			switch ( $key ) {
				case 'category':
					$params['cscf_cat'] = $values[0];
					break;

				case 'brand':
					$params['cscf_brand'] = $values;
					break;

				case 'color':
					$params['cscf_color'] = $values;
					break;

				case 'rating':
					$params['rating_filter'] = (string) absint( $values[0] );
					break;

				case 'stock':
					if ( 'in' === $values[0] ) {
						$params['cscf_in_stock'] = '1';
					}
					break;

				case 'sale':
					if ( 'yes' === $values[0] || 'on' === $values[0] ) {
						$params['cscf_on_sale'] = '1';
					}
					break;

				case 'price':
					$bounds = explode( '-', $values[0], 2 );

					if ( isset( $bounds[0] ) && is_numeric( $bounds[0] ) ) {
						$params['min_price'] = $bounds[0];
					}

					if ( isset( $bounds[1] ) && is_numeric( $bounds[1] ) ) {
						$params['max_price'] = $bounds[1];
					}
					break;

				default:
					$taxonomy = $this->resolve_attribute_taxonomy( $key );

					if ( '' !== $taxonomy ) {
						$params[ 'cscf_attr_' . $taxonomy ] = $values;
					}
					break;
			}
		}

		return $params;
	}

	/**
	 * Build a pretty filter URL from request parameters.
	 *
	 * @param array $params Request parameters, as used in plain filter URLs.
	 * @return string Pretty URL, or the plain shop URL when nothing maps.
	 */
	public static function build_url( array $params ): string {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$shop = is_string( $shop ) && '' !== $shop ? $shop : home_url( '/' );

		$segments = array();

		foreach ( $params as $key => $value ) {
			$key = sanitize_key( (string) $key );

			switch ( $key ) {
				case 'cscf_cat':
					$segments[] = 'category';
					$segments[] = sanitize_title( (string) $value );
					break;

				case 'cscf_brand':
				case 'cscf_color':
					$segments[] = 'cscf_brand' === $key ? 'brand' : 'color';
					$segments[] = implode( ',', array_map( 'sanitize_title', (array) $value ) );
					break;

				case 'rating_filter':
					$segments[] = 'rating';
					$segments[] = (string) absint( $value );
					break;

				case 'cscf_in_stock':
					$segments[] = 'stock';
					$segments[] = 'in';
					break;

				case 'cscf_on_sale':
					$segments[] = 'sale';
					$segments[] = 'yes';
					break;

				case 'min_price':
				case 'max_price':
					// Handled together below.
					break;

				default:
					if ( 0 === strpos( $key, 'cscf_attr_' ) ) {
						$taxonomy   = substr( $key, strlen( 'cscf_attr_' ) );
						$label      = 0 === strpos( $taxonomy, 'pa_' ) ? substr( $taxonomy, 3 ) : $taxonomy;
						$segments[] = sanitize_key( $label );
						$segments[] = implode( ',', array_map( 'sanitize_title', (array) $value ) );
					}
					break;
			}
		}

		if ( isset( $params['min_price'] ) || isset( $params['max_price'] ) ) {
			$segments[] = 'price';
			$segments[] = ( isset( $params['min_price'] ) ? (float) $params['min_price'] : 0 ) . '-' . ( isset( $params['max_price'] ) ? (float) $params['max_price'] : 0 );
		}

		if ( empty( $segments ) ) {
			return $shop;
		}

		return trailingslashit( trailingslashit( $shop ) . implode( '/', $segments ) );
	}

	/*
	 * Duplicate content controls.
	 */

	/**
	 * Add noindex to filtered catalog pages when enabled.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function filter_robots( array $robots ): array {
		if ( ! self::is_noindex_enabled() || ! $this->is_filtered_archive() ) {
			return $robots;
		}

		return wp_robots_no_robots( $robots );
	}

	/**
	 * Point Yoast SEO and Rank Math canonicals at the unfiltered archive.
	 *
	 * @param string $canonical Canonical URL supplied by the SEO plugin.
	 * @return string
	 */
	public function filter_seo_plugin_canonical( $canonical ) {
		if ( ! $this->is_filtered_archive() ) {
			return $canonical;
		}

		$unfiltered = $this->get_unfiltered_url();

		return '' !== $unfiltered ? $unfiltered : $canonical;
	}

	/**
	 * Emit a canonical tag on filtered archives.
	 *
	 * Skipped when Yoast SEO or Rank Math is active, because they print their
	 * own canonical and the filter above already corrects it.
	 *
	 * @return void
	 */
	public function render_canonical(): void {
		if ( ! $this->is_filtered_archive() || $this->has_seo_plugin() ) {
			return;
		}

		$unfiltered = $this->get_unfiltered_url();

		if ( '' === $unfiltered ) {
			return;
		}

		echo '<link rel="canonical" href="' . esc_url( $unfiltered ) . '" />' . "\n";
	}

	/**
	 * Whether an SEO plugin already handles canonical output.
	 *
	 * @return bool
	 */
	private function has_seo_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Whether the current request is a catalog archive carrying filters.
	 *
	 * @return bool
	 */
	private function is_filtered_archive(): bool {
		if ( is_admin() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, shareable catalog filter URL.
		foreach ( array_keys( $_GET ) as $key ) {
			$key = sanitize_key( (string) $key );

			if ( 0 === strpos( $key, 'cscf_' ) || in_array( $key, array( 'min_price', 'max_price', 'rating_filter' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The archive URL without any filter parameters.
	 *
	 * @return string
	 */
	private function get_unfiltered_url(): string {
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$link = get_term_link( $term );

				return is_string( $link ) ? $link : '';
			}
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );

			return is_string( $shop ) ? $shop : '';
		}

		return '';
	}

	/**
	 * Resolve a friendly attribute name to a registered taxonomy.
	 *
	 * @param string $name Friendly attribute name, e.g. "size".
	 * @return string Taxonomy name, or empty string when unknown.
	 */
	private function resolve_attribute_taxonomy( string $name ): string {
		foreach ( array( 'pa_' . $name, $name ) as $candidate ) {
			if ( taxonomy_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Resolve the shop archive path the rewrite rule hangs off.
	 *
	 * @return string Relative path without leading or trailing slashes.
	 */
	private static function get_shop_base(): string {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return '';
		}

		$shop_id = wc_get_page_id( 'shop' );

		if ( $shop_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $shop_id );

		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		$path = wp_parse_url( $permalink, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path      = substr( $path, strlen( $home_path ) );

		return trim( (string) $path, '/' );
	}
}
