<?php
/**
 * Image/visual swatch for attribute filters.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assigns images to attribute terms.
 *
 * The free plugin renders these on the storefront via
 * ShopFilters::get_term_swatch_image_url(), which reads get_swatches().
 */
class VisualSwatches {

	/**
	 * Option holding the term id to attachment id map.
	 */
	private const OPTION_KEY = 'cscf_visual_swatches';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-visual-swatches';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_cscf_save_swatches', array( $this, 'handle_save' ) );
	}

	/**
	 * Register the swatches submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Visual Swatches', 'clandevs-smart-catalog-filters' ),
			__( 'Visual Swatches', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the media picker assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/css/pro-admin.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/js/pro-admin.js', array( 'jquery' ), CSCFP_VERSION, true );

		wp_localize_script(
			'cscfp-admin',
			'cscfpSwatches',
			array(
				'mediaTitle'  => __( 'Select swatch image', 'clandevs-smart-catalog-filters' ),
				'mediaButton' => __( 'Use image', 'clandevs-smart-catalog-filters' ),
				'setImage'    => __( 'Set Image', 'clandevs-smart-catalog-filters' ),
				'changeImage' => __( 'Change Image', 'clandevs-smart-catalog-filters' ),
				'removeImage' => __( 'Remove', 'clandevs-smart-catalog-filters' ),
			)
		);
	}

	/**
	 * Render the swatch assignment screen.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Visual Swatches', 'clandevs-smart-catalog-filters' ) . '</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag rendered after a nonce-checked redirect.
		if ( isset( $_GET['cscf-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Swatches saved.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
		}

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			echo '<p>' . esc_html__( 'WooCommerce is required to manage attribute swatches.', 'clandevs-smart-catalog-filters' ) . '</p></div>';
			return;
		}

		$swatches   = self::get_swatches();
		$taxonomies = wc_get_attribute_taxonomies();

		echo '<p>' . esc_html__( 'Assign an image to an attribute term to show it as a swatch in the filter sidebar.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_save_swatches' );
		echo '<input type="hidden" name="action" value="cscf_save_swatches" />';

		$rendered = false;

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_name = 'pa_' . $taxonomy->attribute_name;

			if ( ! taxonomy_exists( $taxonomy_name ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy_name,
					'hide_empty' => false,
					'number'     => 200,
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$rendered = true;

			echo '<h3>' . esc_html( $taxonomy->attribute_label ) . '</h3>';
			echo '<div class="cscf-swatch-grid">';

			foreach ( $terms as $term ) {
				$this->render_swatch_item( $term, $swatches );
			}

			echo '</div>';
		}

		if ( ! $rendered ) {
			echo '<p>' . esc_html__( 'No product attribute terms found yet.', 'clandevs-smart-catalog-filters' ) . '</p>';
		}

		submit_button( __( 'Save Swatches', 'clandevs-smart-catalog-filters' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render one term's swatch control.
	 *
	 * @param \WP_Term       $term     Attribute term.
	 * @param array<int,int> $swatches Saved swatch map.
	 * @return void
	 */
	private function render_swatch_item( \WP_Term $term, array $swatches ): void {
		$term_id   = absint( $term->term_id );
		$image_id  = isset( $swatches[ $term_id ] ) ? absint( $swatches[ $term_id ] ) : 0;
		$image_url = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$image_url = is_string( $image_url ) ? $image_url : '';
		$label     = $image_id > 0
			? __( 'Change Image', 'clandevs-smart-catalog-filters' )
			: __( 'Set Image', 'clandevs-smart-catalog-filters' );

		echo '<div class="cscf-swatch-item" data-term-id="' . esc_attr( (string) $term_id ) . '">';
		echo '<strong>' . esc_html( $term->name ) . '</strong>';
		echo '<img class="cscf-swatch-preview' . ( '' === $image_url ? ' is-empty' : '' ) . '" src="' . esc_url( $image_url ) . '" alt="" />';
		echo '<input type="hidden" class="cscf-swatch-input" name="swatches[' . esc_attr( (string) $term_id ) . ']" value="' . esc_attr( (string) $image_id ) . '" />';
		echo '<button type="button" class="button cscf-upload-swatch">' . esc_html( $label ) . '</button>';
		echo '<button type="button" class="button-link cscf-remove-swatch"' . ( 0 === $image_id ? ' hidden' : '' ) . '>' . esc_html__( 'Remove', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Persist the swatch assignments.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( 'cscf_save_swatches' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change swatches.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		$raw   = isset( $_POST['swatches'] ) ? wp_unslash( $_POST['swatches'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to integers below.
		$clean = array();

		foreach ( (array) $raw as $term_id => $attachment_id ) {
			$term_id       = absint( $term_id );
			$attachment_id = absint( $attachment_id );

			if ( $term_id > 0 && $attachment_id > 0 ) {
				$clean[ $term_id ] = $attachment_id;
			}
		}

		update_option( self::OPTION_KEY, $clean );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'cscf-updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Read the swatch map.
	 *
	 * The free plugin calls this guarded only by class_exists(), and the
	 * autoloader registers this class whether or not Pro is licensed, so the
	 * licence is re-checked here.
	 *
	 * @return array<int, int>
	 */
	public static function get_swatches(): array {
		if ( ! Gate::can_use_pro() ) {
			return array();
		}

		$swatches = get_option( self::OPTION_KEY, array() );

		return is_array( $swatches ) ? $swatches : array();
	}
}
