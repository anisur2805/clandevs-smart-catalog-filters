<?php
/**
 * Style settings controller.
 *
 * @package WooFilters
 */

namespace WooFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders admin style options for frontend filter UI.
 */
final class StyleSettings {
	/** @var string */
	private const OPTION_KEY = 'wf_style_options';

	/** @var string */
	private const PAGE_SLUG = 'advanced-product-filter';

	/** @var string */
	private const RESET_ACTION = 'wf_reset_styles';

	/**
	 * Register class hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset_request' ) );
	}

	/**
	 * Handle style settings reset request.
	 *
	 * @return void
	 */
	public function handle_reset_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Advanced Product Filter styles.', 'advanced-product-filter' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		delete_option( self::OPTION_KEY );

		set_transient( 'wf_styles_reset_notice', '1', 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue admin assets for settings page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_woo-filter-studio' !== $hook ) {
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
	 * Register plugin settings and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wf_style_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'wf_style_section_main',
			__( 'Design Controls', 'advanced-product-filter' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'preset_skin',
			__( 'Default Skin', 'advanced-product-filter' ),
			array( $this, 'render_skin_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main'
		);

		$this->register_color_field( 'accent_color', __( 'Accent Color', 'advanced-product-filter' ) );
		$this->register_color_field( 'sidebar_bg_color', __( 'Sidebar Background', 'advanced-product-filter' ) );
		$this->register_color_field( 'sidebar_border_color', __( 'Sidebar Border', 'advanced-product-filter' ) );
		$this->register_color_field( 'heading_color', __( 'Heading Color', 'advanced-product-filter' ) );
		$this->register_color_field( 'text_color', __( 'Body Text Color', 'advanced-product-filter' ) );
		$this->register_color_field( 'muted_text_color', __( 'Muted Text Color', 'advanced-product-filter' ) );
		$this->register_color_field( 'chip_bg_color', __( 'Filter Chip Background', 'advanced-product-filter' ) );
		$this->register_color_field( 'chip_border_color', __( 'Filter Chip Border', 'advanced-product-filter' ) );
		$this->register_color_field( 'button_bg_color', __( 'Primary Button Background', 'advanced-product-filter' ) );
		$this->register_color_field( 'button_text_color', __( 'Primary Button Text', 'advanced-product-filter' ) );
		$this->register_color_field( 'input_bg_color', __( 'Input Background', 'advanced-product-filter' ) );
		$this->register_color_field( 'control_border_color', __( 'Input/Control Border', 'advanced-product-filter' ) );
		$this->register_color_field( 'no_results_bg_color', __( 'No Results Background', 'advanced-product-filter' ) );
		$this->register_color_field( 'no_results_border_color', __( 'No Results Border', 'advanced-product-filter' ) );
		$this->register_color_field( 'no_results_shadow_color', __( 'No Results Shadow', 'advanced-product-filter' ) );
		$this->register_text_field( 'font_family', __( 'Font Family', 'advanced-product-filter' ) );
		$this->register_number_field( 'font_size', __( 'Base Font Size (px)', 'advanced-product-filter' ), 10, 48, 1 );
		$this->register_number_field( 'sidebar_width', __( 'Sidebar Width (px)', 'advanced-product-filter' ), 180, 600, 1 );
		$this->register_number_field( 'layout_gap', __( 'Sidebar/Product Gap (px)', 'advanced-product-filter' ), 0, 80, 1 );
		$this->register_number_field( 'sidebar_radius', __( 'Sidebar Radius (px)', 'advanced-product-filter' ), 0, 60, 1 );
		$this->register_number_field( 'control_radius', __( 'Input Radius (px)', 'advanced-product-filter' ), 0, 60, 1 );
		$this->register_number_field( 'button_radius', __( 'Button Radius (px)', 'advanced-product-filter' ), 0, 60, 1 );
		$this->register_number_field( 'section_spacing', __( 'Section Spacing (px)', 'advanced-product-filter' ), 0, 60, 1 );

		add_settings_field(
			'custom_css',
			__( 'Custom CSS', 'advanced-product-filter' ),
			array( $this, 'render_custom_css_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main'
		);
	}

	/**
	 * Register submenu under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::get_menu_slug(),
			__( 'Advanced Product Filter Styling', 'advanced-product-filter' ),
			__( 'Styling', 'advanced-product-filter' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			0
		);
	}

	/**
	 * Render style settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<div class="wf-admin-wrap">
			<div class="wf-admin-header">
				<div class="wf-admin-header-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
				</div>
				<div>
					<h1><?php esc_html_e( 'Advanced Product Filter Styling', 'advanced-product-filter' ); ?></h1>
					<p><?php esc_html_e( 'Customize the appearance of your shop filters', 'advanced-product-filter' ); ?></p>
				</div>
			</div>

			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Style settings saved.', 'advanced-product-filter' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<?php
			if ( get_transient( 'wf_styles_reset_notice' ) ) :
				delete_transient( 'wf_styles_reset_notice' );
				?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Style settings have been reset to defaults.', 'advanced-product-filter' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<div class="wf-admin-card">
				<div class="wf-admin-card-header">
					<h2><?php esc_html_e( 'Design Controls', 'advanced-product-filter' ); ?></h2>
					<p><?php esc_html_e( 'Choose a preset skin and fine-tune colors and typography.', 'advanced-product-filter' ); ?></p>
				</div>
				<div class="wf-admin-card-body">
					<form action="options.php" method="post">
						<?php settings_fields( 'wf_style_settings' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Default Skin', 'advanced-product-filter' ); ?></label>
								</th>
								<td>
									<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preset_skin]" class="wf-admin-select">
										<?php
										$options      = self::get_options();
										$current_skin = isset( $options['preset_skin'] ) ? self::sanitize_skin( (string) $options['preset_skin'] ) : 'classic';
										foreach ( self::get_skin_choices() as $slug => $label ) :
											?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_skin, $slug ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						</table>

						<hr class="wf-admin-divider" />

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Colors', 'advanced-product-filter' ); ?></h3>

						<div class="wf-admin-grid-2">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Accent Color', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'accent_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Background', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'sidebar_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Border', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'sidebar_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Heading Color', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'heading_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Body Text Color', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'text_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Muted Text Color', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'muted_text_color' ); ?>
									</td>
								</tr>
							</table>
							<?php if ( License::can( 'full_styling' ) ) : ?>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Filter Chip Background', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'chip_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Filter Chip Border', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'chip_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Primary Button Background', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'button_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Primary Button Text', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'button_text_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Input Background', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'input_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Input/Control Border', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'control_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Background', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Border', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Shadow', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_shadow_color' ); ?>
									</td>
								</tr>
							</table>
							<?php else : ?>
							<div style="display:flex;align-items:center;justify-content:center;padding:20px;">
								<a href="<?php echo esc_url( License::get_upgrade_url() ); ?>" class="wf-admin-pro-badge" style="font-size:12px;padding:6px 14px;"><?php esc_html_e( '9 more color controls with Pro', 'advanced-product-filter' ); ?></a>
							</div>
							<?php endif; ?>
						</div>

						<?php if ( License::can( 'full_styling' ) ) : ?>

						<hr class="wf-admin-divider" />

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Typography & Layout', 'advanced-product-filter' ); ?></h3>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Font Family', 'advanced-product-filter' ); ?></label>
								</th>
								<td>
									<?php
									$options = self::get_options();
									$value   = isset( $options['font_family'] ) ? (string) $options['font_family'] : '';
									?>
									<input type="text" class="wf-admin-input" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_family]" value="<?php echo esc_attr( $value ); ?>" placeholder="inherit" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Base Font Size (px)', 'advanced-product-filter' ); ?></label>
								</th>
								<td>
									<?php
									$options = self::get_options();
									$value   = isset( $options['font_size'] ) ? absint( (string) $options['font_size'] ) : '';
									?>
									<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[font_size]" value="<?php echo esc_attr( (string) $value ); ?>" min="10" max="48" step="1" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Width (px)', 'advanced-product-filter' ); ?></label>
								</th>
								<td>
									<?php
									$options = self::get_options();
									$value   = isset( $options['sidebar_width'] ) ? absint( (string) $options['sidebar_width'] ) : '';
									?>
									<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sidebar_width]" value="<?php echo esc_attr( (string) $value ); ?>" min="180" max="600" step="1" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Sidebar/Product Gap (px)', 'advanced-product-filter' ); ?></label>
								</th>
								<td>
									<?php
									$options = self::get_options();
									$value   = isset( $options['layout_gap'] ) ? absint( (string) $options['layout_gap'] ) : '';
									?>
									<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[layout_gap]" value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="80" step="1" />
								</td>
							</tr>
						</table>

						<hr class="wf-admin-divider" />

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Border Radius', 'advanced-product-filter' ); ?></h3>

						<div class="wf-admin-grid-2">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Radius (px)', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php
										$options = self::get_options();
										$value   = isset( $options['sidebar_radius'] ) ? absint( (string) $options['sidebar_radius'] ) : '';
										?>
										<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[sidebar_radius]" value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="60" step="1" />
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Input Radius (px)', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php
										$options = self::get_options();
										$value   = isset( $options['control_radius'] ) ? absint( (string) $options['control_radius'] ) : '';
										?>
										<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[control_radius]" value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="60" step="1" />
									</td>
								</tr>
							</table>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Button Radius (px)', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php
										$options = self::get_options();
										$value   = isset( $options['button_radius'] ) ? absint( (string) $options['button_radius'] ) : '';
										?>
										<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[button_radius]" value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="60" step="1" />
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Section Spacing (px)', 'advanced-product-filter' ); ?></label>
									</th>
									<td>
										<?php
										$options = self::get_options();
										$value   = isset( $options['section_spacing'] ) ? absint( (string) $options['section_spacing'] ) : '';
										?>
										<input type="number" class="wf-admin-input wf-admin-input-small" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[section_spacing]" value="<?php echo esc_attr( (string) $value ); ?>" min="0" max="60" step="1" />
									</td>
								</tr>
							</table>
						</div>

						<?php endif; ?>

						<?php if ( License::can( 'custom_css' ) ) : ?>

						<hr class="wf-admin-divider" />

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Custom CSS', 'advanced-product-filter' ); ?></h3>

						<table class="form-table">
							<tr>
								<td colspan="2">
									<?php
									$options = self::get_options();
									$value   = isset( $options['custom_css'] ) ? (string) $options['custom_css'] : '';
									?>
									<textarea class="wf-admin-textarea" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_css]" rows="8" placeholder=".wf-shop-layout { /* your custom styles */ }"><?php echo esc_textarea( $value ); ?></textarea>
									<p class="description wf-admin-help"><?php esc_html_e( 'CSS hooks: .wf-shop-layout, .wf-sidebar, .wf-chip, .wf-actions .button.alt, .wf-no-results', 'advanced-product-filter' ); ?></p>
								</td>
							</tr>
						</table>

						<?php endif; ?>

						<?php if ( ! License::can( 'full_styling' ) || ! License::can( 'custom_css' ) ) : ?>

						<hr class="wf-admin-divider" />

						<div class="wf-admin-upgrade-notice">
							<h3><?php esc_html_e( 'Unlock Full Styling Controls', 'advanced-product-filter' ); ?></h3>
							<p><?php esc_html_e( 'Typography, layout, border radius, and custom CSS are available with Pro.', 'advanced-product-filter' ); ?></p>
							<a href="<?php echo esc_url( License::get_upgrade_url() ); ?>" class="wf-admin-submit"><?php esc_html_e( 'Upgrade to Pro', 'advanced-product-filter' ); ?></a>
						</div>

						<?php endif; ?>

						<?php
						$reset_url = wp_nonce_url(
							add_query_arg(
								array( 'action' => self::RESET_ACTION ),
								admin_url( 'admin-post.php' )
							),
							self::RESET_ACTION
						);
						?>
						<div class="wf-admin-submit-wrap" style="display: flex; align-items: center; gap: 16px;">
							<button type="submit" class="wf-admin-submit">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
								<?php esc_html_e( 'Save Styles', 'advanced-product-filter' ); ?>
							</button>
							<a href="<?php echo esc_url( $reset_url ); ?>" class="wf-admin-reset-btn" data-confirm="<?php echo esc_attr__( 'Are you sure you want to reset all style settings to defaults?', 'advanced-product-filter' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
								<?php esc_html_e( 'Reset to Defaults', 'advanced-product-filter' ); ?>
							</a>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render color field with preview.
	 *
	 * @param string $key Field key.
	 * @return void
	 */
	public function render_color_field_with_preview( string $key ): void {
		$options = self::get_options();
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '';

		echo '<div class="wf-admin-color-row">';
		echo '<input type="color" class="wf-admin-input wf-admin-input-color" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
		echo '<span class="wf-admin-color-hex">' . esc_html( $value ) . '</span>';
		echo '</div>';
	}

	/**
	 * Render section copy.
	 *
	 * @return void
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Choose a preset skin and fine-tune colors/typography. CSS hooks: .wf-shop-layout, .wf-sidebar, .wf-chip, .wf-actions .button.alt, .wf-no-results.', 'advanced-product-filter' ) . '</p>';
	}

	/**
	 * Render skin select field.
	 *
	 * @return void
	 */
	public function render_skin_field(): void {
		$options      = self::get_options();
		$current_skin = isset( $options['preset_skin'] ) ? self::sanitize_skin( (string) $options['preset_skin'] ) : 'classic';

		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[preset_skin]">';
		foreach ( self::get_skin_choices() as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $current_skin, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Register a color picker field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @return void
	 */
	private function register_color_field( string $key, string $label ): void {
		add_settings_field(
			$key,
			$label,
			array( $this, 'render_color_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main',
			array(
				'key' => $key,
			)
		);
	}

	/**
	 * Register a text input field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @return void
	 */
	private function register_text_field( string $key, string $label ): void {
		add_settings_field(
			$key,
			$label,
			array( $this, 'render_text_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main',
			array(
				'key' => $key,
			)
		);
	}

	/**
	 * Register a number input field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param int    $min   Minimum value.
	 * @param int    $max   Maximum value.
	 * @param int    $step  Step value.
	 * @return void
	 */
	private function register_number_field( string $key, string $label, int $min, int $max, int $step ): void {
		add_settings_field(
			$key,
			$label,
			array( $this, 'render_number_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main',
			array(
				'key'  => $key,
				'min'  => $min,
				'max'  => $max,
				'step' => $step,
			)
		);
	}

	/**
	 * Render color input.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_color_field( array $args ): void {
		$key     = isset( $args['key'] ) ? sanitize_key( (string) $args['key'] ) : '';
		$options = self::get_options();
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '';

		echo '<input type="color" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render text input.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$key     = isset( $args['key'] ) ? sanitize_key( (string) $args['key'] ) : '';
		$options = self::get_options();
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '';

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render number input.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$key     = isset( $args['key'] ) ? sanitize_key( (string) $args['key'] ) : '';
		$min     = isset( $args['min'] ) ? absint( $args['min'] ) : 0;
		$max     = isset( $args['max'] ) ? absint( $args['max'] ) : 999;
		$step    = isset( $args['step'] ) ? absint( $args['step'] ) : 1;
		$options = self::get_options();
		$value   = isset( $options[ $key ] ) ? absint( (string) $options[ $key ] ) : '';

		echo '<input type="number" class="small-text" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" step="' . esc_attr( (string) $step ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
	}

	/**
	 * Render custom CSS textarea.
	 *
	 * @return void
	 */
	public function render_custom_css_field(): void {
		$options = self::get_options();
		$value   = isset( $options['custom_css'] ) ? (string) $options['custom_css'] : '';

		echo '<textarea name="' . esc_attr( self::OPTION_KEY ) . '[custom_css]" rows="8" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $raw Raw settings data.
	 * @return array
	 */
	public function sanitize_settings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::get_defaults();
		}

		$sanitized                = array();
		$sanitized['preset_skin'] = self::sanitize_skin( isset( $raw['preset_skin'] ) ? (string) $raw['preset_skin'] : 'classic' );
		$colors                   = array(
			'accent_color',
			'sidebar_bg_color',
			'sidebar_border_color',
			'heading_color',
			'text_color',
			'muted_text_color',
			'chip_bg_color',
			'chip_border_color',
			'button_bg_color',
			'button_text_color',
			'input_bg_color',
			'control_border_color',
			'no_results_bg_color',
			'no_results_border_color',
			'no_results_shadow_color',
		);

		foreach ( $colors as $key ) {
			$input = isset( $raw[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $raw[ $key ] ) ) : '';
			$color = sanitize_hex_color( $input );

			if ( null !== $color && '' !== $color ) {
				$sanitized[ $key ] = $color;
			}
		}

		$font_family = isset( $raw['font_family'] ) ? sanitize_text_field( wp_unslash( (string) $raw['font_family'] ) ) : '';
		if ( '' !== $font_family ) {
			$safe_font = preg_replace( '/[^a-zA-Z0-9,\-\s]/', '', $font_family );
			if ( is_string( $safe_font ) && '' !== $safe_font ) {
				$sanitized['font_family'] = $safe_font;
			}
		}

		$numeric_ranges = array(
			'font_size'       => array( 10, 48 ),
			'sidebar_width'   => array( 180, 600 ),
			'layout_gap'      => array( 0, 80 ),
			'sidebar_radius'  => array( 0, 60 ),
			'control_radius'  => array( 0, 60 ),
			'button_radius'   => array( 0, 60 ),
			'section_spacing' => array( 0, 60 ),
		);

		foreach ( $numeric_ranges as $key => $range ) {
			$value = isset( $raw[ $key ] ) ? absint( wp_unslash( (string) $raw[ $key ] ) ) : 0;
			if ( $value >= $range[0] && $value <= $range[1] ) {
				$sanitized[ $key ] = (string) $value;
			}
		}

		$custom_css = isset( $raw['custom_css'] ) ? wp_unslash( (string) $raw['custom_css'] ) : '';
		$custom_css = wp_strip_all_tags( $custom_css );
		if ( '' !== $custom_css ) {
			$sanitized['custom_css'] = $custom_css;
		}

		return $sanitized;
	}

	/**
	 * Get merged options with defaults.
	 *
	 * @return array
	 */
	public static function get_options(): array {
		$options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = self::get_defaults();
		$skin     = isset( $options['preset_skin'] ) ? self::sanitize_skin( (string) $options['preset_skin'] ) : 'classic';
		$preset   = self::get_skin_values( $skin );
		$merged   = array_merge( $defaults, $preset, $options );

		$merged['preset_skin'] = $skin;

		return $merged;
	}

	/**
	 * Get default style values.
	 *
	 * @return array
	 */
	private static function get_defaults(): array {
		return array(
			'preset_skin'             => 'classic',
			'accent_color'            => '#0b6a78',
			'sidebar_bg_color'        => '#ffffff',
			'sidebar_border_color'    => '#e5e8ee',
			'heading_color'           => '#1f2937',
			'text_color'              => '#1f2937',
			'muted_text_color'        => '#64748b',
			'chip_bg_color'           => '#ffffff',
			'chip_border_color'       => '#c7d5e3',
			'button_bg_color'         => '#4b5563',
			'button_text_color'       => '#ffffff',
			'input_bg_color'          => '#ffffff',
			'control_border_color'    => '#cbd5e1',
			'no_results_bg_color'     => '#ffffff',
			'no_results_border_color' => '#dbe2ea',
			'no_results_shadow_color' => '#0f172a',
			'font_family'             => 'inherit',
			'font_size'               => '16',
			'sidebar_width'           => '280',
			'layout_gap'              => '24',
			'sidebar_radius'          => '14',
			'control_radius'          => '10',
			'button_radius'           => '12',
			'section_spacing'         => '18',
			'custom_css'              => '',
		);
	}

	/**
	 * Get available preset skins.
	 *
	 * @return array<string, string>
	 */
	private static function get_skin_choices(): array {
		$skins = array(
			'classic' => __( 'Classic', 'advanced-product-filter' ),
		);

		if ( License::can( 'extra_skins' ) ) {
			$skins['graphite'] = __( 'Graphite', 'advanced-product-filter' );
			$skins['sunrise']  = __( 'Sunrise', 'advanced-product-filter' );
		} else {
			$skins['graphite'] = __( 'Graphite (Pro)', 'advanced-product-filter' );
			$skins['sunrise']  = __( 'Sunrise (Pro)', 'advanced-product-filter' );
		}

		return $skins;
	}

	/**
	 * Sanitize skin value.
	 *
	 * @param string $skin Raw skin.
	 * @return string
	 */
	private static function sanitize_skin( string $skin ): string {
		$skin = sanitize_key( $skin );

		if ( ! array_key_exists( $skin, self::get_skin_choices() ) ) {
			return 'classic';
		}

		$pro_skins = array( 'graphite', 'sunrise' );
		if ( in_array( $skin, $pro_skins, true ) && ! License::can( 'extra_skins' ) ) {
			return 'classic';
		}

		return $skin;
	}

	/**
	 * Get style variables for a preset skin.
	 *
	 * @param string $skin Skin key.
	 * @return array
	 */
	private static function get_skin_values( string $skin ): array {
		switch ( self::sanitize_skin( $skin ) ) {
			case 'graphite':
				return array(
					'accent_color'            => '#0f766e',
					'sidebar_bg_color'        => '#f8fafc',
					'sidebar_border_color'    => '#d1d5db',
					'heading_color'           => '#111827',
					'text_color'              => '#111827',
					'muted_text_color'        => '#475569',
					'chip_bg_color'           => '#f9fafb',
					'chip_border_color'       => '#94a3b8',
					'button_bg_color'         => '#334155',
					'button_text_color'       => '#f8fafc',
					'input_bg_color'          => '#ffffff',
					'control_border_color'    => '#cbd5e1',
					'no_results_bg_color'     => '#f8fafc',
					'no_results_border_color' => '#d1d5db',
					'no_results_shadow_color' => '#0f172a',
				);
			case 'sunrise':
				return array(
					'accent_color'            => '#c2410c',
					'sidebar_bg_color'        => '#fffaf5',
					'sidebar_border_color'    => '#fed7aa',
					'heading_color'           => '#7c2d12',
					'text_color'              => '#7c2d12',
					'muted_text_color'        => '#9a3412',
					'chip_bg_color'           => '#fff7ed',
					'chip_border_color'       => '#fdba74',
					'button_bg_color'         => '#ea580c',
					'button_text_color'       => '#ffffff',
					'input_bg_color'          => '#ffffff',
					'control_border_color'    => '#fdba74',
					'no_results_bg_color'     => '#fffaf5',
					'no_results_border_color' => '#fed7aa',
					'no_results_shadow_color' => '#7c2d12',
				);
			case 'classic':
			default:
				return array();
		}
	}
}
