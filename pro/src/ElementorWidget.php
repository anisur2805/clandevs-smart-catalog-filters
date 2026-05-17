<?php
/**
 * Elementor widget for filter sidebar.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ElementorWidget {
	public function register_hooks(): void {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$widgets_manager->register( new \ClandevsSmartCatalogFiltersPro\Elementor\FilterSidebarWidget() );
	}
}

namespace ClandevsSmartCatalogFiltersPro\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Widget_Base' ) ) {

	class FilterSidebarWidget extends \Elementor\Widget_Base {
		public function get_name(): string {
			return 'cscf_filter_sidebar';
		}

		public function get_title(): string {
			return __( 'Catalog Filters', 'clandevs-smart-catalog-filters-pro' );
		}

		public function get_icon(): string {
			return 'eicon-filter';
		}

		public function get_categories(): array {
			return array( 'woocommerce' );
		}

		protected function register_controls(): void {
			$this->start_controls_section(
				'content_section',
				array(
					'label' => __( 'Settings', 'clandevs-smart-catalog-filters-pro' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);
			$this->add_control(
				'show_title',
				array(
					'label'   => __( 'Show Title', 'clandevs-smart-catalog-filters-pro' ),
					'type'    => \Elementor\Controls_Manager::SWITCHER,
					'default' => 'yes',
				)
			);
			$this->end_controls_section();
		}

		protected function render(): void {
			$settings = $this->get_settings_for_display();
			if ( 'yes' === $settings['show_title'] ) {
				echo '<h3>' . esc_html__( 'Filter Products', 'clandevs-smart-catalog-filters-pro' ) . '</h3>';
			}
			echo do_shortcode( '[clandevs_catalog_filters]' );
		}
	}
}
