<?php
/**
 * Elementor widget for the filter sidebar.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces the free plugin's single-control widget with a fully configurable
 * one. The free widget is unregistered first so only one appears in the panel.
 */
class ElementorWidget {

	/**
	 * Widget name shared with the free plugin.
	 */
	public const WIDGET_NAME = 'cscf_filter_sidebar';

	/**
	 * Elementor category slug.
	 */
	public const CATEGORY = 'clandevs-smart-catalog-filters';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		// Later than the free plugin so the Pro widget wins the name.
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ), 20 );
	}

	/**
	 * Register the widget category.
	 *
	 * @param object $elements_manager Elementor elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ): void {
		if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
			return;
		}

		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Smart Catalog Filters', 'clandevs-smart-catalog-filters' ),
				'icon'  => 'eicon-filter',
			)
		);
	}

	/**
	 * Swap the free widget for the Pro one.
	 *
	 * @param object $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) ) {
			return;
		}

		if ( method_exists( $widgets_manager, 'unregister' ) ) {
			$widgets_manager->unregister( self::WIDGET_NAME );
		}

		require_once __DIR__ . '/Elementor/FilterSidebarWidget.php';

		$widgets_manager->register( new Elementor\FilterSidebarWidget() );
	}
}
