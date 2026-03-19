<?php
/**
 * Filter settings controller.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin settings for filter visibility configuration.
 */
final class FilterSettings {
	/** @var string */
	private const OPTION_KEY = 'wf_filter_options';

	/** @var string */
	private const PAGE_SLUG = 'wf-filter-settings';

	/**
	 * Register class hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets for settings page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'woo-filter-studio_page_wf-filter-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wf-admin-styles',
			WF_PLUGIN_URL . 'assets/css/wf-admin.css',
			array(),
			WF_VERSION
		);
	}

	/**
	 * Register settings and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wf_filter_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'wf_filter_section_main',
			__( 'Filter Visibility', 'woo-filter-studio' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		$this->register_checkbox_field( 'show_categories', __( 'Show Categories', 'woo-filter-studio' ) );
		$this->register_checkbox_field( 'show_brands', __( 'Show Brands', 'woo-filter-studio' ) );
		$this->register_checkbox_field( 'show_price', __( 'Show Price', 'woo-filter-studio' ) );
		$this->register_checkbox_field( 'show_rating', __( 'Show Customer Rating', 'woo-filter-studio' ) );
		$this->register_checkbox_field( 'show_availability', __( 'Show Availability', 'woo-filter-studio' ) );
		$this->register_checkbox_field( 'show_colors', __( 'Show Color', 'woo-filter-studio' ) );
	}

	/**
	 * Register submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::get_menu_slug(),
			__( 'Woo Filter Studio Settings', 'woo-filter-studio' ),
			__( 'Woo Filter Studio Settings', 'woo-filter-studio' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$options = self::get_options();
		?>
		<div class="wf-admin-wrap">
			<div class="wf-admin-header">
				<div class="wf-admin-header-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
				</div>
				<div>
					<h1><?php esc_html_e( 'Woo Filter Studio Settings', 'woo-filter-studio' ); ?></h1>
					<p><?php esc_html_e( 'Configure which filters appear in your shop sidebar', 'woo-filter-studio' ); ?></p>
				</div>
			</div>

			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Filter settings saved.', 'woo-filter-studio' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'wf_filter_settings' ); ?>

				<div class="wf-admin-card">
					<div class="wf-admin-card-header">
						<h2><?php esc_html_e( 'Filter Visibility', 'woo-filter-studio' ); ?></h2>
						<p><?php esc_html_e( 'Enable or disable individual filter blocks in the shop sidebar.', 'woo-filter-studio' ); ?></p>
					</div>
					<div class="wf-admin-card-body">
						<div class="wf-admin-toggle-group wf-admin-toggle-grid">
							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_categories]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_categories]" value="yes" <?php checked( isset( $options['show_categories'] ) && 'yes' === $options['show_categories'] ); ?> />
								<span><?php esc_html_e( 'Show Categories', 'woo-filter-studio' ); ?></span>
							</label>

							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_brands]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_brands]" value="yes" <?php checked( isset( $options['show_brands'] ) && 'yes' === $options['show_brands'] ); ?> />
								<span><?php esc_html_e( 'Show Brands', 'woo-filter-studio' ); ?></span>
							</label>

							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_price]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_price]" value="yes" <?php checked( isset( $options['show_price'] ) && 'yes' === $options['show_price'] ); ?> />
								<span><?php esc_html_e( 'Show Price Range', 'woo-filter-studio' ); ?></span>
							</label>

							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_rating]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_rating]" value="yes" <?php checked( isset( $options['show_rating'] ) && 'yes' === $options['show_rating'] ); ?> />
								<span><?php esc_html_e( 'Show Customer Rating', 'woo-filter-studio' ); ?></span>
							</label>

							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_availability]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_availability]" value="yes" <?php checked( isset( $options['show_availability'] ) && 'yes' === $options['show_availability'] ); ?> />
								<span><?php esc_html_e( 'Show Availability', 'woo-filter-studio' ); ?></span>
							</label>

							<label class="wf-admin-toggle">
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_colors]" value="no" />
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_colors]" value="yes" <?php checked( isset( $options['show_colors'] ) && 'yes' === $options['show_colors'] ); ?> />
								<span><?php esc_html_e( 'Show Color Filter', 'woo-filter-studio' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<div class="wf-admin-card">
					<div class="wf-admin-card-header">
						<h2><?php esc_html_e( 'Data Management', 'woo-filter-studio' ); ?></h2>
						<p><?php esc_html_e( 'Control what happens to Woo Filter Studio data when the plugin is removed.', 'woo-filter-studio' ); ?></p>
					</div>
					<div class="wf-admin-card-body">
						<label class="wf-admin-toggle">
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_on_uninstall]" value="no" />
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_on_uninstall]" value="yes" <?php checked( isset( $options['delete_data_on_uninstall'] ) && 'yes' === $options['delete_data_on_uninstall'] ); ?> />
							<span><?php esc_html_e( 'Delete plugin data on uninstall', 'woo-filter-studio' ); ?></span>
						</label>
						<p class="wf-admin-help"><?php esc_html_e( 'If enabled, plugin options and analytics data will be removed when Woo Filter Studio is uninstalled.', 'woo-filter-studio' ); ?></p>
					</div>
				</div>

				<div class="wf-admin-submit-wrap">
					<button type="submit" class="wf-admin-submit">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
						<?php esc_html_e( 'Save Settings', 'woo-filter-studio' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render section intro.
	 *
	 * @return void
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Enable or disable individual filter blocks in the shop sidebar.', 'woo-filter-studio' ) . '</p>';
	}

	/**
	 * Register checkbox field.
	 *
	 * @param string $key   Option key.
	 * @param string $label Option label.
	 * @return void
	 */
	private function register_checkbox_field( string $key, string $label ): void {
		add_settings_field(
			$key,
			$label,
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'wf_filter_section_main',
			array(
				'key' => $key,
			)
		);
	}

	/**
	 * Render checkbox input.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$key     = isset( $args['key'] ) ? sanitize_key( (string) $args['key'] ) : '';
		$options = self::get_options();
		$checked = isset( $options[ $key ] ) && 'yes' === $options[ $key ];

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="yes" ' . checked( $checked, true, false ) . ' />';
		echo ' ' . esc_html__( 'Enabled', 'woo-filter-studio' );
		echo '</label>';
	}

	/**
	 * Sanitize incoming option payload.
	 *
	 * @param mixed $raw Raw value.
	 * @return array
	 */
	public function sanitize_settings( $raw ): array {
		$defaults  = self::get_defaults();
		$sanitized = array();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		foreach ( array_keys( $defaults ) as $key ) {
			$sanitized[ $key ] = ( isset( $raw[ $key ] ) && 'yes' === sanitize_text_field( wp_unslash( (string) $raw[ $key ] ) ) ) ? 'yes' : 'no';
		}

		return $sanitized;
	}

	/**
	 * Get merged options.
	 *
	 * @return array
	 */
	public static function get_options(): array {
		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return array_merge( self::get_defaults(), $options );
	}

	/**
	 * Default options.
	 *
	 * @return array
	 */
	private static function get_defaults(): array {
		return array(
			'show_categories'          => 'yes',
			'show_brands'              => 'yes',
			'show_price'               => 'yes',
			'show_rating'              => 'yes',
			'show_availability'        => 'yes',
			'show_colors'              => 'yes',
			'delete_data_on_uninstall' => 'no',
		);
	}
}
