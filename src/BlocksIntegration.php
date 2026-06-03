<?php
/**
 * WooCommerce Blocks integration.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlocksIntegration {
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		if ( class_exists( '\WP_Block_Type_Registry' ) && \WP_Block_Type_Registry::get_instance()->is_registered( 'cscf/catalog-filters' ) ) {
			return;
		}
		register_block_type(
			'cscf/catalog-filters',
			array(
				'api_version'     => 3,
				'title'           => __( 'Catalog Filters', 'clandevs-smart-catalog-filters' ),
				'category'        => 'woocommerce',
				'editor_script'   => null,
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'className' => array( 'type' => 'string' ),
				),
			)
		);
	}

	public function render_block( array $attributes ): string {
		if ( ! class_exists( 'ClandevsSmartCatalogFilters\ShopFilters' ) ) {
			return '';
		}
		ob_start();
		echo do_shortcode( '[clandevs_catalog_filters]' );
		return ob_get_clean();
	}
}
