<?php
/**
 * WooCommerce Blocks integration.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Pro catalog filters block.
 *
 * The free plugin registers a bare render-only block under the same name and
 * bails when the name is taken, so registering earlier hands the name to this
 * richer version with an editor settings panel and block patterns.
 */
class BlocksIntegration {

	/**
	 * Block name, shared with the free plugin.
	 */
	private const BLOCK_NAME = 'cscf/catalog-filters';

	/**
	 * Editor script handle.
	 */
	private const EDITOR_HANDLE = 'cscfp-block-editor';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Priority 5 so this wins the block name over the free plugin's
		// default-priority registration, which no-ops once the name is taken.
		add_action( 'init', array( $this, 'register_block' ), 5 );
		add_action( 'init', array( $this, 'register_patterns' ), 15 );
	}

	/**
	 * Register the block with its editor attributes.
	 *
	 * @return void
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( class_exists( '\WP_Block_Type_Registry' ) && \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return;
		}

		$this->register_editor_script();

		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'     => 3,
				'title'           => __( 'Catalog Filters', 'clandevs-smart-catalog-filters' ),
				'description'     => __( 'Filter sidebar and product grid for the WooCommerce catalog.', 'clandevs-smart-catalog-filters' ),
				'category'        => 'woocommerce',
				'icon'            => 'filter',
				'keywords'        => array( 'filter', 'catalog', 'shop', 'product' ),
				'editor_script'   => self::EDITOR_HANDLE,
				'render_callback' => array( $this, 'render_block' ),
				'supports'        => array(
					'html'   => false,
					'align'  => array( 'wide', 'full' ),
					'anchor' => true,
				),
				'attributes'      => array(
					'showFilters'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showPagination' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'layout'         => array(
						'type'    => 'string',
						'default' => 'sidebar',
					),
					'columns'        => array(
						'type'    => 'number',
						'default' => 4,
					),
					'perPage'        => array(
						'type'    => 'number',
						'default' => 12,
					),
					'category'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'className'      => array( 'type' => 'string' ),
					'align'          => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * Register the editor script backing the settings panel.
	 *
	 * @return void
	 */
	private function register_editor_script(): void {
		wp_register_script(
			self::EDITOR_HANDLE,
			CSCFP_PLUGIN_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			CSCFP_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::EDITOR_HANDLE, 'clandevs-smart-catalog-filters' );
		}

		wp_localize_script(
			self::EDITOR_HANDLE,
			'cscfpBlock',
			array(
				'categories' => $this->get_category_options(),
			)
		);
	}

	/**
	 * Register block patterns pairing the filters with a product grid.
	 *
	 * @return void
	 */
	public function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'cscf-filters',
			array( 'label' => __( 'Smart Catalog Filters', 'clandevs-smart-catalog-filters' ) )
		);

		register_block_pattern(
			'cscf/sidebar-and-grid',
			array(
				'title'      => __( 'Filter sidebar with product grid', 'clandevs-smart-catalog-filters' ),
				'categories' => array( 'cscf-filters' ),
				'content'    => '<!-- wp:' . self::BLOCK_NAME . ' {"layout":"sidebar","columns":3,"perPage":12} /-->',
			)
		);

		register_block_pattern(
			'cscf/horizontal-and-grid',
			array(
				'title'      => __( 'Horizontal filters above product grid', 'clandevs-smart-catalog-filters' ),
				'categories' => array( 'cscf-filters' ),
				'content'    => '<!-- wp:' . self::BLOCK_NAME . ' {"layout":"horizontal","columns":4,"perPage":16} /-->',
			)
		);
	}

	/**
	 * Render the block on the frontend.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( array $attributes = array() ): string {
		if ( ! class_exists( 'ClandevsSmartCatalogFilters\ShopFilters' ) ) {
			return '';
		}

		$layout = 'horizontal' === ( $attributes['layout'] ?? 'sidebar' ) ? 'horizontal' : 'sidebar';

		$atts = array(
			'show_filters'    => ! isset( $attributes['showFilters'] ) || $attributes['showFilters'] ? 'yes' : 'no',
			'show_pagination' => ! isset( $attributes['showPagination'] ) || $attributes['showPagination'] ? 'yes' : 'no',
			'columns'         => max( 1, min( 6, absint( $attributes['columns'] ?? 4 ) ) ),
			'per_page'        => max( 1, absint( $attributes['perPage'] ?? 12 ) ),
			'category'        => sanitize_title( (string) ( $attributes['category'] ?? '' ) ),
		);

		$pairs = array();

		foreach ( $atts as $key => $value ) {
			$pairs[] = sprintf( '%s="%s"', $key, esc_attr( (string) $value ) );
		}

		$classes = 'cscfp-block cscfp-layout-' . $layout;

		if ( ! empty( $attributes['className'] ) ) {
			$classes .= ' ' . sanitize_html_class( (string) $attributes['className'] );
		}

		return '<div class="' . esc_attr( $classes ) . '">'
			. do_shortcode( '[clandevs_catalog_filters ' . implode( ' ', $pairs ) . ']' )
			. '</div>';
	}

	/**
	 * Product categories offered in the editor panel.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_category_options(): array {
		$options = array(
			array(
				'label' => __( 'All categories', 'clandevs-smart-catalog-filters' ),
				'value' => '',
			),
		);

		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return $options;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$options[] = array(
				'label' => $term->name,
				'value' => $term->slug,
			);
		}

		return $options;
	}
}
