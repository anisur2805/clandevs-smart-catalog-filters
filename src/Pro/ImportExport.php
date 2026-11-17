<?php
/**
 * Import/export plugin settings.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfers plugin configuration between sites as a JSON bundle.
 */
class ImportExport {

	/**
	 * Admin page slug.
	 */
	private const PAGE_SLUG = 'cscf-import-export';

	/**
	 * Largest import file accepted, in bytes.
	 */
	private const MAX_UPLOAD_BYTES = 1048576;

	/**
	 * Transient prefix holding the outcome of an import.
	 */
	private const NOTICE_KEY = 'cscfp_import_notice_';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'cscf_admin_menu_registered', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_cscf_export_settings', array( $this, 'handle_export' ) );
		add_action( 'admin_post_cscf_import_settings', array( $this, 'handle_import' ) );
	}

	/**
	 * Register the import/export submenu.
	 *
	 * @param string $menu_slug Parent menu slug.
	 * @return void
	 */
	public function add_submenu( string $menu_slug ): void {
		add_submenu_page(
			$menu_slug,
			__( 'Import/Export', 'clandevs-smart-catalog-filters' ),
			__( 'Import/Export', 'clandevs-smart-catalog-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the import/export screen.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import/Export Settings', 'clandevs-smart-catalog-filters' ) . '</h1>';

		$this->render_notice();

		echo '<h2>' . esc_html__( 'Export', 'clandevs-smart-catalog-filters' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download filter settings, styles, presets, swatches and filter ordering as a JSON file.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_export_settings' );
		echo '<input type="hidden" name="action" value="cscf_export_settings" />';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Download Settings JSON', 'clandevs-smart-catalog-filters' ) . '</button></p>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import', 'clandevs-smart-catalog-filters' ) . '</h2>';
		echo '<p>' . esc_html__( 'Importing overwrites the settings contained in the uploaded file. Values are validated before they are saved.', 'clandevs-smart-catalog-filters' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cscf_import_settings' );
		echo '<input type="hidden" name="action" value="cscf_import_settings" />';
		echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . esc_attr( (string) self::MAX_UPLOAD_BYTES ) . '" />';
		echo '<p><input type="file" name="cscf_import_file" accept="application/json,.json" required /></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import Settings', 'clandevs-smart-catalog-filters' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Stream the settings bundle as a JSON download.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		check_admin_referer( 'cscf_export_settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to export settings.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		$bundle = array(
			'schema'   => 1,
			'version'  => CSCFP_VERSION,
			'exported' => gmdate( 'c' ),
			'filter'   => class_exists( '\ClandevsSmartCatalogFilters\FilterSettings' ) ? \ClandevsSmartCatalogFilters\FilterSettings::get_options() : array(),
			'style'    => class_exists( '\ClandevsSmartCatalogFilters\StyleSettings' ) ? \ClandevsSmartCatalogFilters\StyleSettings::get_options() : array(),
			'presets'  => get_option( 'cscf_presets', array() ),
			'swatches' => get_option( 'cscf_visual_swatches', array() ),
			'order'    => get_option( 'cscf_filter_order', array() ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cscf-settings-' . gmdate( 'Y-m-d' ) . '.json' );

		echo wp_json_encode( $bundle, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Validate and apply an uploaded settings bundle.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		check_admin_referer( 'cscf_import_settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to import settings.', 'clandevs-smart-catalog-filters' ), '', array( 'response' => 403 ) );
		}

		$data = $this->read_uploaded_bundle();

		if ( is_wp_error( $data ) ) {
			$this->redirect_with_notice( 'error', $data->get_error_message() );
		}

		$applied = array();

		if ( isset( $data['filter'] ) && is_array( $data['filter'] ) ) {
			// Sanitized by the callback FilterSettings registers via register_setting().
			update_option( 'cscf_filter_options', $data['filter'] );
			$applied[] = __( 'filter settings', 'clandevs-smart-catalog-filters' );
		}

		if ( isset( $data['style'] ) && is_array( $data['style'] ) ) {
			// Sanitized by the callback StyleSettings registers via register_setting().
			update_option( 'cscf_style_options', $data['style'] );
			$applied[] = __( 'styles', 'clandevs-smart-catalog-filters' );
		}

		if ( isset( $data['presets'] ) && is_array( $data['presets'] ) ) {
			update_option( 'cscf_presets', $this->sanitize_presets( $data['presets'] ) );
			$applied[] = __( 'presets', 'clandevs-smart-catalog-filters' );
		}

		if ( isset( $data['swatches'] ) && is_array( $data['swatches'] ) ) {
			update_option( 'cscf_visual_swatches', $this->sanitize_swatches( $data['swatches'] ) );
			$applied[] = __( 'swatches', 'clandevs-smart-catalog-filters' );
		}

		if ( isset( $data['order'] ) && is_array( $data['order'] ) ) {
			update_option( 'cscf_filter_order', $this->sanitize_order( $data['order'] ) );
			$applied[] = __( 'filter order', 'clandevs-smart-catalog-filters' );
		}

		if ( empty( $applied ) ) {
			$this->redirect_with_notice( 'error', __( 'The file contained no importable settings.', 'clandevs-smart-catalog-filters' ) );
		}

		$this->redirect_with_notice(
			'success',
			sprintf(
				/* translators: %s: comma separated list of imported sections. */
				__( 'Imported: %s.', 'clandevs-smart-catalog-filters' ),
				implode( ', ', $applied )
			)
		);
	}

	/**
	 * Read, validate and decode the uploaded file.
	 *
	 * @return array|\WP_Error Decoded bundle, or an error.
	 */
	private function read_uploaded_bundle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified by the only caller, handle_import().
		if ( empty( $_FILES['cscf_import_file'] ) || ! isset( $_FILES['cscf_import_file']['tmp_name'] ) ) {
			return new \WP_Error( 'cscfp_no_file', __( 'No file was uploaded.', 'clandevs-smart-catalog-filters' ) );
		}

		$error = isset( $_FILES['cscf_import_file']['error'] ) ? absint( $_FILES['cscf_import_file']['error'] ) : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			return new \WP_Error( 'cscfp_upload_error', __( 'The file could not be uploaded.', 'clandevs-smart-catalog-filters' ) );
		}

		$size = isset( $_FILES['cscf_import_file']['size'] ) ? absint( $_FILES['cscf_import_file']['size'] ) : 0;

		if ( $size <= 0 || $size > self::MAX_UPLOAD_BYTES ) {
			return new \WP_Error( 'cscfp_upload_size', __( 'The file is empty or larger than 1 MB.', 'clandevs-smart-catalog-filters' ) );
		}

		$name  = isset( $_FILES['cscf_import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['cscf_import_file']['name'] ) ) : '';
		$check = wp_check_filetype( $name, array( 'json' => 'application/json' ) );

		if ( 'json' !== $check['ext'] ) {
			return new \WP_Error( 'cscfp_upload_type', __( 'Only .json files can be imported.', 'clandevs-smart-catalog-filters' ) );
		}

		$tmp_name = isset( $_FILES['cscf_import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['cscf_import_file']['tmp_name'] ) ) : '';

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'cscfp_upload_invalid', __( 'The upload could not be verified.', 'clandevs-smart-catalog-filters' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$json = $wp_filesystem ? $wp_filesystem->get_contents( $tmp_name ) : false;

		if ( ! is_string( $json ) || '' === $json ) {
			return new \WP_Error( 'cscfp_read_failed', __( 'The file could not be read.', 'clandevs-smart-catalog-filters' ) );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'cscfp_bad_json', __( 'The file is not valid JSON.', 'clandevs-smart-catalog-filters' ) );
		}

		return $data;
	}

	/**
	 * Sanitize an imported preset collection.
	 *
	 * @param array $presets Raw presets.
	 * @return array<string, array<string, string>>
	 */
	private function sanitize_presets( array $presets ): array {
		$clean = array();

		foreach ( $presets as $id => $preset ) {
			if ( ! is_array( $preset ) ) {
				continue;
			}

			$key  = sanitize_key( (string) $id );
			$name = isset( $preset['name'] ) && is_scalar( $preset['name'] ) ? sanitize_text_field( (string) $preset['name'] ) : '';
			$args = isset( $preset['filters'] ) && is_scalar( $preset['filters'] ) ? (string) $preset['filters'] : '';

			if ( '' === $key || '' === $name || '' === $args ) {
				continue;
			}

			$parsed = array();
			wp_parse_str( ltrim( $args, '?' ), $parsed );

			if ( empty( $parsed ) ) {
				continue;
			}

			$clean[ $key ] = array(
				'name'    => $name,
				'filters' => http_build_query( map_deep( $parsed, 'sanitize_text_field' ) ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize an imported swatch map.
	 *
	 * @param array $swatches Raw term id to attachment id map.
	 * @return array<int, int>
	 */
	private function sanitize_swatches( array $swatches ): array {
		$clean = array();

		foreach ( $swatches as $term_id => $attachment_id ) {
			$term_id       = absint( $term_id );
			$attachment_id = absint( $attachment_id );

			if ( $term_id > 0 && $attachment_id > 0 ) {
				$clean[ $term_id ] = $attachment_id;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize an imported filter ordering.
	 *
	 * @param array $order Raw section keys.
	 * @return array<int, string>
	 */
	private function sanitize_order( array $order ): array {
		$clean = array();

		foreach ( $order as $key ) {
			if ( ! is_scalar( $key ) ) {
				continue;
			}

			$key = sanitize_key( (string) $key );

			if ( '' !== $key && ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}

		return $clean;
	}

	/**
	 * Store a notice and return to the settings screen.
	 *
	 * @param string $type    Either success or error.
	 * @param string $message Notice text.
	 * @return void
	 */
	private function redirect_with_notice( string $type, string $message ): void {
		set_transient(
			self::NOTICE_KEY . get_current_user_id(),
			array(
				'type'    => 'success' === $type ? 'success' : 'error',
				'message' => $message,
			),
			60
		);

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SLUG ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Print and clear a stored notice.
	 *
	 * @return void
	 */
	private function render_notice(): void {
		$key    = self::NOTICE_KEY . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ),
			esc_html( (string) $notice['message'] )
		);
	}
}
