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

/**
 * Lets shoppers pick products from the catalog and compare them side by side.
 */
class Comparison {

	/**
	 * Maximum products that can be compared at once.
	 */
	private const MAX_COMPARE = 4;

	/**
	 * AJAX action name.
	 */
	private const ACTION = 'cscf_compare';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_after_filter_render', array( $this, 'render_compare_bar' ) );
		add_action( 'wp_footer', array( $this, 'render_compare_modal' ) );
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_compare_toggle' ), 20 );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax_compare' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'ajax_compare' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets on catalog screens.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! \ClandevsSmartCatalogFilters\ShopFilters::is_catalog_screen() ) {
			return;
		}

		wp_enqueue_style( 'cscfp-shop', CSCFP_PLUGIN_URL . 'assets/css/pro-shop.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-comparison', CSCFP_PLUGIN_URL . 'assets/js/comparison.js', array( 'jquery' ), CSCFP_VERSION, true );

		wp_localize_script(
			'cscfp-comparison',
			'cscfpCompare',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::ACTION,
				'nonce'   => wp_create_nonce( self::ACTION ),
				'max'     => self::MAX_COMPARE,
				'i18n'    => array(
					'compare'  => __( 'Compare', 'clandevs-smart-catalog-filters' ),
					'selected' => __( 'Selected', 'clandevs-smart-catalog-filters' ),
					'max'      => sprintf(
						/* translators: %d: maximum number of comparable products. */
						__( 'You can compare up to %d products.', 'clandevs-smart-catalog-filters' ),
						self::MAX_COMPARE
					),
					'minimum'  => __( 'Select at least two products to compare.', 'clandevs-smart-catalog-filters' ),
					'error'    => __( 'The comparison could not be loaded. Please try again.', 'clandevs-smart-catalog-filters' ),
					/* translators: %d: number of selected products. */
					'count'    => __( '%d selected', 'clandevs-smart-catalog-filters' ),
				),
			)
		);
	}

	/**
	 * Render the floating comparison bar and modal shell.
	 *
	 * @param array $filter_options Current filter visibility options.
	 * @return void
	 */
	public function render_compare_bar( array $filter_options = array() ): void {
		unset( $filter_options );

		echo '<div class="cscf-compare-bar" hidden>';
		echo '<span class="cscf-compare-count" aria-live="polite"></span>';
		echo '<button type="button" class="cscf-compare-go button">' . esc_html__( 'Compare', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '<button type="button" class="cscf-compare-clear button">' . esc_html__( 'Clear', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Render the comparison modal at the end of the document.
	 *
	 * The modal is a full-viewport overlay, so it must not live inside the
	 * filter sidebar: `.wf-sidebar` is `position: sticky`, which establishes a
	 * stacking context that would trap the modal beneath the product grid no
	 * matter how high its z-index is. Rendering it from wp_footer makes it a
	 * direct child of <body>, where the overlay covers the whole page.
	 *
	 * @return void
	 */
	public function render_compare_modal(): void {
		if ( ! \ClandevsSmartCatalogFilters\ShopFilters::is_catalog_screen() ) {
			return;
		}

		echo '<div class="cscf-compare-modal" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Product comparison', 'clandevs-smart-catalog-filters' ) . '" hidden>';
		echo '<div class="cscf-compare-modal__inner">';
		echo '<button type="button" class="cscf-compare-close" aria-label="' . esc_attr__( 'Close comparison', 'clandevs-smart-catalog-filters' ) . '">&times;</button>';
		echo '<div class="cscf-compare-modal__body"></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the per-product compare toggle inside the catalog loop.
	 *
	 * @return void
	 */
	public function render_compare_toggle(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		printf(
			'<button type="button" class="button cscf-compare-toggle" data-product-id="%1$s" aria-pressed="false">%2$s</button>',
			esc_attr( (string) $product->get_id() ),
			esc_html__( 'Compare', 'clandevs-smart-catalog-filters' )
		);
	}

	/**
	 * Build and return the comparison table markup.
	 *
	 * @return void
	 */
	public function ajax_compare(): void {
		check_ajax_referer( self::ACTION, 'nonce' );

		$raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to integers below.
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
		$ids = array_slice( $ids, 0, self::MAX_COMPARE );

		if ( count( $ids ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Select at least two products to compare.', 'clandevs-smart-catalog-filters' ) ) );
		}

		$products = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( $product instanceof \WC_Product && 'publish' === $product->get_status() ) {
				$products[] = $product;
			}
		}

		if ( count( $products ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Those products are no longer available to compare.', 'clandevs-smart-catalog-filters' ) ) );
		}

		wp_send_json_success( array( 'html' => $this->build_table( $products ) ) );
	}

	/**
	 * Build the comparison table.
	 *
	 * @param array<int, \WC_Product> $products Products to compare.
	 * @return string
	 */
	private function build_table( array $products ): string {
		$attribute_labels = $this->collect_attribute_labels( $products );

		ob_start();

		echo '<table class="cscf-compare-table">';

		echo '<thead><tr><th scope="row">' . esc_html__( 'Product', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			echo '<td>';
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			if ( is_string( $image ) && '' !== $image ) {
				echo '<img class="cscf-compare-image" src="' . esc_url( $image ) . '" alt="" loading="lazy" />';
			}
			echo '<a class="cscf-compare-name" href="' . esc_url( (string) get_permalink( $product->get_id() ) ) . '">' . esc_html( $product->get_name() ) . '</a>';
			echo '<button type="button" class="cscf-compare-remove" data-product-id="' . esc_attr( (string) $product->get_id() ) . '">' . esc_html__( 'Remove', 'clandevs-smart-catalog-filters' ) . '</button>';
			echo '</td>';
		}
		echo '</tr></thead><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Price', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			echo '<td>' . wp_kses_post( $product->get_price_html() ) . '</td>';
		}
		echo '</tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Availability', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			echo '<td>' . esc_html( $this->get_stock_label( $product ) ) . '</td>';
		}
		echo '</tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Rating', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			$rating = (float) $product->get_average_rating();
			echo '<td>' . ( $rating > 0 ? esc_html( number_format_i18n( $rating, 2 ) ) : esc_html__( 'Not rated', 'clandevs-smart-catalog-filters' ) ) . '</td>';
		}
		echo '</tr>';

		echo '<tr><th scope="row">' . esc_html__( 'SKU', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			$sku = $product->get_sku();
			echo '<td>' . ( '' !== $sku ? esc_html( $sku ) : '&mdash;' ) . '</td>';
		}
		echo '</tr>';

		foreach ( $attribute_labels as $attribute_key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $products as $product ) {
				$value = $product->get_attribute( $attribute_key );
				echo '<td>' . ( '' !== $value ? esc_html( $value ) : '&mdash;' ) . '</td>';
			}
			echo '</tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'Actions', 'clandevs-smart-catalog-filters' ) . '</th>';
		foreach ( $products as $product ) {
			echo '<td><a class="button" href="' . esc_url( $product->add_to_cart_url() ) . '">' . esc_html( $product->add_to_cart_text() ) . '</a></td>';
		}
		echo '</tr>';

		echo '</tbody></table>';

		return (string) ob_get_clean();
	}

	/**
	 * Collect the visible attribute columns shared across the compared products.
	 *
	 * @param array<int, \WC_Product> $products Products to compare.
	 * @return array<string, string> Attribute key to display label.
	 */
	private function collect_attribute_labels( array $products ): array {
		$labels = array();

		foreach ( $products as $product ) {
			foreach ( $product->get_attributes() as $attribute ) {
				if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->get_visible() ) {
					continue;
				}

				$name = $attribute->get_name();

				if ( '' === $name || isset( $labels[ $name ] ) ) {
					continue;
				}

				$labels[ $name ] = wc_attribute_label( $name, $product );
			}
		}

		return $labels;
	}

	/**
	 * Human readable stock status.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function get_stock_label( \WC_Product $product ): string {
		switch ( $product->get_stock_status() ) {
			case 'instock':
				return __( 'In stock', 'clandevs-smart-catalog-filters' );
			case 'onbackorder':
				return __( 'On backorder', 'clandevs-smart-catalog-filters' );
			default:
				return __( 'Out of stock', 'clandevs-smart-catalog-filters' );
		}
	}
}
