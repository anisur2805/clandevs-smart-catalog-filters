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
			__( 'Filter Visibility', 'woo-filters' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		$this->register_checkbox_field( 'show_categories', __( 'Show Categories', 'woo-filters' ) );
		$this->register_checkbox_field( 'show_brands', __( 'Show Brands', 'woo-filters' ) );
		$this->register_checkbox_field( 'show_price', __( 'Show Price', 'woo-filters' ) );
		$this->register_checkbox_field( 'show_rating', __( 'Show Customer Rating', 'woo-filters' ) );
		$this->register_checkbox_field( 'show_availability', __( 'Show Availability', 'woo-filters' ) );
		$this->register_checkbox_field( 'show_colors', __( 'Show Color', 'woo-filters' ) );
	}

	/**
	 * Register submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AdminMenu::get_menu_slug(),
			__( 'Woo Filters Settings', 'woo-filters' ),
			__( 'Woo Filters Settings', 'woo-filters' ),
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

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Woo Filters Settings', 'woo-filters' ) . '</h1>';
		echo '<form action="options.php" method="post">';
		settings_fields( 'wf_filter_settings' );
		do_settings_sections( self::PAGE_SLUG );
		submit_button( __( 'Save Settings', 'woo-filters' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render section intro.
	 *
	 * @return void
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__( 'Enable or disable individual filter blocks in the shop sidebar.', 'woo-filters' ) . '</p>';
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
		echo ' ' . esc_html__( 'Enabled', 'woo-filters' );
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
			'show_categories'   => 'yes',
			'show_brands'       => 'yes',
			'show_price'        => 'yes',
			'show_rating'       => 'yes',
			'show_availability' => 'yes',
			'show_colors'       => 'yes',
		);
	}
}
