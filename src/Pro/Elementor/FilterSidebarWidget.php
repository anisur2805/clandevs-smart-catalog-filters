<?php
/**
 * Elementor filter sidebar widget.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro\Elementor;

use ClandevsSmartCatalogFiltersPro\ElementorWidget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Renders the catalog filters with full panel controls.
 */
class FilterSidebarWidget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return ElementorWidget::WIDGET_NAME;
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Catalog Filters', 'clandevs-smart-catalog-filters' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-filter';
	}

	/**
	 * Panel categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( ElementorWidget::CATEGORY, 'woocommerce' );
	}

	/**
	 * Search keywords.
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'filter', 'catalog', 'shop', 'woocommerce', 'product' );
	}

	/**
	 * Frontend styles the widget depends on.
	 *
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array( 'cscfp-shop' );
	}

	/**
	 * Register panel controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'layout_section',
			array(
				'label' => __( 'Layout', 'clandevs-smart-catalog-filters' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'   => __( 'Show Title', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'     => __( 'Title', 'clandevs-smart-catalog-filters' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Filter Products', 'clandevs-smart-catalog-filters' ),
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'sidebar',
				'options' => array(
					'sidebar'    => __( 'Sidebar', 'clandevs-smart-catalog-filters' ),
					'horizontal' => __( 'Horizontal', 'clandevs-smart-catalog-filters' ),
				),
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 6,
				'default' => 4,
			)
		);

		$this->add_control(
			'per_page',
			array(
				'label'   => __( 'Products per page', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 48,
				'default' => 12,
			)
		);

		$this->add_control(
			'show_pagination',
			array(
				'label'   => __( 'Show Pagination', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => __( 'Limit to category', 'clandevs-smart-catalog-filters' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_category_options(),
				'default'     => '',
				'label_block' => true,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'filters_section',
			array(
				'label' => __( 'Filters', 'clandevs-smart-catalog-filters' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_filters',
			array(
				'label'   => __( 'Show Filter Sidebar', 'clandevs-smart-catalog-filters' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'filters_notice',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Individual filter sections are toggled under Smart Catalog Filters → Filter Settings, and reordered under Filter Order.', 'clandevs-smart-catalog-filters' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( 'show_filters' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$layout = 'horizontal' === ( $settings['layout'] ?? 'sidebar' ) ? 'horizontal' : 'sidebar';

		echo '<div class="cscfp-elementor-filters cscfp-layout-' . esc_attr( $layout ) . '">';

		if ( 'yes' === ( $settings['show_title'] ?? 'yes' ) ) {
			$title = isset( $settings['title'] ) && '' !== $settings['title']
				? $settings['title']
				: __( 'Filter Products', 'clandevs-smart-catalog-filters' );

			echo '<h3 class="cscfp-elementor-title">' . esc_html( $title ) . '</h3>';
		}

		echo do_shortcode( $this->build_shortcode( $settings ) );
		echo '</div>';
	}

	/**
	 * Build the shortcode call from panel settings.
	 *
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function build_shortcode( array $settings ): string {
		$atts = array(
			'show_filters'    => 'yes' === ( $settings['show_filters'] ?? 'yes' ) ? 'yes' : 'no',
			'show_pagination' => 'yes' === ( $settings['show_pagination'] ?? 'yes' ) ? 'yes' : 'no',
			'columns'         => max( 1, min( 6, absint( $settings['columns'] ?? 4 ) ) ),
			'per_page'        => max( 1, absint( $settings['per_page'] ?? 12 ) ),
			'category'        => sanitize_title( (string) ( $settings['category'] ?? '' ) ),
		);

		$pairs = array();

		foreach ( $atts as $key => $value ) {
			$pairs[] = sprintf( '%s="%s"', $key, esc_attr( (string) $value ) );
		}

		return '[clandevs_catalog_filters ' . implode( ' ', $pairs ) . ']';
	}

	/**
	 * Product categories for the SELECT2 control.
	 *
	 * @return array<string, string>
	 */
	private function get_category_options(): array {
		$options = array( '' => __( 'All categories', 'clandevs-smart-catalog-filters' ) );

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
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}
}
