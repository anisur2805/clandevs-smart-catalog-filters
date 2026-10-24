<?php
/**
 * Elementor integration.
 *
 * Registers the filter sidebar widget with Elementor.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks the Elementor widget registration into Elementor.
 */
class ElementorWidget {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the filter sidebar widget with Elementor.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		$widgets_manager->register( new \ClandevsSmartCatalogFilters\Elementor\FilterSidebarWidget() );
	}
}
