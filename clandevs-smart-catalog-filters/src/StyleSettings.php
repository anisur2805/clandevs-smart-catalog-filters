<?php
/**
 * Style settings controller.
 *
 * @package ClandevsSmartCatalogFilters
 */

namespace ClandevsSmartCatalogFilters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders admin style options for frontend filter UI.
 */
final class StyleSettings {
	/** @var string */
	private const OPTION_KEY = 'cscf_style_options';

	/** @var string */
	private const PAGE_SLUG = 'clandevs-smart-catalog-filters';

	/** @var string */
	private const RESET_ACTION = 'cscf_reset_styles';

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
			wp_die( esc_html__( 'You are not allowed to manage Clandevs Smart Catalog Filters styles.', 'clandevs-smart-catalog-filters' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		delete_option( self::OPTION_KEY );

		set_transient( 'cscf_styles_reset_notice', '1', 30 );

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
		if ( 'toplevel_page_clandevs-smart-catalog-filters' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cscf-admin-styles',
			CSCF_PLUGIN_URL . 'assets/css/wf-admin.css',
			array(),
			CSCF_VERSION
		);

		add_action( 'admin_footer', array( $this, 'render_color_picker_script' ) );
	}

	/**
	 * Print inline JS to sync color picker, preview, and hex input.
	 *
	 * @return void
	 */
	public function render_color_picker_script(): void {
		if ( 'toplevel_page_clandevs-smart-catalog-filters' !== $GLOBALS['hook_suffix'] ?? '' ) {
			return;
		}
		?>
		<script>
		(function(){
			function cscfSyncColor(key, val){
				document.querySelectorAll('.wf-admin-input-color[data-cscf-color="'+key+'"]').forEach(function(el){
					el.value = val;
				});
				document.querySelectorAll('input[type="hidden"][data-cscf-color="'+key+'"]').forEach(function(el){
					el.value = val;
				});
				document.querySelectorAll('.wf-admin-input-color-hex[data-cscf-color="'+key+'"]').forEach(function(el){
					el.value = val;
				});
				var preview = document.querySelector('[data-cscf-preview="'+key+'"]');
				if(preview) preview.style.background = val;
			}
			document.querySelectorAll('.wf-admin-input-color').forEach(function(el){
				el.addEventListener('change', function(){
					cscfSyncColor(this.getAttribute('data-cscf-color'), this.value);
				});
			});
			document.querySelectorAll('.wf-admin-input-color-hex').forEach(function(el){
				el.addEventListener('input', function(){
					if(/^#[0-9a-fA-F]{6}$/.test(this.value)){
						cscfSyncColor(this.getAttribute('data-cscf-color'), this.value);
					}
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Register plugin settings and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'cscf_style_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'cscf_style_section_main',
			__( 'Design Controls', 'clandevs-smart-catalog-filters' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'preset_skin',
			__( 'Default Skin', 'clandevs-smart-catalog-filters' ),
			array( $this, 'render_skin_field' ),
			self::PAGE_SLUG,
			'cscf_style_section_main'
		);

		$this->register_color_field( 'accent_color', __( 'Accent Color', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'sidebar_bg_color', __( 'Sidebar Background', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'sidebar_border_color', __( 'Sidebar Border', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'heading_color', __( 'Heading Color', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'text_color', __( 'Body Text Color', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'muted_text_color', __( 'Muted Text Color', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'chip_bg_color', __( 'Filter Chip Background', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'chip_border_color', __( 'Filter Chip Border', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'button_bg_color', __( 'Primary Button Background', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'button_text_color', __( 'Primary Button Text', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'input_bg_color', __( 'Input Background', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'control_border_color', __( 'Input/Control Border', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'no_results_bg_color', __( 'No Results Background', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'no_results_border_color', __( 'No Results Border', 'clandevs-smart-catalog-filters' ) );
		$this->register_color_field( 'no_results_shadow_color', __( 'No Results Shadow', 'clandevs-smart-catalog-filters' ) );
		$this->register_text_field( 'font_family', __( 'Font Family', 'clandevs-smart-catalog-filters' ) );
		$this->register_number_field( 'font_size', __( 'Base Font Size (px)', 'clandevs-smart-catalog-filters' ), 10, 48, 1 );
		$this->register_number_field( 'sidebar_width', __( 'Sidebar Width (px)', 'clandevs-smart-catalog-filters' ), 180, 600, 1 );
		$this->register_number_field( 'layout_gap', __( 'Sidebar/Product Gap (px)', 'clandevs-smart-catalog-filters' ), 0, 80, 1 );
		$this->register_number_field( 'sidebar_radius', __( 'Sidebar Radius (px)', 'clandevs-smart-catalog-filters' ), 0, 60, 1 );
		$this->register_number_field( 'control_radius', __( 'Input Radius (px)', 'clandevs-smart-catalog-filters' ), 0, 60, 1 );
		$this->register_number_field( 'button_radius', __( 'Button Radius (px)', 'clandevs-smart-catalog-filters' ), 0, 60, 1 );
		$this->register_number_field( 'section_spacing', __( 'Section Spacing (px)', 'clandevs-smart-catalog-filters' ), 0, 60, 1 );

	}

	/**
	 * Register submenu under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::get_menu_slug(),
			__( 'Catalog Filter Styling', 'clandevs-smart-catalog-filters' ),
			__( 'Styling', 'clandevs-smart-catalog-filters' ),
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
					<h1><?php esc_html_e( 'Clandevs Smart Catalog Filters Styling', 'clandevs-smart-catalog-filters' ); ?></h1>
					<p><?php esc_html_e( 'Customize the appearance of your shop filters', 'clandevs-smart-catalog-filters' ); ?></p>
				</div>
			</div>

			<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Style settings saved.', 'clandevs-smart-catalog-filters' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<?php
			if ( get_transient( 'cscf_styles_reset_notice' ) ) :
				delete_transient( 'cscf_styles_reset_notice' );
				?>
				<div class="wf-admin-card wf-admin-success-card">
					<div class="wf-admin-card-body wf-admin-card-body-compact">
						<p class="wf-admin-success-text">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
							<?php esc_html_e( 'Style settings have been reset to defaults.', 'clandevs-smart-catalog-filters' ); ?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<div class="wf-admin-card">
				<div class="wf-admin-card-header">
					<h2><?php esc_html_e( 'Design Controls', 'clandevs-smart-catalog-filters' ); ?></h2>
					<p><?php esc_html_e( 'Choose a preset skin and fine-tune colors and typography.', 'clandevs-smart-catalog-filters' ); ?></p>
				</div>
				<div class="wf-admin-card-body">
					<form action="options.php" method="post">
						<?php settings_fields( 'cscf_style_settings' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Default Skin', 'clandevs-smart-catalog-filters' ); ?></label>
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

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Colors', 'clandevs-smart-catalog-filters' ); ?></h3>

						<div class="wf-admin-grid-2">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Accent Color', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'accent_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Background', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'sidebar_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Border', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'sidebar_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Heading Color', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'heading_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Body Text Color', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'text_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Muted Text Color', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'muted_text_color' ); ?>
									</td>
								</tr>
							</table>
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Filter Chip Background', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'chip_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Filter Chip Border', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'chip_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Primary Button Background', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'button_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Primary Button Text', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'button_text_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Input Background', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'input_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Input/Control Border', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'control_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Background', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_bg_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Border', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_border_color' ); ?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'No Results Shadow', 'clandevs-smart-catalog-filters' ); ?></label>
									</th>
									<td>
										<?php $this->render_color_field_with_preview( 'no_results_shadow_color' ); ?>
									</td>
								</tr>
							</table>
						</div>

						<hr class="wf-admin-divider" />

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Typography & Layout', 'clandevs-smart-catalog-filters' ); ?></h3>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label class="wf-admin-label"><?php esc_html_e( 'Font Family', 'clandevs-smart-catalog-filters' ); ?></label>
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
									<label class="wf-admin-label"><?php esc_html_e( 'Base Font Size (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
									<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Width (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
									<label class="wf-admin-label"><?php esc_html_e( 'Sidebar/Product Gap (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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

						<h3 class="wf-admin-section-title"><?php esc_html_e( 'Border Radius', 'clandevs-smart-catalog-filters' ); ?></h3>

						<div class="wf-admin-grid-2">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label class="wf-admin-label"><?php esc_html_e( 'Sidebar Radius (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
										<label class="wf-admin-label"><?php esc_html_e( 'Input Radius (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
										<label class="wf-admin-label"><?php esc_html_e( 'Button Radius (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
										<label class="wf-admin-label"><?php esc_html_e( 'Section Spacing (px)', 'clandevs-smart-catalog-filters' ); ?></label>
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
								<?php esc_html_e( 'Save Styles', 'clandevs-smart-catalog-filters' ); ?>
							</button>
							<a href="<?php echo esc_url( $reset_url ); ?>" class="wf-admin-reset-btn" data-confirm="<?php echo esc_attr__( 'Are you sure you want to reset all style settings to defaults?', 'clandevs-smart-catalog-filters' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
								<?php esc_html_e( 'Reset to Defaults', 'clandevs-smart-catalog-filters' ); ?>
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
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '#000000';
		$hex     = sanitize_hex_color( $value );

		echo '<div class="wf-admin-color-row">';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $hex ) . '" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '<input type="color" class="wf-admin-input-color" value="' . esc_attr( $hex ) . '" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '<div class="wf-admin-color-preview" style="background:' . esc_attr( $hex ) . ';" data-cscf-preview="' . esc_attr( $key ) . '"></div>';
		echo '<input type="text" class="wf-admin-input-color-hex" value="' . esc_attr( $hex ) . '" maxlength="7" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '</div>';
	}

	/**
	 * Render section copy.
	 *
	 * @return void
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Choose a preset skin and fine-tune colors/typography. CSS hooks: .wf-shop-layout, .wf-sidebar, .wf-chip, .wf-actions .button.alt, .wf-no-results.', 'clandevs-smart-catalog-filters' ) . '</p>';
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
			'cscf_style_section_main',
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
			'cscf_style_section_main',
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
			'cscf_style_section_main',
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
		$value   = isset( $options[ $key ] ) ? (string) $options[ $key ] : '#000000';
		$hex     = sanitize_hex_color( $value );

		echo '<div class="wf-admin-color-row">';
		echo '<input type="hidden" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $hex ) . '" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '<input type="color" class="wf-admin-input-color" value="' . esc_attr( $hex ) . '" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '<div class="wf-admin-color-preview" style="background:' . esc_attr( $hex ) . ';" data-cscf-preview="' . esc_attr( $key ) . '"></div>';
		echo '<input type="text" class="wf-admin-input-color-hex" value="' . esc_attr( $hex ) . '" maxlength="7" data-cscf-color="' . esc_attr( $key ) . '" />';
		echo '</div>';
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
		);
	}

	/**
	 * Get available preset skins.
	 *
	 * @return array<string, string>
	 */
	private static function get_skin_choices(): array {
		return array(
			'classic'  => __( 'Classic', 'clandevs-smart-catalog-filters' ),
			'graphite' => __( 'Graphite', 'clandevs-smart-catalog-filters' ),
			'sunrise'  => __( 'Sunrise', 'clandevs-smart-catalog-filters' ),
		);
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
