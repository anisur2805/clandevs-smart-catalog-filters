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
	private const PAGE_SLUG = 'wf-style-settings';

	/**
	 * Register class hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
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
			__( 'Design Controls', 'woo-filters' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'preset_skin',
			__( 'Default Skin', 'woo-filters' ),
			array( $this, 'render_skin_field' ),
			self::PAGE_SLUG,
			'wf_style_section_main'
		);

		$this->register_color_field( 'accent_color', __( 'Accent Color', 'woo-filters' ) );
		$this->register_color_field( 'sidebar_bg_color', __( 'Sidebar Background', 'woo-filters' ) );
		$this->register_color_field( 'sidebar_border_color', __( 'Sidebar Border', 'woo-filters' ) );
		$this->register_color_field( 'heading_color', __( 'Heading Color', 'woo-filters' ) );
		$this->register_color_field( 'text_color', __( 'Body Text Color', 'woo-filters' ) );
		$this->register_color_field( 'muted_text_color', __( 'Muted Text Color', 'woo-filters' ) );
		$this->register_color_field( 'chip_bg_color', __( 'Filter Chip Background', 'woo-filters' ) );
		$this->register_color_field( 'chip_border_color', __( 'Filter Chip Border', 'woo-filters' ) );
		$this->register_color_field( 'button_bg_color', __( 'Primary Button Background', 'woo-filters' ) );
		$this->register_color_field( 'button_text_color', __( 'Primary Button Text', 'woo-filters' ) );
		$this->register_color_field( 'input_bg_color', __( 'Input Background', 'woo-filters' ) );
		$this->register_color_field( 'control_border_color', __( 'Input/Control Border', 'woo-filters' ) );
		$this->register_text_field( 'font_family', __( 'Font Family', 'woo-filters' ) );
		$this->register_number_field( 'font_size', __( 'Base Font Size (px)', 'woo-filters' ), 12, 24, 1 );
		$this->register_number_field( 'sidebar_width', __( 'Sidebar Width (px)', 'woo-filters' ), 220, 420, 1 );
		$this->register_number_field( 'layout_gap', __( 'Sidebar/Product Gap (px)', 'woo-filters' ), 12, 48, 1 );
		$this->register_number_field( 'sidebar_radius', __( 'Sidebar Radius (px)', 'woo-filters' ), 6, 28, 1 );
		$this->register_number_field( 'control_radius', __( 'Input Radius (px)', 'woo-filters' ), 4, 18, 1 );
		$this->register_number_field( 'button_radius', __( 'Button Radius (px)', 'woo-filters' ), 6, 28, 1 );
		$this->register_number_field( 'section_spacing', __( 'Section Spacing (px)', 'woo-filters' ), 10, 32, 1 );

		add_settings_field(
			'custom_css',
			__( 'Custom CSS', 'woo-filters' ),
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
			__( 'Woo Filters Styling', 'woo-filters' ),
			__( 'Woo Filters Styling', 'woo-filters' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
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

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Woo Filters Styling', 'woo-filters' ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'wf_style_settings' );
		do_settings_sections( self::PAGE_SLUG );
		submit_button( __( 'Save Styles', 'woo-filters' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render section copy.
	 *
	 * @return void
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Choose a preset skin and fine-tune colors/typography. CSS hooks: .wf-shop-layout, .wf-sidebar, .wf-chip, .wf-actions .button.alt.', 'woo-filters' ) . '</p>';
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
		$colors    = array(
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
			$safe_font = preg_replace( '/[^a-zA-Z0-9,\-\'\"\s]/', '', $font_family );
			if ( is_string( $safe_font ) && '' !== $safe_font ) {
				$sanitized['font_family'] = $safe_font;
			}
		}

		$numeric_ranges = array(
			'font_size'      => array( 12, 24 ),
			'sidebar_width'  => array( 220, 420 ),
			'layout_gap'     => array( 12, 48 ),
			'sidebar_radius' => array( 6, 28 ),
			'control_radius' => array( 4, 18 ),
			'button_radius'  => array( 6, 28 ),
			'section_spacing' => array( 10, 32 ),
		);

		foreach ( $numeric_ranges as $key => $range ) {
			$value = isset( $raw[ $key ] ) ? absint( wp_unslash( (string) $raw[ $key ] ) ) : 0;
			if ( $value >= $range[0] && $value <= $range[1] ) {
				$sanitized[ $key ] = (string) $value;
			}
		}

		$custom_css = isset( $raw['custom_css'] ) ? sanitize_textarea_field( wp_unslash( (string) $raw['custom_css'] ) ) : '';
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
			'preset_skin'          => 'classic',
			'accent_color'        => '#0b6a78',
			'sidebar_bg_color'    => '#ffffff',
			'sidebar_border_color' => '#e5e8ee',
			'heading_color'       => '#1f2937',
			'text_color'          => '#1f2937',
			'muted_text_color'    => '#64748b',
			'chip_bg_color'       => '#ffffff',
			'chip_border_color'   => '#c7d5e3',
			'button_bg_color'     => '#4b5563',
			'button_text_color'   => '#ffffff',
			'input_bg_color'      => '#ffffff',
			'control_border_color' => '#cbd5e1',
			'font_family'         => 'inherit',
			'font_size'           => '16',
			'sidebar_width'       => '280',
			'layout_gap'          => '24',
			'sidebar_radius'      => '14',
			'control_radius'      => '10',
			'button_radius'       => '12',
			'section_spacing'     => '18',
			'custom_css'          => '',
		);
	}

	/**
	 * Get available preset skins.
	 *
	 * @return array<string, string>
	 */
	private static function get_skin_choices(): array {
		return array(
			'classic'  => __( 'Classic', 'woo-filters' ),
			'graphite' => __( 'Graphite', 'woo-filters' ),
			'sunrise'  => __( 'Sunrise', 'woo-filters' ),
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

		return array_key_exists( $skin, self::get_skin_choices() ) ? $skin : 'classic';
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
					'accent_color'         => '#0f766e',
					'sidebar_bg_color'     => '#f8fafc',
					'sidebar_border_color' => '#d1d5db',
					'heading_color'        => '#111827',
					'text_color'           => '#111827',
					'muted_text_color'     => '#475569',
					'chip_bg_color'        => '#f9fafb',
					'chip_border_color'    => '#94a3b8',
					'button_bg_color'      => '#334155',
					'button_text_color'    => '#f8fafc',
					'input_bg_color'       => '#ffffff',
					'control_border_color' => '#cbd5e1',
				);
			case 'sunrise':
				return array(
					'accent_color'         => '#c2410c',
					'sidebar_bg_color'     => '#fffaf5',
					'sidebar_border_color' => '#fed7aa',
					'heading_color'        => '#7c2d12',
					'text_color'           => '#7c2d12',
					'muted_text_color'     => '#9a3412',
					'chip_bg_color'        => '#fff7ed',
					'chip_border_color'    => '#fdba74',
					'button_bg_color'      => '#ea580c',
					'button_text_color'    => '#ffffff',
					'input_bg_color'       => '#ffffff',
					'control_border_color' => '#fdba74',
				);
			case 'classic':
			default:
				return array();
		}
	}
}
