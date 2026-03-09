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

	/** @var array<int, string> */
	private $custom_attribute_taxonomies = array();

	/** @var string */
	private $plugin_url = '';

	/** @var string */
	private $asset_version = '0.4.0';

	/** @var bool */
	private $is_shortcode_context = false;

	/** @var string */
	private $shortcode_action_url = '';

	/** @var int */
	private $filter_form_instance = 0;

	/** @var bool */
	private $assets_enqueued = false;

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

		add_filter( 'loop_shop_per_page', array( $this, 'filter_loop_per_page' ), 20 );

		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
		add_action( 'woocommerce_no_products_found', array( $this, 'render_no_products_state' ), 10 );
	}

	/**
	 * Invalidate cached filter metadata keys.
	 *
	 * @return void
	 */
	public function invalidate_filter_cache( ...$args ): void {
		unset( $args );
		update_option( 'wf_cache_last_changed', (string) microtime( true ), false );
	}

	/**
	 * Resolve dynamic taxonomies after WooCommerce registers attributes.
	 *
	 * @return void
	 */
	public function bootstrap_taxonomies(): void {
		$this->brand_taxonomy              = $this->resolve_taxonomy( array( 'pa_brand', 'product_brand', 'brand' ) );
		$this->color_taxonomy              = $this->resolve_taxonomy( array( 'pa_color', 'color' ) );
		$this->custom_attribute_taxonomies = $this->get_filterable_attribute_taxonomies();
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

		$this->enqueue_frontend_assets();
	}

	/**
	 * Enqueue frontend style/script once per request.
	 *
	 * @return void
	 */
	private function enqueue_frontend_assets(): void {
		if ( $this->assets_enqueued ) {
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

		$this->assets_enqueued = true;
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
			'--wf-text-color'      => isset( $options['text_color'] ) ? (string) $options['text_color'] : '#1f2937',
			'--wf-muted-text'      => isset( $options['muted_text_color'] ) ? (string) $options['muted_text_color'] : '#64748b',
			'--wf-chip-bg'         => isset( $options['chip_bg_color'] ) ? (string) $options['chip_bg_color'] : '#ffffff',
			'--wf-chip-border'     => isset( $options['chip_border_color'] ) ? (string) $options['chip_border_color'] : '#c7d5e3',
			'--wf-button-bg'       => isset( $options['button_bg_color'] ) ? (string) $options['button_bg_color'] : '#4b5563',
			'--wf-button-text'     => isset( $options['button_text_color'] ) ? (string) $options['button_text_color'] : '#ffffff',
			'--wf-input-bg'        => isset( $options['input_bg_color'] ) ? (string) $options['input_bg_color'] : '#ffffff',
			'--wf-control-border'  => isset( $options['control_border_color'] ) ? (string) $options['control_border_color'] : '#cbd5e1',
			'--wf-font-family'     => isset( $options['font_family'] ) ? (string) $options['font_family'] : 'inherit',
			'--wf-font-size'       => ( isset( $options['font_size'] ) ? absint( $options['font_size'] ) : 16 ) . 'px',
			'--wf-sidebar-width'   => ( isset( $options['sidebar_width'] ) ? absint( $options['sidebar_width'] ) : 280 ) . 'px',
			'--wf-layout-gap'      => ( isset( $options['layout_gap'] ) ? absint( $options['layout_gap'] ) : 24 ) . 'px',
			'--wf-sidebar-radius'  => ( isset( $options['sidebar_radius'] ) ? absint( $options['sidebar_radius'] ) : 14 ) . 'px',
			'--wf-control-radius'  => ( isset( $options['control_radius'] ) ? absint( $options['control_radius'] ) : 10 ) . 'px',
			'--wf-button-radius'   => ( isset( $options['button_radius'] ) ? absint( $options['button_radius'] ) : 12 ) . 'px',
			'--wf-section-spacing' => ( isset( $options['section_spacing'] ) ? absint( $options['section_spacing'] ) : 18 ) . 'px',
			'--wf-empty-bg'        => isset( $options['no_results_bg_color'] ) ? (string) $options['no_results_bg_color'] : '#ffffff',
			'--wf-empty-border'    => isset( $options['no_results_border_color'] ) ? (string) $options['no_results_border_color'] : '#dbe2ea',
			'--wf-empty-shadow'    => $this->hex_to_rgba( isset( $options['no_results_shadow_color'] ) ? (string) $options['no_results_shadow_color'] : '#0f172a', 0.16 ),
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
	 * Convert hex color to rgba() string.
	 *
	 * @param string $hex_color Hex color value.
	 * @param float  $alpha     Alpha from 0 to 1.
	 * @return string
	 */
	private function hex_to_rgba( string $hex_color, float $alpha ): string {
		$safe_hex = sanitize_hex_color( $hex_color );
		if ( ! is_string( $safe_hex ) || '' === $safe_hex ) {
			return 'rgba(15, 23, 42, 0.16)';
		}

		$hex = ltrim( $safe_hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) ) {
			return 'rgba(15, 23, 42, 0.16)';
		}

		$red   = hexdec( substr( $hex, 0, 2 ) );
		$green = hexdec( substr( $hex, 2, 2 ) );
		$blue  = hexdec( substr( $hex, 4, 2 ) );
		$alpha = max( 0.0, min( 1.0, $alpha ) );

		return sprintf( 'rgba(%d, %d, %d, %.2f)', $red, $green, $blue, $alpha );
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
		$post_in      = $clauses['post_in'];

		if ( ! empty( $tax_clauses ) ) {
			$query->set( 'tax_query', $this->merge_query_clauses( (array) $query->get( 'tax_query' ), $tax_clauses ) );
		}

		if ( ! empty( $meta_clauses ) ) {
			$query->set( 'meta_query', $this->merge_query_clauses( (array) $query->get( 'meta_query' ), $meta_clauses ) );
		}

		if ( ! empty( $post_in ) ) {
			$query->set( 'post__in', $this->merge_post_in_values( $query->get( 'post__in' ), $post_in ) );
		}
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( array $atts = array() ): string {
		$this->enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'per_page'        => '12',
				'columns'         => '4',
				'category'        => '',
				'show_filters'    => 'yes',
				'show_pagination' => 'yes',
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
		$forced_category = sanitize_title( (string) $atts['category'] );
		$show_filters    = $this->parse_shortcode_bool( (string) $atts['show_filters'], true );
		$show_pagination = $this->parse_shortcode_bool( (string) $atts['show_pagination'], true );

		$paged = max( 1, $this->get_request_absint( 'paged' ) );
		if ( $paged <= 1 ) {
			$paged = max( 1, $this->get_request_absint( 'product-page' ) );
		}

		$query = $this->get_shortcode_products_query( $per_page, $paged, $forced_category );

		$this->is_shortcode_context = true;
		$this->shortcode_action_url = get_permalink();
		$skin_class                 = $this->get_layout_skin_class();

		ob_start();
		$layout_class = 'wf-shop-layout alignwide wf-shortcode-layout ' . $skin_class;
		if ( ! $show_filters ) {
			$layout_class .= ' wf-shortcode-no-sidebar';
		}

		echo '<div class="' . esc_attr( $layout_class ) . '">';
		if ( $show_filters ) {
			echo '<button type="button" class="wf-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'woo-filters' ) . '</button>';
			echo '<div class="wf-sidebar-overlay" aria-hidden="true"></div>';
			echo '<div class="wf-sidebar" role="complementary" aria-label="' . esc_attr__( 'Shop filters', 'woo-filters' ) . '">';
			echo '<button type="button" class="wf-sidebar-close" aria-label="' . esc_attr__( 'Close filters', 'woo-filters' ) . '">&times;</button>';
			$this->render_filter_form();
			echo '</div>';
		}
		echo '<div class="wf-products">';

		if ( $query->have_posts() ) {
			echo '<ul class="products columns-' . esc_attr( (string) $columns ) . '">';
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			echo '</ul>';
			if ( $show_pagination ) {
				$this->render_shortcode_pagination( $query );
			}
		} else {
			$this->render_no_products_state();
		}

		echo '</div>';
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

		echo '<div class="wf-shop-layout alignwide ' . esc_attr( $this->get_layout_skin_class() ) . '">';
		echo '<button type="button" class="wf-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'woo-filters' ) . '</button>';
		echo '<div class="wf-sidebar-overlay" aria-hidden="true"></div>';
		echo '<div class="wf-sidebar" role="complementary" aria-label="' . esc_attr__( 'Shop filters', 'woo-filters' ) . '">';
		echo '<button type="button" class="wf-sidebar-close" aria-label="' . esc_attr__( 'Close filters', 'woo-filters' ) . '">&times;</button>';
		$this->render_filter_form();
		echo '</div>';
		echo '<div class="wf-products">';
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

		echo '</div>';
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
			$url        = $this->build_filter_url_for_per_page( (int) $value );
			$active     = ( 0 === (int) $value && $current >= $total && $total > 0 ) || ( (int) $value > 0 && $current === (int) $value );
			$class_name = $active ? 'is-active' : '';

			echo '<a class="' . esc_attr( $class_name ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $label ) . '</a>';
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

		$action              = $this->get_archive_url();
		$selected_brands     = $this->get_request_slug_list( 'wf_brand' );
		$selected_colors     = $this->get_request_slug_list( 'wf_color' );
		$selected_attributes = array();
		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			$request_key                         = $this->get_attribute_request_key( $attribute_taxonomy );
			$selected_attributes[ $request_key ] = $this->get_request_slug_list( $request_key );
		}
		$multiselect_mode = $this->get_request_multiselect_mode();
		$selected_rating  = $this->get_request_absint( 'rating_filter' );
		$in_stock_only    = $this->get_request_flag( 'wf_in_stock' );
		$on_sale_only     = $this->get_request_flag( 'wf_on_sale' );
		$min_price        = null;
		$max_price        = null;
		$slider_min       = 0.0;
		$slider_max       = 0.0;
		$current_min      = 0.0;
		$current_max      = 0.0;

		if ( $show_price ) {
			$min_price    = $this->get_request_decimal( 'min_price' );
			$max_price    = $this->get_request_decimal( 'max_price' );
			$price_bounds = $this->get_price_bounds();
			$slider_min   = $price_bounds['min'];
			$slider_max   = $price_bounds['max'];
			$current_min  = null !== $min_price ? $min_price : $slider_min;
			$current_max  = null !== $max_price ? $max_price : $slider_max;

			if ( $current_min > $current_max ) {
				$tmp         = $current_min;
				$current_min = $current_max;
				$current_max = $tmp;
			}
		}

		$list_suffix = (string) ++$this->filter_form_instance;

		echo '<form class="wf-filter-form" method="get" action="' . esc_url( $action ) . '">';
		$excluded_preserved = array( 'wf_cat', 'wf_brand', 'wf_color', 'wf_logic', 'min_price', 'max_price', 'rating_filter', 'wf_in_stock', 'wf_on_sale', 'paged', 'product-page' );
		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			$excluded_preserved[] = $this->get_attribute_request_key( $attribute_taxonomy );
		}
		$this->render_preserved_fields( $excluded_preserved );
		$this->render_active_filters();

		if ( $show_categories ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Categories', 'woo-filters' ) . '</h4>';
			$this->render_categories( $list_suffix );
			echo '</div>';
		}

		if ( $show_brands && '' !== $this->brand_taxonomy ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Filter by Brands', 'woo-filters' ) . '</h4>';
			$this->render_term_checkboxes( $this->brand_taxonomy, 'wf_brand[]', 'wf_brand', $selected_brands, false, $list_suffix );
			echo '</div>';
		}

		echo '<div class="wf-filter-block">';
		echo '<h4>' . esc_html__( 'Multi-select Logic', 'woo-filters' ) . '</h4>';
		echo '<label class="wf-radio"><input type="radio" name="wf_logic" value="or" ' . checked( $multiselect_mode, 'or', false ) . ' /> <span>' . esc_html__( 'Match any selected option (OR)', 'woo-filters' ) . '</span></label>';
		echo '<label class="wf-radio"><input type="radio" name="wf_logic" value="and" ' . checked( $multiselect_mode, 'and', false ) . ' /> <span>' . esc_html__( 'Match all selected options (AND)', 'woo-filters' ) . '</span></label>';
		echo '</div>';

		if ( $show_price ) {
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
		}

		if ( $show_rating ) {
			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html__( 'Customer Rating', 'woo-filters' ) . '</h4>';
			for ( $i = 5; $i >= 1; $i-- ) {
				echo '<label class="wf-rating">';
				echo '<input type="radio" name="rating_filter" value="' . esc_attr( (string) $i ) . '" ' . checked( $selected_rating, $i, false ) . ' />';
				echo '<span class="wf-rating-stars">';
				for ( $s = 0; $s < $i; $s++ ) {
					echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
				}
				for ( $s = $i; $s < 5; $s++ ) {
					echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14" class="wf-star-empty"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
				}
				echo '</span>';
				echo '<span class="wf-rating-text">' . esc_html__( '& up', 'woo-filters' ) . '</span>';
				echo '</label>';
			}
			echo '<label class="wf-rating">';
			echo '<input type="radio" name="rating_filter" value="" ' . checked( $selected_rating, 0, false ) . ' />';
			echo '<span class="wf-rating-text">' . esc_html__( 'Any', 'woo-filters' ) . '</span>';
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
			$this->render_term_checkboxes( $this->color_taxonomy, 'wf_color[]', 'wf_color', $selected_colors, true, $list_suffix );
			echo '</div>';
		}

		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			$request_key        = $this->get_attribute_request_key( $attribute_taxonomy );
			$field_name         = $request_key . '[]';
			$selected           = isset( $selected_attributes[ $request_key ] ) && is_array( $selected_attributes[ $request_key ] ) ? $selected_attributes[ $request_key ] : array();
			$is_color_attribute = $this->is_color_like_taxonomy( $attribute_taxonomy );

			echo '<div class="wf-filter-block">';
			echo '<h4>' . esc_html( $this->get_attribute_display_label( $attribute_taxonomy ) ) . '</h4>';
			$this->render_term_checkboxes( $attribute_taxonomy, $field_name, $request_key, $selected, $is_color_attribute, $list_suffix );
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
		$chips          = array();

		$selected_category = $this->get_request_slug( 'wf_cat' );
		if ( isset( $filter_options['show_categories'] ) && 'yes' === $filter_options['show_categories'] && '' !== $selected_category ) {
			$term = get_term_by( 'slug', $selected_category, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$chips[] = array(
					/* translators: %s: product category name. */
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
		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			$request_key = $this->get_attribute_request_key( $attribute_taxonomy );
			$label       = $this->get_attribute_display_label( $attribute_taxonomy );
			$chips       = array_merge( $chips, $this->get_term_chips_from_selected( $attribute_taxonomy, $request_key, $label ) );
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
				/* translators: %s: minimum price with currency symbol. */
				'label' => sprintf( __( 'Min: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $min_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'min_price' ),
			);
		}

		$max_price = $this->get_request_decimal( 'max_price' );
		if ( isset( $filter_options['show_price'] ) && 'yes' === $filter_options['show_price'] && null !== $max_price ) {
			$chips[] = array(
				/* translators: %s: maximum price with currency symbol. */
				'label' => sprintf( __( 'Max: %s', 'woo-filters' ), wp_strip_all_tags( wc_price( (float) $max_price ), true ) ),
				'url'   => $this->build_remove_filter_url( 'max_price' ),
			);
		}

		$rating = $this->get_request_absint( 'rating_filter' );
		if ( isset( $filter_options['show_rating'] ) && 'yes' === $filter_options['show_rating'] && $rating > 0 && $rating <= 5 ) {
			$chips[] = array(
				/* translators: %d: star rating threshold. */
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
	private function render_categories( string $list_suffix = '' ): void {
		$selected              = $this->get_request_slug( 'wf_cat' );
		$use_contextual_counts = $this->has_filter_query_args() && $this->is_valid_filter_request();
		$terms                 = $this->get_terms_cached(
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

		$list_id = 'wf-cat-list-' . sanitize_key( $list_suffix );

		echo '<ul id="' . esc_attr( $list_id ) . '" class="wf-cat-list">';
		echo '<li><label><input type="radio" name="wf_cat" value="" ' . checked( $selected, '', false ) . ' /> <span>' . esc_html__( 'All Categories', 'woo-filters' ) . '</span></label></li>';
		foreach ( $terms as $term ) {
			$live_count  = $use_contextual_counts ? $this->get_contextual_term_count( 'product_cat', $term->slug, 'wf_cat' ) : (int) $term->count;
			$is_active   = $selected === $term->slug;
			$disabled    = ! $is_active && 0 === $live_count;
			$label_class = $disabled ? 'is-disabled' : '';

			echo '<li><label class="' . esc_attr( $label_class ) . '"><input type="radio" name="wf_cat" value="' . esc_attr( $term->slug ) . '"' . disabled( $disabled, true, false ) . ' ' . checked( $selected, $term->slug, false ) . ' /> <span>' . esc_html( $term->name ) . '</span><small>' . esc_html( (string) $live_count ) . '</small></label></li>';
		}
		echo '</ul>';
	}

	/**
	 * Render checkbox list for a taxonomy.
	 *
	 * @param string $taxonomy           Taxonomy key.
	 * @param string $field_name         HTML field name.
	 * @param string $request_key        Request key.
	 * @param array  $selected_values    Selected term slugs.
	 * @param bool   $show_color_swatch  Whether to render color swatches.
	 * @return void
	 */
	private function render_term_checkboxes( string $taxonomy, string $field_name, string $request_key, array $selected_values, bool $show_color_swatch = false, string $list_suffix = '' ): void {
		$use_contextual_counts = $this->has_filter_query_args() && $this->is_valid_filter_request();
		$terms                 = $this->get_terms_cached(
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

		$list_id = 'wf-term-list-' . sanitize_key( $taxonomy . '-' . $list_suffix );

		if ( count( $terms ) > 7 ) {
			echo '<div class="wf-option-search-wrap">';
			echo '<input type="search" class="wf-option-search" data-list-id="' . esc_attr( $list_id ) . '" placeholder="' . esc_attr__( 'Search options...', 'woo-filters' ) . '" aria-label="' . esc_attr__( 'Search filter options', 'woo-filters' ) . '" />';
			echo '</div>';
		}

		echo '<ul id="' . esc_attr( $list_id ) . '" class="wf-term-list">';
		foreach ( $terms as $term ) {
			$live_count  = $use_contextual_counts ? $this->get_contextual_term_count( $taxonomy, $term->slug, $request_key ) : (int) $term->count;
			$checked     = in_array( $term->slug, $selected_values, true );
			$disabled    = ! $checked && 0 === $live_count;
			$label_class = $disabled ? 'is-disabled' : '';

			echo '<li>';
			echo '<label class="' . esc_attr( $label_class ) . '">';
			echo '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $term->slug ) . '"' . disabled( $disabled, true, false ) . ' ' . checked( $checked, true, false ) . ' />';
			if ( $show_color_swatch ) {
				$swatch_hex = $this->get_term_color_hex( $term );
				if ( '' !== $swatch_hex ) {
					echo '<span class="wf-color-swatch" style="background:' . esc_attr( $swatch_hex ) . ';"></span>';
				}
			}
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
	 * @param int    $per_page        Products per page.
	 * @param int    $paged           Current page.
	 * @param string $forced_category Optional forced category slug.
	 * @return \WP_Query
	 */
	private function get_shortcode_products_query( int $per_page, int $paged, string $forced_category = '' ): \WP_Query {
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'paged'          => max( 1, $paged ),
			'posts_per_page' => min( self::MAX_PER_PAGE, $per_page ),
		);

		$request_category = $this->get_request_slug( 'wf_cat' );
		$forced_taxonomy  = '';
		if ( '' === $request_category && '' !== $forced_category ) {
			$forced_taxonomy = $forced_category;
		}

		if ( ! $this->is_valid_filter_request() ) {
			if ( '' !== $forced_taxonomy ) {
				$query_args['tax_query'] = $this->merge_query_clauses(
					array(),
					array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'slug',
							'terms'    => array( $forced_taxonomy ),
						),
					)
				);
			}

			return new \WP_Query( $query_args );
		}

		$clauses = $this->get_request_filter_clauses();
		if ( '' !== $forced_taxonomy && ! $this->has_taxonomy_in_clauses( $clauses['tax'], 'product_cat' ) ) {
			$clauses['tax'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array( $forced_taxonomy ),
			);
		}

		if ( ! empty( $clauses['tax'] ) ) {
			$query_args['tax_query'] = $this->merge_query_clauses( array(), $clauses['tax'] );
		}

		if ( ! empty( $clauses['meta'] ) ) {
			$query_args['meta_query'] = $this->merge_query_clauses( array(), $clauses['meta'] );
		}

		if ( ! empty( $clauses['post_in'] ) ) {
			$query_args['post__in'] = $clauses['post_in'];
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Parse shortcode yes/no-style boolean attribute.
	 *
	 * @param string $value         Raw value.
	 * @param bool   $default_value Default value.
	 * @return bool
	 */
	private function parse_shortcode_bool( string $value, bool $default_value ): bool {
		$normalized = strtolower( trim( $value ) );
		if ( '' === $normalized ) {
			return $default_value;
		}

		if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		if ( in_array( $normalized, array( '0', 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return $default_value;
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
		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			unset( $args[ $this->get_attribute_request_key( $attribute_taxonomy ) ] );
		}

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
	 * Normalize generated query args.
	 *
	 * @param array $args Existing args.
	 * @return array
	 */
	private function with_security_args( array $args ): array {
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Dynamic key is validated and unslashed in this helper.
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Dynamic key is validated and each item is unslashed in this helper.
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
	 * @return array{tax: array, meta: array, post_in: array<int, int>}
	 */
	private function get_request_filter_clauses( array $exclude_keys = array() ): array {
		$filter_options = FilterSettings::get_options();
		$excluded       = array_fill_keys( $exclude_keys, true );
		$logic_mode     = $this->get_request_multiselect_mode();
		$tax_operator   = 'and' === $logic_mode ? 'AND' : 'IN';

		$tax_clauses  = array();
		$meta_clauses = array();
		$post_in      = array();

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
		foreach ( $this->custom_attribute_taxonomies as $attribute_taxonomy ) {
			$request_key = $this->get_attribute_request_key( $attribute_taxonomy );
			if ( isset( $excluded[ $request_key ] ) ) {
				continue;
			}

			$selected_attribute_terms = $this->get_request_slug_list( $request_key );
			if ( empty( $selected_attribute_terms ) ) {
				continue;
			}

			$tax_clauses[] = array(
				'taxonomy' => $attribute_taxonomy,
				'field'    => 'slug',
				'terms'    => $selected_attribute_terms,
				'operator' => $tax_operator,
			);
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
			$post_in = $this->get_on_sale_product_ids();
		}

		return array(
			'tax'     => $tax_clauses,
			'meta'    => $meta_clauses,
			'post_in' => $post_in,
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
		$cache_key = $this->build_cache_key(
			'ctx_count_' . md5(
				wp_json_encode(
					array(
						'taxonomy' => $taxonomy,
						'slug'     => $term_slug,
						'source'   => $source_key,
						'query'    => $this->get_current_query_args(),
					)
				)
			)
		);
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$clauses          = $this->get_request_filter_clauses( array( $source_key ) );
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

		if ( ! empty( $clauses['post_in'] ) ) {
			$query_args['post__in'] = $clauses['post_in'];
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
	 * @param array $incoming New clauses.
	 * @return array
	 */
	private function merge_query_clauses( array $existing, array $incoming ): array {
		$clauses = array();

		foreach ( $existing as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}

			if ( is_array( $clause ) ) {
				$clauses[] = $clause;
			}
		}

		foreach ( $incoming as $clause ) {
			$clauses[] = $clause;
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		array_unshift( $clauses, array( 'relation' => 'AND' ) );

		return $clauses;
	}

	/**
	 * Merge post inclusion IDs while preserving existing query restrictions.
	 *
	 * @param mixed          $existing     Existing post__in value.
	 * @param array<int,int> $incoming_ids New post IDs to include.
	 * @return array<int,int>
	 */
	private function merge_post_in_values( $existing, array $incoming_ids ): array {
		$existing_ids = is_array( $existing ) ? array_map( 'absint', $existing ) : array();
		$new_ids      = array_map( 'absint', $incoming_ids );

		$existing_ids = array_values( array_unique( $existing_ids ) );
		$new_ids      = array_values( array_unique( $new_ids ) );

		if ( empty( $new_ids ) ) {
			return $existing_ids;
		}

		if ( empty( $existing_ids ) ) {
			return $new_ids;
		}

		$merged = array_values( array_intersect( $existing_ids, $new_ids ) );

		return ! empty( $merged ) ? $merged : array( 0 );
	}

	/**
	 * Check whether taxonomy clause already exists in clause list.
	 *
	 * @param array  $clauses  Existing tax clauses.
	 * @param string $taxonomy Taxonomy key.
	 * @return bool
	 */
	private function has_taxonomy_in_clauses( array $clauses, string $taxonomy ): bool {
		foreach ( $clauses as $clause ) {
			if ( isset( $clause['taxonomy'] ) && $taxonomy === $clause['taxonomy'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve product IDs currently on sale.
	 *
	 * @return array<int,int>
	 */
	private function get_on_sale_product_ids(): array {
		if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
			return array();
		}

		$product_ids = array_map( 'absint', wc_get_product_ids_on_sale() );
		$product_ids = array_values( array_unique( $product_ids ) );

		return ! empty( $product_ids ) ? $product_ids : array( 0 );
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
	 * Resolve filterable product attribute taxonomies excluding dedicated brand/color.
	 *
	 * @return array<int, string>
	 */
	private function get_filterable_attribute_taxonomies(): array {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_attribute_taxonomy_name' ) ) {
			return array();
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return array();
		}

		$resolved = array();
		foreach ( $attribute_taxonomies as $attribute ) {
			if ( ! is_object( $attribute ) || ! isset( $attribute->attribute_name ) ) {
				continue;
			}

			$taxonomy = wc_attribute_taxonomy_name( (string) $attribute->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			if ( $taxonomy === $this->brand_taxonomy || $taxonomy === $this->color_taxonomy ) {
				continue;
			}

			$resolved[] = $taxonomy;
		}

		return array_values( array_unique( $resolved ) );
	}

	/**
	 * Build request key for dynamic attribute taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	private function get_attribute_request_key( string $taxonomy ): string {
		return 'wf_attr_' . sanitize_key( $taxonomy );
	}

	/**
	 * Get display label for an attribute taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return string
	 */
	private function get_attribute_display_label( string $taxonomy ): string {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( $taxonomy_object instanceof \WP_Taxonomy && isset( $taxonomy_object->labels->singular_name ) && '' !== (string) $taxonomy_object->labels->singular_name ) {
			return (string) $taxonomy_object->labels->singular_name;
		}

		return ucwords( str_replace( array( 'pa_', '_' ), array( '', ' ' ), $taxonomy ) );
	}

	/**
	 * Determine whether taxonomy should render color swatches.
	 *
	 * @param string $taxonomy Taxonomy key.
	 * @return bool
	 */
	private function is_color_like_taxonomy( string $taxonomy ): bool {
		return $taxonomy === $this->color_taxonomy || false !== strpos( $taxonomy, 'color' );
	}

	/**
	 * Resolve color swatch hex value from term metadata.
	 *
	 * @param \WP_Term $term Term object.
	 * @return string
	 */
	private function get_term_color_hex( \WP_Term $term ): string {
		$meta_keys = array( 'color', 'sw_color', 'product_attribute_color', 'attribute_pa_color' );
		foreach ( $meta_keys as $meta_key ) {
			$value = get_term_meta( $term->term_id, $meta_key, true );
			if ( ! is_string( $value ) ) {
				continue;
			}

			$color = sanitize_hex_color( $value );
			if ( is_string( $color ) && '' !== $color ) {
				return $color;
			}
		}

		$name_color = sanitize_hex_color( (string) $term->name );

		return is_string( $name_color ) ? $name_color : '';
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
	 * Validate filter request payload.
	 *
	 * @return bool
	 */
	private function is_valid_filter_request(): bool {
		if ( ! $this->has_filter_query_args() ) {
			return true;
		}

		if ( ! isset( $_GET['wf_nonce'] ) ) {
			return true;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['wf_nonce'] ) );
		if ( '' === $nonce ) {
			return true;
		}

		$verified = wp_verify_nonce( $nonce, self::NONCE_ACTION );
		if ( 1 === $verified || 2 === $verified ) {
			return true;
		}

		// Filtering is read-only; stale or missing nonce should not break shareable URLs.
		return true;
	}

	/**
	 * Determine whether current request includes filter-bearing query args.
	 *
	 * @return bool
	 */
	private function has_filter_query_args(): bool {
		$filter_keys = array(
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

		foreach ( $filter_keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				return true;
			}
		}

		foreach ( $_GET as $key => $value ) {
			if ( 0 === strpos( sanitize_key( (string) $key ), 'wf_attr_' ) ) {
				return true;
			}
		}

		return false;
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

		$row = $wpdb->get_row(
			$wpdb->prepare(
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
			),
			ARRAY_A
		);

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
