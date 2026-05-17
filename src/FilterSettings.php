<?php
/**
 * Filter settings controller.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

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
		if ( 'clandevs-smart-catalog-filters_page_wf-filter-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wf-admin-styles',
			CSCF_PLUGIN_URL . 'assets/css/wf-admin.css',
			array(),
			CSCF_VERSION
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
			__( 'Filter Visibility', 'clandevs-smart-catalog-filters' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		$this->register_checkbox_field( 'show_categories', __( 'Show Categories', 'clandevs-smart-catalog-filters' ) );
		$this->register_checkbox_field( 'show_brands', __( 'Show Brands', 'clandevs-smart-catalog-filters' ) );
		$this->register_checkbox_field( 'show_price', __( 'Show Price', 'clandevs-smart-catalog-filters' ) );
		$this->register_checkbox_field( 'show_rating', __( 'Show Customer Rating', 'clandevs-smart-catalog-filters' ) );
		$this->register_checkbox_field( 'show_availability', __( 'Show Availability', 'clandevs-smart-catalog-filters' ) );
		$this->register_checkbox_field( 'show_colors', __( 'Show Color', 'clandevs-smart-catalog-filters' ) );
	}

	/**
	 * Register submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::get_menu_slug(),
			__( 'Catalog Filter Settings', 'clandevs-smart-catalog-filters' ),
			__( 'Settings', 'clandevs-smart-catalog-filters' ),
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
					<h1><?php esc_html_e( 'Clandevs Smart Catalog Filters Settings', 'clandevs-smart-catalog-filters' ); ?></h1>
					<p><?php esc_html_e( 'Configure which filters appear in your shop sidebar', 'clandevs-smart-catalog-filters' ); ?></p>
				</div>
			</div>

			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Filter settings saved.', 'clandevs-smart-catalog-filters' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'wf_filter_settings' ); ?>

				<div class="wf-admin-card">
					<div class="wf-admin-card-header">
						<h2><?php esc_html_e( 'Filter Visibility', 'clandevs-smart-catalog-filters' ); ?></h2>
						<p><?php esc_html_e( 'Enable or disable individual filter blocks in the shop sidebar.', 'clandevs-smart-catalog-filters' ); ?></p>
					</div>
					<div class="wf-admin-card-body">
						<div class="wf-admin-toggle-group wf-admin-toggle-grid">
							<?php
							$toggles = array(
								'show_categories'   => array(
									'label'   => __( 'Show Categories', 'clandevs-smart-catalog-filters' ),
									'feature' => 'category_filter',
								),
								'show_brands'       => array(
									'label'   => __( 'Show Brands', 'clandevs-smart-catalog-filters' ),
									'feature' => 'brand_filter',
								),
								'show_price'        => array(
									'label'   => __( 'Show Price Range', 'clandevs-smart-catalog-filters' ),
									'feature' => 'price_filter',
								),
								'show_rating'       => array(
									'label'   => __( 'Show Customer Rating', 'clandevs-smart-catalog-filters' ),
									'feature' => 'rating_filter',
								),
								'show_availability' => array(
									'label'   => __( 'Show Availability', 'clandevs-smart-catalog-filters' ),
									'feature' => 'availability_filter',
								),
								'show_colors'       => array(
									'label'   => __( 'Show Color Filter', 'clandevs-smart-catalog-filters' ),
									'feature' => 'color_filter',
								),
							);

							foreach ( $toggles as $key => $toggle ) :
								$is_checked = isset( $options[ $key ] ) && 'yes' === $options[ $key ];
								?>
								<label class="wf-admin-toggle">
									<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="no" />
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $is_checked ); ?> />
									<span><?php echo esc_html( $toggle['label'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="wf-admin-card">
					<div class="wf-admin-card-header">
						<h2><?php esc_html_e( 'Data Management', 'clandevs-smart-catalog-filters' ); ?></h2>
						<p><?php esc_html_e( 'Control what happens to Clandevs Smart Catalog Filters data when the plugin is removed.', 'clandevs-smart-catalog-filters' ); ?></p>
					</div>
					<div class="wf-admin-card-body">
						<label class="wf-admin-toggle">
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_on_uninstall]" value="no" />
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_on_uninstall]" value="yes" <?php checked( isset( $options['delete_data_on_uninstall'] ) && 'yes' === $options['delete_data_on_uninstall'] ); ?> />
							<span><?php esc_html_e( 'Delete plugin data on uninstall', 'clandevs-smart-catalog-filters' ); ?></span>
						</label>
						<p class="wf-admin-help"><?php esc_html_e( 'If enabled, plugin options and analytics data will be removed when Clandevs Smart Catalog Filters is uninstalled.', 'clandevs-smart-catalog-filters' ); ?></p>
					</div>
				</div>

				<div class="wf-admin-submit-wrap">
					<button type="submit" class="wf-admin-submit">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
						<?php esc_html_e( 'Save Settings', 'clandevs-smart-catalog-filters' ); ?>
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
		echo '<p>' . esc_html__( 'Enable or disable individual filter blocks in the shop sidebar.', 'clandevs-smart-catalog-filters' ) . '</p>';
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
		echo ' ' . esc_html__( 'Enabled', 'clandevs-smart-catalog-filters' );
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
