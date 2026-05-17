<?php
/**
 * Product comparison tool.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comparison {
	private const MAX_COMPARE = 4;

	public function register_hooks(): void {
		add_action( 'cscf_after_filter_render', array( $this, 'render_compare_button' ) );
		add_action( 'wp_ajax_cscf_compare', array( $this, 'ajax_compare' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
			return;
		}
		wp_enqueue_style( 'cscfp-shop', CSCFP_PLUGIN_URL . 'assets/css/pro-shop.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-comparison', CSCFP_PLUGIN_URL . 'assets/js/comparison.js', array( 'jquery' ), CSCFP_VERSION, true );
		wp_localize_script( 'cscfp-comparison', 'cscfpCompare', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cscf_compare' ),
			'max'     => self::MAX_COMPARE,
			'i18n'    => array(
				'added'   => __( 'Added to comparison', 'clandevs-smart-catalog-filters-pro' ),
				'removed' => __( 'Removed from comparison', 'clandevs-smart-catalog-filters-pro' ),
				'max'     => sprintf( __( 'Maximum %d products', 'clandevs-smart-catalog-filters-pro' ), self::MAX_COMPARE ),
			),
		) );
	}

	public function render_compare_button( array $filter_options ): void {
		echo '<div class="cscf-compare-bar" style="display:none;">';
		echo '<span class="cscf-compare-count"></span>';
		echo '<button class="cscf-compare-go button">' . esc_html__( 'Compare', 'clandevs-smart-catalog-filters-pro' ) . '</button>';
		echo '</div>';
	}

	public function ajax_compare(): void {
		check_ajax_referer( 'cscf_compare', '_nonce' );
		$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) {
			wp_send_json_error( 'No products' );
		}
		$products = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$products[] = array(
				'id'    => $id,
				'name'  => $product->get_name(),
				'price' => $product->get_price_html(),
				'image' => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
				'url'   => get_permalink( $id ),
			);
		}
		wp_send_json_success( $products );
	}
}
