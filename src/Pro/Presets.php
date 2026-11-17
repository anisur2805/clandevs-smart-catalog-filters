<?php
/**
 * Filter presets — save and load filter combinations.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store-wide filter presets plus per-shopper saved combinations.
 */
class Presets {

	/**
	 * Option holding store presets.
	 */
	private const OPTION_KEY = 'cscf_presets';

	/**
	 * User meta key holding shopper presets.
	 */
	private const USER_META_KEY = 'cscf_presets';

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-presets';

	/**
	 * Presets kept per shopper.
	 */
	private const MAX_USER_PRESETS = 10;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cscf_save_preset', array( $this, 'ajax_save_preset' ) );
		add_action( 'wp_ajax_cscf_delete_preset', array( $this, 'ajax_delete_preset' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'cscf_before_filter_render', array( $this, 'render_frontend_presets' ) );
		add_action( 'wp_ajax_cscf_save_user_preset', array( $this, 'ajax_save_user_preset' ) );
		add_action( 'wp_ajax_cscf_delete_user_preset', array( $this, 'ajax_delete_user_preset' ) );
	}

	/*
	 * Admin.
	 */

	/**
	 * Register the presets submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Filter Presets', 'clandevs-smart-catalog-filters' ),
			__( 'Presets', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the admin presets script.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'cscfp-admin', CSCFP_PLUGIN_URL . 'assets/css/pro-admin.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-presets', CSCFP_PLUGIN_URL . 'assets/js/presets.js', array( 'jquery' ), CSCFP_VERSION, true );

		wp_localize_script(
			'cscfp-presets',
			'cscfpPresets',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cscf_preset' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Delete this preset?', 'clandevs-smart-catalog-filters' ),
					'error'         => __( 'The preset could not be saved.', 'clandevs-smart-catalog-filters' ),
					'nameRequired'  => __( 'Enter a preset name.', 'clandevs-smart-catalog-filters' ),
					'delete'        => __( 'Delete', 'clandevs-smart-catalog-filters' ),
				),
			)
		);
	}

	/**
	 * Render the admin presets page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$presets = $this->get_store_presets();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Filter Presets', 'clandevs-smart-catalog-filters' ) . '</h1>';
		echo '<p>' . esc_html__( 'Presets appear above the filter sidebar so shoppers can apply a saved filter combination in one click.', 'clandevs-smart-catalog-filters' ) . '</p>';

		echo '<form class="cscf-preset-create-form">';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="cscf-preset-name">' . esc_html__( 'Preset Name', 'clandevs-smart-catalog-filters' ) . '</label></th>';
		echo '<td><input type="text" id="cscf-preset-name" name="name" class="regular-text" required /></td></tr>';
		echo '<tr><th scope="row"><label for="cscf-preset-filters">' . esc_html__( 'Filter Query', 'clandevs-smart-catalog-filters' ) . '</label></th>';
		echo '<td><textarea id="cscf-preset-filters" name="filters" rows="3" class="large-text code" placeholder="cscf_cat=shoes&amp;cscf_brand[]=nike&amp;min_price=50" required></textarea>';
		echo '<p class="description">' . esc_html__( 'Paste the query string from a filtered shop page, without the leading question mark. Unrecognised parameters are discarded.', 'clandevs-smart-catalog-filters' ) . '</p></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Preset', 'clandevs-smart-catalog-filters' ) . '</button></p>';
		echo '</form>';

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Saved Presets', 'clandevs-smart-catalog-filters' ) . '</h2>';
		echo '<div id="cscf-presets-list">';

		if ( empty( $presets ) ) {
			echo '<p class="cscf-presets-empty">' . esc_html__( 'No presets yet.', 'clandevs-smart-catalog-filters' ) . '</p>';
		}

		foreach ( $presets as $id => $preset ) {
			$this->render_admin_preset_row( (string) $id, $preset );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one preset row in the admin list.
	 *
	 * @param string $id     Preset id.
	 * @param array  $preset Preset data.
	 * @return void
	 */
	private function render_admin_preset_row( string $id, array $preset ): void {
		echo '<div class="cscf-preset-item" data-id="' . esc_attr( $id ) . '">';
		echo '<strong>' . esc_html( (string) ( $preset['name'] ?? '' ) ) . '</strong>';
		echo '<code>' . esc_html( (string) ( $preset['filters'] ?? '' ) ) . '</code>';
		echo '<button type="button" class="button cscf-delete-preset">' . esc_html__( 'Delete', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Save a store preset.
	 *
	 * @return void
	 */
	public function ajax_save_preset(): void {
		check_ajax_referer( 'cscf_preset', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage presets.', 'clandevs-smart-catalog-filters' ) ), 403 );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_filter_query() whitelists keys and sanitizes every value.
		$filters = $this->sanitize_filter_query( wp_unslash( $_POST['filters'] ?? '' ) );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Enter a preset name.', 'clandevs-smart-catalog-filters' ) ) );
		}

		if ( '' === $filters ) {
			wp_send_json_error( array( 'message' => __( 'The filter query contains no recognised filter parameters.', 'clandevs-smart-catalog-filters' ) ) );
		}

		$presets = $this->get_store_presets();
		$id      = wp_generate_password( 8, false, false );

		$presets[ $id ] = array(
			'name'    => $name,
			'filters' => $filters,
		);

		update_option( self::OPTION_KEY, $presets );

		wp_send_json_success(
			array(
				'id'      => $id,
				'name'    => $name,
				'filters' => $filters,
			)
		);
	}

	/**
	 * Delete a store preset.
	 *
	 * @return void
	 */
	public function ajax_delete_preset(): void {
		check_ajax_referer( 'cscf_preset', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to manage presets.', 'clandevs-smart-catalog-filters' ) ), 403 );
		}

		$id      = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$presets = $this->get_store_presets();

		if ( ! isset( $presets[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Preset not found.', 'clandevs-smart-catalog-filters' ) ) );
		}

		unset( $presets[ $id ] );
		update_option( self::OPTION_KEY, $presets );

		wp_send_json_success();
	}

	/*
	 * Frontend.
	 */

	/**
	 * Enqueue frontend preset assets on catalog screens.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! \ClandevsSmartCatalogFilters\ShopFilters::is_catalog_screen() ) {
			return;
		}

		wp_enqueue_style( 'cscfp-shop', CSCFP_PLUGIN_URL . 'assets/css/pro-shop.css', array(), CSCFP_VERSION );
		wp_enqueue_script( 'cscfp-shop', CSCFP_PLUGIN_URL . 'assets/js/pro-shop.js', array( 'jquery' ), CSCFP_VERSION, true );

		wp_localize_script(
			'cscfp-shop',
			'cscfpShop',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cscf_user_preset' ),
				'loggedIn' => is_user_logged_in(),
				'i18n'     => array(
					'promptName'    => __( 'Name this filter preset:', 'clandevs-smart-catalog-filters' ),
					'noFilters'     => __( 'Apply some filters before saving a preset.', 'clandevs-smart-catalog-filters' ),
					'confirmDelete' => __( 'Remove this saved preset?', 'clandevs-smart-catalog-filters' ),
					'error'         => __( 'The preset could not be saved.', 'clandevs-smart-catalog-filters' ),
				),
			)
		);
	}

	/**
	 * Render store and shopper presets above the filter form.
	 *
	 * @param array $filter_options Current filter visibility options.
	 * @return void
	 */
	public function render_frontend_presets( array $filter_options = array() ): void {
		unset( $filter_options );

		$store_presets = $this->get_store_presets();
		$user_presets  = $this->get_user_presets();
		$logged_in     = is_user_logged_in();

		echo '<div class="cscf-presets" data-logged-in="' . esc_attr( $logged_in ? '1' : '0' ) . '">';
		echo '<h4>' . esc_html__( 'Filter Presets', 'clandevs-smart-catalog-filters' ) . '</h4>';

		// Guests keep their presets in localStorage, so the dropdown is always
		// rendered and topped up client side.
		echo '<label class="cscf-preset-loader"><span class="screen-reader-text">';
		echo esc_html__( 'Load a filter preset', 'clandevs-smart-catalog-filters' ) . '</span>';
		echo '<select class="cscf-preset-select">';
		echo '<option value="">' . esc_html__( 'Load a preset…', 'clandevs-smart-catalog-filters' ) . '</option>';

		if ( ! empty( $store_presets ) ) {
			echo '<optgroup label="' . esc_attr__( 'Store presets', 'clandevs-smart-catalog-filters' ) . '">';
			foreach ( $store_presets as $preset ) {
				$query = (string) ( $preset['filters'] ?? '' );

				if ( '' === $query ) {
					continue;
				}

				echo '<option value="' . esc_url( $this->build_preset_url( $query ) ) . '">' . esc_html( (string) ( $preset['name'] ?? '' ) ) . '</option>';
			}
			echo '</optgroup>';
		}

		echo '<optgroup class="cscf-preset-mine" label="' . esc_attr__( 'My presets', 'clandevs-smart-catalog-filters' ) . '">';

		if ( $logged_in ) {
			foreach ( $user_presets as $id => $preset ) {
				$query = (string) ( $preset['filters'] ?? '' );

				if ( '' === $query ) {
					continue;
				}

				echo '<option value="' . esc_url( $this->build_preset_url( $query ) ) . '" data-id="' . esc_attr( (string) $id ) . '">' . esc_html( (string) ( $preset['name'] ?? '' ) ) . '</option>';
			}
		}

		echo '</optgroup>';
		echo '</select></label>';

		echo '<div class="cscf-preset-actions">';
		// Hidden until the shopper has filters applied; revealed by pro-shop.js.
		echo '<button type="button" class="cscf-preset-save button" hidden>' . esc_html__( 'Save current filters', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '<button type="button" class="cscf-preset-remove button" hidden>' . esc_html__( 'Delete selected preset', 'clandevs-smart-catalog-filters' ) . '</button>';
		echo '</div>';

		echo '<p class="cscf-preset-limit" hidden>' . esc_html__( 'You can keep up to 10 presets.', 'clandevs-smart-catalog-filters' ) . '</p>';

		echo '</div>';
	}

	/**
	 * Save the current filter combination for the logged-in shopper.
	 *
	 * @return void
	 */
	public function ajax_save_user_preset(): void {
		check_ajax_referer( 'cscf_user_preset', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Sign in to save presets.', 'clandevs-smart-catalog-filters' ) ), 403 );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_filter_query() whitelists keys and sanitizes every value.
		$filters = $this->sanitize_filter_query( wp_unslash( $_POST['filters'] ?? '' ) );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Enter a preset name.', 'clandevs-smart-catalog-filters' ) ) );
		}

		if ( '' === $filters ) {
			wp_send_json_error( array( 'message' => __( 'Apply some filters before saving a preset.', 'clandevs-smart-catalog-filters' ) ) );
		}

		$presets = $this->get_user_presets( $user_id );

		if ( count( $presets ) >= self::MAX_USER_PRESETS ) {
			array_shift( $presets );
		}

		$id = wp_generate_password( 8, false, false );

		$presets[ $id ] = array(
			'name'    => $name,
			'filters' => $filters,
		);

		update_user_meta( $user_id, self::USER_META_KEY, $presets );

		wp_send_json_success(
			array(
				'id'   => $id,
				'name' => $name,
				'url'  => $this->build_preset_url( $filters ),
			)
		);
	}

	/**
	 * Delete one of the shopper's presets.
	 *
	 * @return void
	 */
	public function ajax_delete_user_preset(): void {
		check_ajax_referer( 'cscf_user_preset', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Sign in to manage presets.', 'clandevs-smart-catalog-filters' ) ), 403 );
		}

		$id      = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$presets = $this->get_user_presets( $user_id );

		if ( ! isset( $presets[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Preset not found.', 'clandevs-smart-catalog-filters' ) ) );
		}

		unset( $presets[ $id ] );
		update_user_meta( $user_id, self::USER_META_KEY, $presets );

		wp_send_json_success();
	}

	/*
	 * Data helpers.
	 */

	/**
	 * Read the store presets.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_store_presets(): array {
		$presets = get_option( self::OPTION_KEY, array() );

		return is_array( $presets ) ? $presets : array();
	}

	/**
	 * Read a shopper's presets.
	 *
	 * @param int $user_id Optional user id, defaults to the current user.
	 * @return array<string, array<string, string>>
	 */
	private function get_user_presets( int $user_id = 0 ): array {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return array();
		}

		$presets = get_user_meta( $user_id, self::USER_META_KEY, true );

		return is_array( $presets ) ? $presets : array();
	}

	/**
	 * The catalog URL presets link back to.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );

			if ( is_string( $shop_url ) && '' !== $shop_url ) {
				return $shop_url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Build a preset link.
	 *
	 * Uses add_query_arg() rather than concatenating "?", because on plain
	 * permalinks the shop URL already carries a query string.
	 *
	 * @param string $filters Filter query string.
	 * @return string
	 */
	private function build_preset_url( string $filters ): string {
		$args = array();
		wp_parse_str( $filters, $args );

		if ( empty( $args ) ) {
			return $this->get_base_url();
		}

		return add_query_arg( $args, $this->get_base_url() );
	}

	/**
	 * Reduce an arbitrary query string to recognised filter parameters.
	 *
	 * @param mixed $raw Raw query string.
	 * @return string Rebuilt query string, empty when nothing is recognised.
	 */
	private function sanitize_filter_query( $raw ): string {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}

		$raw = ltrim( trim( (string) $raw ), '?' );

		if ( '' === $raw ) {
			return '';
		}

		$parsed = array();
		wp_parse_str( $raw, $parsed );

		$allowed = array(
			'cscf_cat',
			'cscf_brand',
			'cscf_color',
			'cscf_logic',
			'cscf_in_stock',
			'cscf_on_sale',
			'cscf_per_page',
			'cscf_date_from',
			'cscf_date_to',
			'min_price',
			'max_price',
			'rating_filter',
		);

		$clean = array();

		foreach ( $parsed as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( ! in_array( $key, $allowed, true ) && 0 !== strpos( $key, 'cscf_attr_' ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$values = array();

				foreach ( $value as $item ) {
					if ( ! is_scalar( $item ) ) {
						continue;
					}

					$item = sanitize_text_field( (string) $item );

					if ( '' !== $item ) {
						$values[] = $item;
					}
				}

				if ( ! empty( $values ) ) {
					$clean[ $key ] = array_values( array_unique( $values ) );
				}

				continue;
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $value );

			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		return empty( $clean ) ? '' : http_build_query( $clean );
	}
}
