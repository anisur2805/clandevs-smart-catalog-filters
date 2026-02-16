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
	/** @var string */
	private const NONCE_ACTION = 'wf_filter_request';

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

	/** @var bool */
	private $is_shortcode_context = false;

	/** @var string */
	private $shortcode_action_url = '';

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
		add_shortcode( 'woo_filters', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters_to_main_query' ) );
		add_action( 'save_post_product', array( $this, 'invalidate_filter_cache' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'invalidate_filter_cache' ), 10, 2 );
		add_action( 'trash_post', array( $this, 'invalidate_filter_cache' ), 10, 2 );
		add_action( 'untrash_post', array( $this, 'invalidate_filter_cache' ), 10, 2 );
		add_action( 'set_object_terms', array( $this, 'invalidate_filter_cache' ), 10, 6 );
		add_action( 'created_term', array( $this, 'invalidate_filter_cache' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'invalidate_filter_cache' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'invalidate_filter_cache' ), 10, 5 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'invalidate_filter_cache' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'invalidate_filter_cache' ), 10, 2 );

		add_action( 'woocommerce_before_main_content', array( $this, 'render_layout_start' ), 15 );
		add_action( 'woocommerce_after_main_content', array( $this, 'render_layout_end' ), 5 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_top_active_filters' ), 20 );
		add_action( 'woocommerce_before_shop_loop', array( $this, 'render_per_page_switcher' ), 25 );

		add_filter( 'loop_shop_columns', array( $this, 'filter_loop_columns' ) );
		add_filter( 'loop_shop_per_page', array( $this, 'filter_loop_per_page' ), 20 );

		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
		add_action( 'woocommerce_no_products_found', array( $this, 'render_no_products_state' ), 10 );
	}

	/**
	 * Invalidate cached filter metadata keys.
	 *
	 * @return void
	 */
	public function invalidate_filter_cache( ...$unused ): void {
		update_option( 'wf_cache_last_changed', (string) microtime( true ), false );
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
		if ( ! $this->is_shop_archive() && ! $this->has_shortcode_on_current_page() ) {
			return;
		}

		wp_enqueue_style(
			'wf-shop-filters',
			$this->plugin_url . 'assets/css/wf-shop.css',
			array(),
			$this->asset_version
		);
		$this->enqueue_inline_styles();

		wp_enqueue_script(
			'wf-shop-filters',
			$this->plugin_url . 'assets/js/wf-shop.js',
			array(),
			$this->asset_version,
			true
		);

		wp_localize_script(
			'wf-shop-filters',
			'wfShopFilters',
			array(
				'nonce' => $this->get_filter_nonce(),
			)
		);
	}

	/**
	 * Add user-configured CSS variables and custom CSS.
	 *
	 * @return void
	 */
	private function enqueue_inline_styles(): void {
		$options = StyleSettings::get_options();

		$variables = array(
			'--wf-accent'          => isset( $options['accent_color'] ) ? (string) $options['accent_color'] : '#0b6a78',
			'--wf-sidebar-bg'      => isset( $options['sidebar_bg_color'] ) ? (string) $options['sidebar_bg_color'] : '#ffffff',
			'--wf-sidebar-border'  => isset( $options['sidebar_border_color'] ) ? (string) $options['sidebar_border_color'] : '#e5e8ee',
			'--wf-heading-color'   => isset( $options['heading_color'] ) ? (string) $options['heading_color'] : '#1f2937',
			'--wf-chip-bg'         => isset( $options['chip_bg_color'] ) ? (string) $options['chip_bg_color'] : '#ffffff',
			'--wf-chip-border'     => isset( $options['chip_border_color'] ) ? (string) $options['chip_border_color'] : '#c7d5e3',
			'--wf-button-bg'       => isset( $options['button_bg_color'] ) ? (string) $options['button_bg_color'] : '#4b5563',
			'--wf-button-text'     => isset( $options['button_text_color'] ) ? (string) $options['button_text_color'] : '#ffffff',
			'--wf-font-family'     => isset( $options['font_family'] ) ? (string) $options['font_family'] : 'inherit',
			'--wf-font-size'       => ( isset( $options['font_size'] ) ? absint( $options['font_size'] ) : 16 ) . 'px',
		);

		$declarations = array();
		foreach ( $variables as $name => $value ) {
			$declarations[] = $name . ':' . trim( (string) $value );
		}

		$css = '.wf-shop-layout{' . implode( ';', $declarations ) . ';}';
		if ( ! empty( $options['custom_css'] ) ) {
			$css .= "\n" . (string) $options['custom_css'];
		}

		wp_add_inline_style( 'wf-shop-filters', $css );
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

		if ( ! $this->is_valid_filter_request() ) {
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

		if ( ! $this->is_valid_filter_request() ) {
			return;
		}

		$clauses      = $this->get_request_filter_clauses();
		$tax_clauses  = $clauses['tax'];
		$meta_clauses = $clauses['meta'];

		if ( ! empty( $tax_clauses ) ) {
			$query->set( 'tax_query', $this->merge_query_clauses( (array) $query->get( 'tax_query' ), $tax_clauses ) );
		}

		if ( ! empty( $meta_clauses ) ) {
			$query->set( 'meta_query', $this->merge_query_clauses( (array) $query->get( 'meta_query' ), $meta_clauses ) );
		}
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => '12',
				'columns'  => '4',
			),
			$atts,
			'woo_filters'
		);

		$per_page = absint( $atts['per_page'] );
		if ( $per_page <= 0 ) {
			$per_page = 12;
		}

		$columns = absint( $atts['columns'] );
		if ( $columns <= 0 || $columns > 6 ) {
			$columns = 4;
		}

		$paged = max( 1, $this->get_request_absint( 'paged' ) );
		if ( $paged <= 1 ) {
			$paged = max( 1, $this->get_request_absint( 'product-page' ) );
		}

		$query = $this->get_shortcode_products_query( $per_page, $paged );

		$this->is_shortcode_context = true;
		$this->shortcode_action_url = get_permalink();
		$skin_class                 = $this->get_layout_skin_class();

		ob_start();
		echo '<div class="wf-shop-layout wf-shortcode-layout ' . esc_attr( $skin_class ) . '">';
		echo '<button type="button" class="wf-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'woo-filters' ) . '</button>';
		echo '<div class="wf-sidebar-overlay" aria-hidden="true"></div>';
		echo '<aside class="wf-sidebar">';
		echo '<button type="button" class="wf-sidebar-close" aria-label="' . esc_attr__( 'Close filters', 'woo-filters' ) . '">&times;</button>';
		$this->render_filter_form();
		echo '</aside>';
		echo '<section class="wf-products">';

		if ( $query->have_posts() ) {
			echo '<ul class="products columns-' . esc_attr( (string) $columns ) . '">';
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			echo '</ul>';
			$this->render_shortcode_pagination( $query );
		} else {
			$this->render_no_products_state();
		}

		echo '</section>';
		echo '</div>';

		wp_reset_postdata();
		$this->is_shortcode_context = false;
		$this->shortcode_action_url = '';

		return (string) ob_get_clean();
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

		echo '<div class="wf-shop-layout ' . esc_attr( $this->get_layout_skin_class() ) . '">';
		echo '<button type="button" class="wf-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'woo-filters' ) . '</button>';
		echo '<div class="wf-sidebar-overlay" aria-hidden="true"></div>';
		echo '<aside class="wf-sidebar">';
		echo '<button type="button" class="wf-sidebar-close" aria-label="' . esc_attr__( 'Close filters', 'woo-filters' ) . '">&times;</button>';
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
	 * Render no-results empty state.
	 *
	 * @return void
	 */
	public function render_no_products_state(): void {
		if ( ! $this->is_shop_archive() && ! $this->is_shortcode_context ) {
			return;
		}

		echo '<div class="wf-no-results" role="status" aria-live="polite">';
		echo '<h3>' . esc_html__( 'No products found', 'woo-filters' ) . '</h3>';
		echo '<p>' . esc_html__( 'Try removing or changing some filters to find matching products.', 'woo-filters' ) . '</p>';
		echo '<div class="wf-empty-actions">';
		echo '<a class="button alt" href="' . esc_url( $this->build_clear_filters_url() ) . '">' . esc_html__( 'Clear all filters', 'woo-filters' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $this->get_shop_page_url() ) . '">' . esc_html__( 'Back to shop', 'woo-filters' ) . '</a>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render top active filters bar above product loop.
	 *
	 * @return void
	 */
	public function render_top_active_filters(): void {
		if ( ! $this->is_shop_archive() ) {
			return;
		}

		$chips = $this->get_active_filter_chips();
		if ( empty( $chips ) ) {
			return;
		}

		echo '<div class="wf-top-active-filters">';
		echo '<div class="wf-chip-list">';
		foreach ( $chips as $chip ) {
			echo '<a class="wf-chip" href="' . esc_url( (string) $chip['url'] ) . '">' . esc_html( (string) $chip['label'] ) . ' <span aria-hidden="true">&times;</span></a>';
		}
		echo '</div>';
		echo '<a class="wf-clear-all" href="' . esc_url( $this->build_clear_filters_url() ) . '">' . esc_html__( 'Clear all', 'woo-filters' ) . '</a>';
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
		$filter_options  = FilterSettings::get_options();
		$show_categories = isset( $filter_options['show_categories'] ) && 'yes' === $filter_options['show_categories'];
		$show_brands     = isset( $filter_options['show_brands'] ) && 'yes' === $filter_options['show_brands'];
		$show_price      = isset( $filter_options['show_price'] ) && 'yes' === $filter_options['show_price'];
		$show_rating     = isset( $filter_options['show_rating'] ) && 'yes' === $filter_options['show_rating'];
		$show_stock      = isset( $filter_options['show_availability'] ) && 'yes' === $filter_options['show_availability'];
		$show_colors     = isset( $filter_options['show_colors'] ) && 'yes' === $filter_options['show_colors'];

		$action          = $this->get_archive_url();
		$selected_brands  = $this->get_request_slug_list( 'wf_brand' );
		$selected_colors  = $this->get_request_slug_list( 'wf_color' );
		$multiselect_mode = $this->get_request_multiselect_mode();
		$selected_rating = $this->get_request_absint( 'rating_filter' );
		$min_price       = $this->get_request_decimal( 'min_price' );
		$max_price       = $this->get_request_decimal( 'max_price' );
		$in_stock_only   = $this->get_request_flag( 'wf_in_stock' );
		$on_sale_only    = $this->get_request_flag( 'wf_on_sale' );
		$price_bounds    = $this->get_price_bounds();
		$slider_min      = $price_bounds['min'];
		$slider_max      = $price_bounds['max'];
		$current_min     = null !== $min_price ? $min_price : $slider_min;
		$current_max     = null !== $max_price ? $max_price : $slider_max;

		if ( $current_min > $current_max ) {
			$tmp         = $current_min;
			$current_min = $current_max;
			$current_max = $tmp;
		}

		echo '<form class="wf-filter-form" method="get" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="wf_nonce" value="' . esc_attr( $this->get_filter_nonce() ) . '" />';
		$this->render_preserved_fields( array( 'wf_cat', 'wf_brand', 'wf_color', 'wf_logic', 'min_price', 'max_price', 'rating_filter', 'wf_in_stock', 'wf_on_sale', 'paged', 'product-page' ) );
		$this->render_active_filters();

		if ( $show_categories ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Categories', 'woo-filters' ) . '</h4>';
			$this->render_categories();
			echo '</div>';
		}

		if ( $show_brands && '' !== $this->brand_taxonomy ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Filter by Brands', 'woo-filters' ) . '</h4>';
			$this->render_term_checkboxes( $this->brand_taxonomy, 'wf_brand[]', 'wf_brand', $selected_brands );
			echo '</div>';
		}

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Multi-select Logic', 'woo-filters' ) . '</h4>';
		echo '<label class="wf-radio"><input type="radio" name="wf_logic" value="or" ' . checked( $multiselect_mode, 'or', false ) . ' /> <span>' . esc_html__( 'Match any selected option (OR)', 'woo-filters' ) . '</span></label>';
		echo '<label class="wf-radio"><input type="radio" name="wf_logic" value="and" ' . checked( $multiselect_mode, 'and', false ) . ' /> <span>' . esc_html__( 'Match all selected options (AND)', 'woo-filters' ) . '</span></label>';
		echo '</div>';

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Price', 'woo-filters' ) . '</h4>';
		echo '<div class="wf-price-slider" data-min="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" data-max="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" data-step="0.01">';
		echo '<div class="wf-price-range-inputs">';
		echo '<input class="wf-price-range wf-price-range-min" type="range" aria-label="' . esc_attr__( 'Minimum price', 'woo-filters' ) . '" min="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" max="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" step="0.01" value="' . esc_attr( $this->format_decimal_for_input( $current_min ) ) . '" />';
		echo '<input class="wf-price-range wf-price-range-max" type="range" aria-label="' . esc_attr__( 'Maximum price', 'woo-filters' ) . '" min="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" max="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" step="0.01" value="' . esc_attr( $this->format_decimal_for_input( $current_max ) ) . '" />';
		echo '</div>';
		echo '<div class="wf-price-track"><span class="wf-price-track-fill"></span></div>';
		echo '</div>';
		echo '<div class="wf-price-grid">';
		echo '<label><span>' . esc_html__( 'Min', 'woo-filters' ) . '</span><input type="number" min="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" max="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" step="0.01" name="min_price" value="' . esc_attr( $this->format_decimal_for_input( $min_price ) ) . '" placeholder="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" /></label>';
		echo '<label><span>' . esc_html__( 'Max', 'woo-filters' ) . '</span><input type="number" min="' . esc_attr( $this->format_decimal_for_input( $slider_min ) ) . '" max="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" step="0.01" name="max_price" value="' . esc_attr( $this->format_decimal_for_input( $max_price ) ) . '" placeholder="' . esc_attr( $this->format_decimal_for_input( $slider_max ) ) . '" /></label>';
		echo '</div>';
		echo '</div>';

		if ( $show_rating ) {
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
		}

		if ( $show_stock ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Availability', 'woo-filters' ) . '</h4>';
			echo '<label class="wf-radio"><input type="checkbox" name="wf_in_stock" value="1" ' . checked( $in_stock_only, true, false ) . ' /> <span>' . esc_html__( 'In stock only', 'woo-filters' ) . '</span></label>';
			echo '<label class="wf-radio"><input type="checkbox" name="wf_on_sale" value="1" ' . checked( $on_sale_only, true, false ) . ' /> <span>' . esc_html__( 'On sale only', 'woo-filters' ) . '</span></label>';
			echo '</div>';
		}

		if ( $show_colors && '' !== $this->color_taxonomy ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Color', 'woo-filters' ) . '</h4>';
			$this->render_term_checkboxes( $this->color_taxonomy, 'wf_color[]', 'wf_color', $selected_colors );
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
		$chips = $this->get_active_filter_chips();

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
	 * Build a normalized list of active filter chips.
	 *
	 * @return array
	 */
	private function get_active_filter_chips(): array {
		$filter_options = FilterSettings::get_options();
		$chips = array();

		$selected_category = $this->get_request_slug( 'wf_cat' );
		if ( isset( $filter_options['show_categories'] ) && 'yes' === $filter_options['show_categories'] && '' !== $selected_category ) {
			$term = get_term_by( 'slug', $selected_category, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$chips[] = array(
					'label' => sprintf( __( 'Category: %s', 'woo-filters' ), $term->name ),
					'url'   => $this->build_remove_filter_url( 'wf_cat' ),
				);
			}
		}

		if ( isset( $filter_options['show_brands'] ) && 'yes' === $filter_options['show_brands'] ) {
			$chips = array_merge( $chips, $this->get_term_chips_from_selected( $this->brand_taxonomy, 'wf_brand', __( 'Brand', 'woo-filters' ) ) );
		}
		if ( isset( $filter_options['show_colors'] ) && 'yes' === $filter_options['show_colors'] ) {
			$chips = array_merge( $chips, $this->get_term_chips_from_selected( $this->color_taxonomy, 'wf_color', __( 'Color', 'woo-filters' ) ) );
		}

		$multiselect_mode = $this->get_request_multiselect_mode();
		if ( 'and' === $multiselect_mode ) {
			$chips[] = array(
				'label' => __( 'Logic: AND', 'woo-filters' ),
				'url'   => $this->build_remove_filter_url( 'wf_logic' ),
			);
		}

		$min_price = $this->get_request_decimal( 'min_price' );
		if ( isset( $filter_options['show_price'] ) && 'yes' === $filter_options['show_price'] && null !== $min_price ) {
			$chips[] = array(
				'label' => sprintf( __( 'Min: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $min_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'min_price' ),
			);
		}

		$max_price = $this->get_request_decimal( 'max_price' );
		if ( isset( $filter_options['show_price'] ) && 'yes' === $filter_options['show_price'] && null !== $max_price ) {
			$chips[] = array(
				'label' => sprintf( __( 'Max: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $max_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'max_price' ),
			);
		}

		$rating = $this->get_request_absint( 'rating_filter' );
		if ( isset( $filter_options['show_rating'] ) && 'yes' === $filter_options['show_rating'] && $rating > 0 && $rating <= 5 ) {
			$chips[] = array(
				'label' => sprintf( __( '%d stars & up', 'woo-filters' ), $rating ),
				'url'   => $this->build_remove_filter_url( 'rating_filter' ),
			);
		}

		if ( isset( $filter_options['show_availability'] ) && 'yes' === $filter_options['show_availability'] && $this->get_request_flag( 'wf_in_stock' ) ) {
			$chips[] = array(
				'label' => __( 'Stock: In stock', 'woo-filters' ),
				'url'   => $this->build_remove_filter_url( 'wf_in_stock' ),
			);
		}

		if ( isset( $filter_options['show_availability'] ) && 'yes' === $filter_options['show_availability'] && $this->get_request_flag( 'wf_on_sale' ) ) {
			$chips[] = array(
				'label' => __( 'Sale: On sale', 'woo-filters' ),
				'url'   => $this->build_remove_filter_url( 'wf_on_sale' ),
			);
		}

		return $chips;
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
			$live_count = $this->get_contextual_term_count( 'product_cat', $term->slug, 'wf_cat' );
			$is_active  = $selected === $term->slug;
			$disabled   = ! $is_active && 0 === $live_count;
			$disabled_a = $disabled ? ' disabled="disabled"' : '';
			$label_c    = $disabled ? ' class="is-disabled"' : '';

			echo '<li><label' . $label_c . '><input type="radio" name="wf_cat" value="' . esc_attr( $term->slug ) . '"' . $disabled_a . ' ' . checked( $selected, $term->slug, false ) . ' /> <span>' . esc_html( $term->name ) . '</span><small>' . esc_html( (string) $live_count ) . '</small></label></li>';
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
	private function render_term_checkboxes( string $taxonomy, string $field_name, string $request_key, array $selected_values ): void {
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

		$list_id = 'wf-term-list-' . sanitize_key( $taxonomy );

		if ( count( $terms ) > 7 ) {
			echo '<div class="wf-option-search-wrap">';
			echo '<input type="search" class="wf-option-search" data-list-id="' . esc_attr( $list_id ) . '" placeholder="' . esc_attr__( 'Search options...', 'woo-filters' ) . '" aria-label="' . esc_attr__( 'Search filter options', 'woo-filters' ) . '" />';
			echo '</div>';
		}

		echo '<ul id="' . esc_attr( $list_id ) . '" class="wf-term-list">';
		foreach ( $terms as $term ) {
			$live_count = $this->get_contextual_term_count( $taxonomy, $term->slug, $request_key );
			$checked    = in_array( $term->slug, $selected_values, true );
			$disabled   = ! $checked && 0 === $live_count;
			$disabled_a = $disabled ? ' disabled="disabled"' : '';
			$label_c    = $disabled ? ' class="is-disabled"' : '';

			echo '<li>';
			echo '<label' . $label_c . '>';
			echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $term->slug ) . '"' . $disabled_a . ' ' . checked( $checked, true, false ) . ' />';
			echo '<span>' . esc_html( $term->name ) . '</span>';
			echo '<small>' . esc_html( (string) $live_count ) . '</small>';
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

		$args = $this->with_security_args( $args );

		return add_query_arg( $args, $this->get_archive_url() );
	}

	/**
	 * Build products query for shortcode context.
	 *
	 * @param int $per_page Products per page.
	 * @param int $paged    Current page.
	 * @return \WP_Query
	 */
	private function get_shortcode_products_query( int $per_page, int $paged ): \WP_Query {
		if ( ! $this->is_valid_filter_request() ) {
			return new \WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'paged'          => max( 1, $paged ),
					'posts_per_page' => min( self::MAX_PER_PAGE, $per_page ),
				)
			);
		}

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'paged'          => max( 1, $paged ),
			'posts_per_page' => min( self::MAX_PER_PAGE, $per_page ),
		);

		$tax_clauses  = array();
		$meta_clauses = array();
		$logic_mode   = $this->get_request_multiselect_mode();
		$tax_operator = 'and' === $logic_mode ? 'AND' : 'IN';

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
				'operator' => $tax_operator,
			);
		}

		$selected_colors = $this->get_request_slug_list( 'wf_color' );
		if ( '' !== $this->color_taxonomy && ! empty( $selected_colors ) ) {
			$tax_clauses[] = array(
				'taxonomy' => $this->color_taxonomy,
				'field'    => 'slug',
				'terms'    => $selected_colors,
				'operator' => $tax_operator,
			);
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

		if ( ! empty( $tax_clauses ) ) {
			$query_args['tax_query'] = $this->merge_query_clauses( array(), $tax_clauses );
		}

		if ( ! empty( $meta_clauses ) ) {
			$query_args['meta_query'] = $this->merge_query_clauses( array(), $meta_clauses );
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Render pagination for shortcode product query.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	private function render_shortcode_pagination( \WP_Query $query ): void {
		if ( $query->max_num_pages <= 1 ) {
			return;
		}

		$current_url = $this->get_archive_url();
		$page_base   = remove_query_arg( array( 'paged', 'product-page' ), $current_url );
		$base_args   = $this->with_security_args( array() );

		echo '<nav class="woocommerce-pagination" aria-label="' . esc_attr__( 'Product Pagination', 'woo-filters' ) . '">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => esc_url_raw( add_query_arg( array_merge( $base_args, array( 'paged' => '%#%' ) ), $page_base ) ),
					'format'    => '',
					'current'   => max( 1, $query->get( 'paged' ) ),
					'total'     => max( 1, (int) $query->max_num_pages ),
					'type'      => 'list',
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
				)
			)
		);
		echo '</nav>';
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
			return add_query_arg( $this->with_security_args( $args ), $this->get_archive_url() );
		}

		if ( '' === $value_to_remove || ! is_array( $args[ $key ] ) ) {
			unset( $args[ $key ] );
			return add_query_arg( $this->with_security_args( $args ), $this->get_archive_url() );
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

		return add_query_arg( $this->with_security_args( $args ), $this->get_archive_url() );
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
			$args['wf_logic'],
			$args['min_price'],
			$args['max_price'],
			$args['rating_filter'],
			$args['wf_in_stock'],
			$args['wf_on_sale'],
			$args['paged'],
			$args['product-page']
		);

		return add_query_arg( $this->with_security_args( $args ), $this->get_archive_url() );
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
	 * Add security-related query args.
	 *
	 * @param array $args Existing args.
	 * @return array
	 */
	private function with_security_args( array $args ): array {
		$args['wf_nonce'] = $this->get_filter_nonce();

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
	 * Parse request key as boolean flag.
	 *
	 * @param string $key Query key.
	 * @return bool
	 */
	private function get_request_flag( string $key ): bool {
		return 1 === $this->get_request_absint( $key );
	}

	/**
	 * Parse request key for multi-select relation mode.
	 *
	 * @return string
	 */
	private function get_request_multiselect_mode(): string {
		if ( ! isset( $_GET['wf_logic'] ) ) {
			return 'or';
		}

		$value = sanitize_key( wp_unslash( (string) $_GET['wf_logic'] ) );

		return 'and' === $value ? 'and' : 'or';
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
	 * Build normalized filter clauses from request.
	 *
	 * @param array $exclude_keys Keys to ignore while building clauses.
	 * @return array{tax: array, meta: array}
	 */
	private function get_request_filter_clauses( array $exclude_keys = array() ): array {
		$filter_options = FilterSettings::get_options();
		$excluded = array_fill_keys( $exclude_keys, true );
		$logic_mode = $this->get_request_multiselect_mode();
		$tax_operator = 'and' === $logic_mode ? 'AND' : 'IN';

		$tax_clauses  = array();
		$meta_clauses = array();

		if ( ! isset( $excluded['wf_cat'] ) && isset( $filter_options['show_categories'] ) && 'yes' === $filter_options['show_categories'] ) {
			$selected_category = $this->get_request_slug( 'wf_cat' );
			if ( '' !== $selected_category ) {
				$tax_clauses[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( $selected_category ),
				);
			}
		}

		if ( ! isset( $excluded['wf_brand'] ) && isset( $filter_options['show_brands'] ) && 'yes' === $filter_options['show_brands'] ) {
			$selected_brands = $this->get_request_slug_list( 'wf_brand' );
			if ( '' !== $this->brand_taxonomy && ! empty( $selected_brands ) ) {
				$tax_clauses[] = array(
					'taxonomy' => $this->brand_taxonomy,
					'field'    => 'slug',
					'terms'    => $selected_brands,
					'operator' => $tax_operator,
				);
			}
		}

		if ( ! isset( $excluded['wf_color'] ) && isset( $filter_options['show_colors'] ) && 'yes' === $filter_options['show_colors'] ) {
			$selected_colors = $this->get_request_slug_list( 'wf_color' );
			if ( '' !== $this->color_taxonomy && ! empty( $selected_colors ) ) {
				$tax_clauses[] = array(
					'taxonomy' => $this->color_taxonomy,
					'field'    => 'slug',
					'terms'    => $selected_colors,
					'operator' => $tax_operator,
				);
			}
		}

		if ( ( ! isset( $excluded['min_price'] ) || ! isset( $excluded['max_price'] ) ) && isset( $filter_options['show_price'] ) && 'yes' === $filter_options['show_price'] ) {
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
		}

		if ( ! isset( $excluded['rating_filter'] ) && isset( $filter_options['show_rating'] ) && 'yes' === $filter_options['show_rating'] ) {
			$rating = $this->get_request_absint( 'rating_filter' );
			if ( $rating > 0 && $rating <= 5 ) {
				$meta_clauses[] = array(
					'key'     => '_wc_average_rating',
					'value'   => (float) $rating,
					'compare' => '>=',
					'type'    => 'DECIMAL(10,2)',
				);
			}
		}

		if ( ! isset( $excluded['wf_in_stock'] ) && isset( $filter_options['show_availability'] ) && 'yes' === $filter_options['show_availability'] && $this->get_request_flag( 'wf_in_stock' ) ) {
			$meta_clauses[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		if ( ! isset( $excluded['wf_on_sale'] ) && isset( $filter_options['show_availability'] ) && 'yes' === $filter_options['show_availability'] && $this->get_request_flag( 'wf_on_sale' ) ) {
			$meta_clauses[] = array(
				'key'     => '_sale_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
		}

		return array(
			'tax'  => $tax_clauses,
			'meta' => $meta_clauses,
		);
	}

	/**
	 * Get live product count for a term in the current filter context.
	 *
	 * @param string $taxonomy   Term taxonomy.
	 * @param string $term_slug  Term slug.
	 * @param string $source_key Filter key to exclude from baseline context.
	 * @return int
	 */
	private function get_contextual_term_count( string $taxonomy, string $term_slug, string $source_key ): int {
		$cache_key = $this->build_cache_key( 'ctx_count_' . md5( wp_json_encode( array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
			'source'   => $source_key,
			'query'    => $this->get_current_query_args(),
		) ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$clauses = $this->get_request_filter_clauses( array( $source_key ) );
		$clauses['tax'][] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array( $term_slug ),
		);

		$query_args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'suppress_filters'       => false,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $clauses['tax'] ) ) {
			$query_args['tax_query'] = $this->merge_query_clauses( array(), $clauses['tax'] );
		}

		if ( ! empty( $clauses['meta'] ) ) {
			$query_args['meta_query'] = $this->merge_query_clauses( array(), $clauses['meta'] );
		}

		$count_query = new \WP_Query( $query_args );
		$count       = (int) $count_query->found_posts;

		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, 300 );

		return $count;
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
		$cache_key = $this->build_cache_key( 'terms_' . md5( wp_json_encode( $args ) ) );
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
		if ( $this->is_shortcode_context && '' !== $this->shortcode_action_url ) {
			return $this->shortcode_action_url;
		}

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
	 * Get default shop page URL.
	 *
	 * @return string
	 */
	private function get_shop_page_url(): string {
		return wc_get_page_permalink( 'shop' );
	}

	/**
	 * Return current filter nonce.
	 *
	 * @return string
	 */
	private function get_filter_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Determine whether current singular page has shortcode usage.
	 *
	 * @return bool
	 */
	private function has_shortcode_on_current_page(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_shortcode( (string) $post->post_content, 'woo_filters' );
	}

	/**
	 * Validate request nonce for filter operations.
	 *
	 * @return bool
	 */
	private function is_valid_filter_request(): bool {
		if ( ! $this->has_filter_query_keys() ) {
			return true;
		}

		if ( ! isset( $_GET['wf_nonce'] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['wf_nonce'] ) );

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Detect if current request contains filter-related keys.
	 *
	 * @return bool
	 */
	private function has_filter_query_keys(): bool {
		foreach ( array_keys( $_GET ) as $key ) {
			$normalized_key = sanitize_key( (string) $key );
			if ( '' === $normalized_key ) {
				continue;
			}

			if ( in_array( $normalized_key, $this->get_filter_request_keys(), true ) ) {
				return true;
			}

			if ( 0 === strpos( $normalized_key, 'wf_attr_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return request keys recognized as filter controls.
	 *
	 * @return array
	 */
	private function get_filter_request_keys(): array {
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
			'wf_per_page',
			'paged',
			'product-page',
		);
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
	 * Get CSS class name for active style preset.
	 *
	 * @return string
	 */
	private function get_layout_skin_class(): string {
		$options = StyleSettings::get_options();
		$skin    = isset( $options['preset_skin'] ) ? sanitize_key( (string) $options['preset_skin'] ) : 'classic';

		return 'wf-skin-' . $skin;
	}

	/**
	 * Get global product price boundaries for slider controls.
	 *
	 * @return array{min: float, max: float}
	 */
	private function get_price_bounds(): array {
		$cache_key = $this->build_cache_key( 'price_bounds' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
			return array(
				'min' => (float) $cached['min'],
				'max' => (float) $cached['max'],
			);
		}

		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT
				MIN(CAST(pm.meta_value AS DECIMAL(20, 4))) AS min_price,
				MAX(CAST(pm.meta_value AS DECIMAL(20, 4))) AS max_price
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = %s
				AND p.post_status = %s",
			'_price',
			'product',
			'publish'
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		$min = isset( $row['min_price'] ) ? (float) $row['min_price'] : 0.0;
		$max = isset( $row['max_price'] ) ? (float) $row['max_price'] : 0.0;

		if ( $min < 0 ) {
			$min = 0.0;
		}

		if ( $max <= $min ) {
			$max = $min + 100;
		}

		$bounds = array(
			'min' => $min,
			'max' => $max,
		);

		wp_cache_set( $cache_key, $bounds, self::CACHE_GROUP, 300 );

		return $bounds;
	}

	/**
	 * Build namespaced cache key using rolling invalidation marker.
	 *
	 * @param string $suffix Cache suffix.
	 * @return string
	 */
	private function build_cache_key( string $suffix ): string {
		return $suffix . ':' . $this->get_cache_last_changed();
	}

	/**
	 * Get global last-changed marker for filter cache namespace.
	 *
	 * @return string
	 */
	private function get_cache_last_changed(): string {
		$last_changed = get_option( 'wf_cache_last_changed', '' );
		if ( ! is_string( $last_changed ) || '' === $last_changed ) {
			$last_changed = (string) microtime( true );
			update_option( 'wf_cache_last_changed', $last_changed, false );
		}

		return $last_changed;
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

		$formatted = rtrim( rtrim( sprintf( '%.4F', $value ), '0' ), '.' );

		return '' !== $formatted ? $formatted : '0';
	}
}
