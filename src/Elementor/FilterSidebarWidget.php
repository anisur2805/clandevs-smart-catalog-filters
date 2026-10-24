<?php
/**
 * Elementor filter sidebar widget.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the catalog filter sidebar shortcode as an Elementor widget.
 */
class FilterSidebarWidget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cscf_filter_sidebar';
	}

	/**
	 * Widget title in the Elementor panel.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Catalog Filters', 'clandevs-smart-catalog-filters' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-filter';
	}

	/**
	 * Widget categories.
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'woocommerce' );
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Settings', 'clandevs-smart-catalog-filters' ),
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
		$this->end_controls_section();
	}

	/**
	 * Render the widget output.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		if ( 'yes' === $settings['show_title'] ) {
			echo '<h3>' . esc_html__( 'Filter Products', 'clandevs-smart-catalog-filters' ) . '</h3>';
		}
		echo do_shortcode( '[clandevs_catalog_filters]' );
	}
}
