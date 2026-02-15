<?php
/**
 * Shop filter controller.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce archive filters and UI rendering.
 */
final class ShopFilters {
	/** @var int */
	private const MAX_PER_PAGE = 9999;

	/** @var float */
	private const MAX_PRICE = 99999999;

	/** @var int */
	private const TERM_LIMIT = 40;

	/** @var string */
	private const CACHE_GROUP = 'wf_shop_filters';

	/** @var string */
	private $brand_taxonomy = '';

	/** @var string */
	private $color_taxonomy = '';

	/** @var string */
	private $plugin_url = '';

	/** @var string */
	private $asset_version = '0.4.0';

	/**
	 * Constructor.
	 *
	 * @param string $plugin_url    Plugin base URL.
	 * @param string $asset_version Asset version.
	 */
	public function __construct( string $plugin_url, string $asset_version ) {
		$this->plugin_url    = untrailingslashit( $plugin_url ) . '/';
		$this->asset_version = $asset_version;
	}

	/**
	 * Register class hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'bootstrap_taxonomies' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters_to_main_query' ) );

		add_action( 'woocommerce_before_main_content', array( $this, 'render_layout_start' ), 15 );
		add_action( 'woocommerce_after_main_content', array( $this, 'render_layout_end' ), 5 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_per_page_switcher' ), 25 );

		add_filter( 'loop_shop_columns', array( $this, 'filter_loop_columns' ) );
		add_filter( 'loop_shop_per_page', array( $this, 'filter_loop_per_page' ), 20 );
	}

	/**
	 * Resolve dynamic taxonomies after WooCommerce registers attributes.
	 *
	 * @return void
	 */
	public function bootstrap_taxonomies(): void {
		$this->brand_taxonomy = $this->resolve_taxonomy( array( 'pa_brand', 'product_brand', 'brand' ) );
		$this->color_taxonomy = $this->resolve_taxonomy( array( 'pa_color', 'color' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_shop_archive() ) {
			return;
		}

		wp_enqueue_style(
			'wf-shop-filters',
			$this->plugin_url . 'assets/css/wf-shop.css',
			array(),
			$this->asset_version
		);

		wp_enqueue_script(
			'wf-shop-filters',
			$this->plugin_url . 'assets/js/wf-shop.js',
			array(),
			$this->asset_version,
			true
		);
	}

	/**
	 * Force product columns for this layout.
	 *
	 * @param int $columns Existing column count.
	 * @return int
	 */
	public function filter_loop_columns( int $columns ): int {
		if ( ! $this->is_shop_archive() ) {
			return $columns;
		}

		return 4;
	}

	/**
	 * Control per-page from request.
	 *
	 * @param int $per_page Existing per-page value.
	 * @return int
	 */
	public function filter_loop_per_page( int $per_page ): int {
		if ( ! $this->is_shop_archive() ) {
			return $per_page;
		}

		$requested = $this->get_request_absint( 'wf_per_page' );
		if ( $requested > 0 && $requested <= self::MAX_PER_PAGE ) {
			return $requested;
		}

		return $per_page;
	}

	/**
	 * Apply selected filters to main archive query.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public function apply_filters_to_main_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $this->is_query_shop_archive( $query ) ) {
			return;
		}

		$tax_clauses  = array();
		$meta_clauses = array();

		$selected_category = $this->get_request_slug( 'wf_cat' );
		if ( '' !== $selected_category ) {
			$tax_clauses[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( $selected_category ),
			);
		}

		$selected_brands = $this->get_request_slug_list( 'wf_brand' );
		if ( '' !== $this->brand_taxonomy && ! empty( $selected_brands ) ) {
			$tax_clauses[] = array(
				'taxonomy' => $this->brand_taxonomy,
				'field'    => 'slug',
				'terms'    => $selected_brands,
				'operator' => 'IN',
			);
		}

		$selected_colors = $this->get_request_slug_list( 'wf_color' );
		if ( '' !== $this->color_taxonomy && ! empty( $selected_colors ) ) {
			$tax_clauses[] = array(
				'taxonomy' => $this->color_taxonomy,
				'field'    => 'slug',
				'terms'    => $selected_colors,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tax_clauses ) ) {
			$query->set( 'tax_query', $this->merge_query_clauses( (array) $query->get( 'tax_query' ), $tax_clauses ) );
		}

		$min_price = $this->get_request_decimal( 'min_price' );
		$max_price = $this->get_request_decimal( 'max_price' );

		if ( null !== $min_price || null !== $max_price ) {
			$range_min = null !== $min_price ? $min_price : 0.0;
			$range_max = null !== $max_price ? $max_price : self::MAX_PRICE;

			if ( $range_min > $range_max ) {
				$tmp       = $range_min;
				$range_min = $range_max;
				$range_max = $tmp;
			}

			$meta_clauses[] = array(
				'key'     => '_price',
				'value'   => array( $range_min, $range_max ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		$rating = $this->get_request_absint( 'rating_filter' );
		if ( $rating > 0 && $rating <= 5 ) {
			$meta_clauses[] = array(
				'key'     => '_wc_average_rating',
				'value'   => (float) $rating,
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		if ( ! empty( $meta_clauses ) ) {
			$query->set( 'meta_query', $this->merge_query_clauses( (array) $query->get( 'meta_query' ), $meta_clauses ) );
		}
	}

	/**
	 * Render layout wrapper and sidebar start.
	 *
	 * @return void
	 */
	public function render_layout_start(): void {
		if ( ! $this->is_shop_archive() ) {
			return;
		}

		echo '<div class="wf-shop-layout">';
		echo '<aside class="wf-sidebar">';
		$this->render_filter_form();
		echo '</aside>';
		echo '<section class="wf-products">';
	}

	/**
	 * Render layout wrapper end.
	 *
	 * @return void
	 */
	public function render_layout_end(): void {
		if ( ! $this->is_shop_archive() ) {
			return;
		}

		echo '</section>';
		echo '</div>';
	}

	/**
	 * Render per-page links.
	 *
	 * @return void
	 */
	public function render_per_page_switcher(): void {
		if ( ! $this->is_shop_archive() ) {
			return;
		}

		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) {
			return;
		}

		$current = $this->get_request_absint( 'wf_per_page' );
		if ( $current <= 0 ) {
			$current = (int) $wp_query->get( 'posts_per_page' );
		}

		$total = (int) $wp_query->found_posts;

		$choices = array(
			20 => '20',
			40 => '40',
			60 => '60',
			0  => __( 'All', 'woo-filters' ),
		);

		echo '<div class="wf-per-page" aria-label="' . esc_attr__( 'Products per page', 'woo-filters' ) . '">';
		foreach ( $choices as $value => $label ) {
			$url    = $this->build_filter_url_for_per_page( (int) $value );
			$active = ( 0 === (int) $value && $current >= $total && $total > 0 ) || ( (int) $value > 0 && $current === (int) $value );
			$class  = $active ? ' class="is-active"' : '';

			echo '<a' . $class . ' href="' . esc_url( $url ) . '">' . esc_html( (string) $label ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Render sidebar filter form.
	 *
	 * @return void
	 */
	private function render_filter_form(): void {
		$action          = $this->get_archive_url();
		$selected_brands = $this->get_request_slug_list( 'wf_brand' );
		$selected_colors = $this->get_request_slug_list( 'wf_color' );
		$selected_rating = $this->get_request_absint( 'rating_filter' );
		$min_price       = $this->get_request_decimal( 'min_price' );
		$max_price       = $this->get_request_decimal( 'max_price' );

		echo '<form class="wf-filter-form" method="get" action="' . esc_url( $action ) . '">';
		$this->render_preserved_fields( array( 'wf_cat', 'wf_brand', 'wf_color', 'min_price', 'max_price', 'rating_filter', 'paged', 'product-page' ) );
		$this->render_active_filters();

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Categories', 'woo-filters' ) . '</h4>';
		$this->render_categories();
		echo '</div>';

		if ( '' !== $this->brand_taxonomy ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Filter by Brands', 'woo-filters' ) . '</h4>';
			$this->render_term_checkboxes( $this->brand_taxonomy, 'wf_brand[]', $selected_brands );
			echo '</div>';
		}

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Price', 'woo-filters' ) . '</h4>';
		echo '<div class="wf-price-grid">';
		echo '<label><span>' . esc_html__( 'Min', 'woo-filters' ) . '</span><input type="number" min="0" step="0.01" name="min_price" value="' . esc_attr( $this->format_decimal_for_input( $min_price ) ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Max', 'woo-filters' ) . '</span><input type="number" min="0" step="0.01" name="max_price" value="' . esc_attr( $this->format_decimal_for_input( $max_price ) ) . '" /></label>';
		echo '</div>';
		echo '</div>';

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Customer Rating', 'woo-filters' ) . '</h4>';
		for ( $i = 5; $i >= 1; $i-- ) {
			echo '<label class="wf-radio">';
			echo '<input type="radio" name="rating_filter" value="' . esc_attr( (string) $i ) . '" ' . checked( $selected_rating, $i, false ) . ' />';
			echo '<span>' . esc_html( sprintf( __( '%d stars & up', 'woo-filters' ), $i ) ) . '</span>';
			echo '</label>';
		}
		echo '<label class="wf-radio">';
		echo '<input type="radio" name="rating_filter" value="" ' . checked( $selected_rating, 0, false ) . ' />';
		echo '<span>' . esc_html__( 'Any', 'woo-filters' ) . '</span>';
		echo '</label>';
		echo '</div>';

		if ( '' !== $this->color_taxonomy ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Color', 'woo-filters' ) . '</h4>';
			$this->render_term_checkboxes( $this->color_taxonomy, 'wf_color[]', $selected_colors );
			echo '</div>';
		}

		echo '<div class="wf-actions">';
		echo '<button type="submit" class="button alt">' . esc_html__( 'Apply Filters', 'woo-filters' ) . '</button>';
		echo '<a class="button" href="' . esc_url( $action ) . '">' . esc_html__( 'Clear', 'woo-filters' ) . '</a>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * Render chips for active filters.
	 *
	 * @return void
	 */
	private function render_active_filters(): void {
		$chips = array();

		$selected_category = $this->get_request_slug( 'wf_cat' );
		if ( '' !== $selected_category ) {
			$term = get_term_by( 'slug', $selected_category, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$chips[] = array(
					'label' => sprintf( __( 'Category: %s', 'woo-filters' ), $term->name ),
					'url'   => $this->build_remove_filter_url( 'wf_cat' ),
				);
			}
		}

		$chips = array_merge( $chips, $this->get_term_chips_from_selected( $this->brand_taxonomy, 'wf_brand', __( 'Brand', 'woo-filters' ) ) );
		$chips = array_merge( $chips, $this->get_term_chips_from_selected( $this->color_taxonomy, 'wf_color', __( 'Color', 'woo-filters' ) ) );

		$min_price = $this->get_request_decimal( 'min_price' );
		if ( null !== $min_price ) {
			$chips[] = array(
				'label' => sprintf( __( 'Min: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $min_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'min_price' ),
			);
		}

		$max_price = $this->get_request_decimal( 'max_price' );
		if ( null !== $max_price ) {
			$chips[] = array(
				'label' => sprintf( __( 'Max: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $max_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'max_price' ),
			);
		}

		$rating = $this->get_request_absint( 'rating_filter' );
		if ( $rating > 0 && $rating <= 5 ) {
			$chips[] = array(
				'label' => sprintf( __( '%d stars & up', 'woo-filters' ), $rating ),
				'url'   => $this->build_remove_filter_url( 'rating_filter' ),
			);
		}

		if ( empty( $chips ) ) {
			return;
		}

		echo '<div class="wf-active-filters">';
		echo '<h5>' . esc_html__( 'Active Filters', 'woo-filters' ) . '</h5>';
		echo '<div class="wf-chip-list">';
		foreach ( $chips as $chip ) {
			echo '<a class="wf-chip" href="' . esc_url( (string) $chip['url'] ) . '">' . esc_html( (string) $chip['label'] ) . ' <span aria-hidden="true">&times;</span></a>';
		}
		echo '</div>';
		echo '<a class="wf-clear-all" href="' . esc_url( $this->build_clear_filters_url() ) . '">' . esc_html__( 'Clear all', 'woo-filters' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Render category radio options.
	 *
	 * @return void
	 */
	private function render_categories(): void {
		$selected = $this->get_request_slug( 'wf_cat' );
		$terms    = $this->get_terms_cached(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $terms ) ) {
			return;
		}

		echo '<ul class="wf-cat-list">';
		echo '<li><label><input type="radio" name="wf_cat" value="" ' . checked( $selected, '', false ) . ' /> <span>' . esc_html__( 'All Categories', 'woo-filters' ) . '</span></label></li>';
		foreach ( $terms as $term ) {
			echo '<li><label><input type="radio" name="wf_cat" value="' . esc_attr( $term->slug ) . '" ' . checked( $selected, $term->slug, false ) . ' /> <span>' . esc_html( $term->name ) . '</span></label></li>';
		}
		echo '</ul>';
	}

	/**
	 * Render checkbox list for a taxonomy.
	 *
	 * @param string $taxonomy        Taxonomy key.
	 * @param string $field_name      HTML field name.
	 * @param array  $selected_values Selected term slugs.
	 * @return void
	 */
	private function render_term_checkboxes( string $taxonomy, string $field_name, array $selected_values ): void {
		$terms = $this->get_terms_cached(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => self::TERM_LIMIT,
			)
		);

		if ( empty( $terms ) ) {
			echo '<p class="wf-empty">' . esc_html__( 'No options found.', 'woo-filters' ) . '</p>';
			return;
		}

		echo '<ul class="wf-term-list">';
		foreach ( $terms as $term ) {
			$checked = in_array( $term->slug, $selected_values, true );
			echo '<li>';
			echo '<label>';
			echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $term->slug ) . '" ' . checked( $checked, true, false ) . ' />';
			echo '<span>' . esc_html( $term->name ) . '</span>';
			echo '<small>' . esc_html( (string) $term->count ) . '</small>';
			echo '</label>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render hidden inputs for non-filter query args.
	 *
	 * @param array $excluded_keys Excluded keys.
	 * @return void
	 */
	private function render_preserved_fields( array $excluded_keys ): void {
		$excluded = array_fill_keys( $excluded_keys, true );
		$args     = $this->get_current_query_args();

		foreach ( $args as $key => $value ) {
			if ( isset( $excluded[ $key ] ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( (string) $item ) . '" />';
				}
				continue;
			}

			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
	}

	/**
	 * Build per-page URL preserving current query args.
	 *
	 * @param int $value Per-page value.
	 * @return string
	 */
	private function build_filter_url_for_per_page( int $value ): string {
		$args = $this->get_current_query_args();

		unset( $args['paged'], $args['product-page'] );
		$args['wf_per_page'] = 0 === $value ? self::MAX_PER_PAGE : $value;

		return add_query_arg( $args, $this->get_archive_url() );
	}

	/**
	 * Build chips for selected terms in a taxonomy.
	 *
	 * @param string $taxonomy     Taxonomy key.
	 * @param string $param_key    Query key.
	 * @param string $label_prefix Chip prefix.
	 * @return array
	 */
	private function get_term_chips_from_selected( string $taxonomy, string $param_key, string $label_prefix ): array {
		if ( '' === $taxonomy ) {
			return array();
		}

		$selected = $this->get_request_slug_list( $param_key );
		if ( empty( $selected ) ) {
			return array();
		}

		$terms = $this->get_terms_cached(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'slug'       => $selected,
			)
		);

		if ( empty( $terms ) ) {
			return array();
		}

		$chips = array();
		foreach ( $terms as $term ) {
			$chips[] = array(
				'label' => sprintf( '%s: %s', $label_prefix, $term->name ),
				'url'   => $this->build_remove_filter_url( $param_key, $term->slug ),
			);
		}

		return $chips;
	}

	/**
	 * Build URL that removes specific filter key/value.
	 *
	 * @param string $key             Query key.
	 * @param string $value_to_remove Optional value for multi-select keys.
	 * @return string
	 */
	private function build_remove_filter_url( string $key, string $value_to_remove = '' ): string {
		$args = $this->get_current_query_args();
		unset( $args['paged'], $args['product-page'] );

		if ( ! isset( $args[ $key ] ) ) {
			return add_query_arg( $args, $this->get_archive_url() );
		}

		if ( '' === $value_to_remove || ! is_array( $args[ $key ] ) ) {
			unset( $args[ $key ] );
			return add_query_arg( $args, $this->get_archive_url() );
		}

		$remaining = array_values(
			array_filter(
				$args[ $key ],
				static function ( $item ) use ( $value_to_remove ) {
					return sanitize_title( (string) $item ) !== sanitize_title( $value_to_remove );
				}
			)
		);

		if ( empty( $remaining ) ) {
			unset( $args[ $key ] );
		} else {
			$args[ $key ] = $remaining;
		}

		return add_query_arg( $args, $this->get_archive_url() );
	}

	/**
	 * Build URL without active filter keys.
	 *
	 * @return string
	 */
	private function build_clear_filters_url(): string {
		$args = $this->get_current_query_args();
		unset(
			$args['wf_cat'],
			$args['wf_brand'],
			$args['wf_color'],
			$args['min_price'],
			$args['max_price'],
			$args['rating_filter'],
			$args['paged'],
			$args['product-page']
		);

		return add_query_arg( $args, $this->get_archive_url() );
	}

	/**
	 * Get sanitized current query args.
	 *
	 * @return array
	 */
	private function get_current_query_args(): array {
		$args = array();

		foreach ( $_GET as $key => $value ) {
			$normalized_key = sanitize_key( (string) $key );
			if ( '' === $normalized_key ) {
				continue;
			}

			if ( 0 === strpos( $normalized_key, '_' ) || false !== strpos( $normalized_key, 'nonce' ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$items = array_values(
					array_filter(
						array_map(
							static function ( $item ) {
								return sanitize_text_field( wp_unslash( (string) $item ) );
							},
							$value
						),
						static function ( $item ) {
							return '' !== $item;
						}
					)
				);

				if ( ! empty( $items ) ) {
					$args[ $normalized_key ] = $items;
				}

				continue;
			}

			$item = sanitize_text_field( wp_unslash( (string) $value ) );
			if ( '' !== $item ) {
				$args[ $normalized_key ] = $item;
			}
		}

		return $args;
	}

	/**
	 * Parse slug request value.
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
	 * Parse request value as integer.
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
	 * Parse decimal from request.
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
	 * Parse list of slugs from request value.
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
			$values = array_map( 'sanitize_title', explode( ',', sanitize_text_field( wp_unslash( (string) $raw ) ) ) );
		}

		$values = array_values(
			array_unique(
				array_filter(
					$values,
					static function ( $value ) {
						return '' !== $value;
					}
				)
			)
		);

		return $values;
	}

	/**
	 * Merge query clauses with existing tax/meta clauses.
	 *
	 * @param array $existing Existing query data.
	 * @param array $new      New clauses.
	 * @return array
	 */
	private function merge_query_clauses( array $existing, array $new ): array {
		$clauses = array();

		foreach ( $existing as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}

			if ( is_array( $clause ) ) {
				$clauses[] = $clause;
			}
		}

		foreach ( $new as $clause ) {
			$clauses[] = $clause;
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		array_unshift( $clauses, array( 'relation' => 'AND' ) );

		return $clauses;
	}

	/**
	 * Get cached term list.
	 *
	 * @param array $args get_terms args.
	 * @return array
	 */
	private function get_terms_cached( array $args ): array {
		$cache_key = 'terms_' . md5( wp_json_encode( $args ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			wp_cache_set( $cache_key, array(), self::CACHE_GROUP, 300 );
			return array();
		}

		$valid_terms = array_values(
			array_filter(
				$terms,
				static function ( $term ) {
					return $term instanceof \WP_Term;
				}
			)
		);

		wp_cache_set( $cache_key, $valid_terms, self::CACHE_GROUP, 300 );

		return $valid_terms;
	}

	/**
	 * Resolve first available taxonomy.
	 *
	 * @param array $candidates Candidate taxonomy keys.
	 * @return string
	 */
	private function resolve_taxonomy( array $candidates ): string {
		foreach ( $candidates as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Get current archive URL.
	 *
	 * @return string
	 */
	private function get_archive_url(): string {
		$queried_object = get_queried_object();
		if ( $queried_object instanceof \WP_Term ) {
			$term_link = get_term_link( $queried_object );
			if ( ! is_wp_error( $term_link ) ) {
				return $term_link;
			}
		}

		return wc_get_page_permalink( 'shop' );
	}

	/**
	 * Determine whether current request is a product archive.
	 *
	 * @return bool
	 */
	private function is_shop_archive(): bool {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	}

	/**
	 * Determine whether query is a product archive query.
	 *
	 * @param \WP_Query $query Query object.
	 * @return bool
	 */
	private function is_query_shop_archive( \WP_Query $query ): bool {
		return (bool) ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) );
	}

	/**
	 * Format decimal value for numeric input fields.
	 *
	 * @param float|null $value Decimal value.
	 * @return string
	 */
	private function format_decimal_for_input( ?float $value ): string {
		if ( null === $value ) {
			return '';
		}

		return rtrim( rtrim( (string) $value, '0' ), '.' );
	}
}
