<?php
/**
 * Main plugin service container.
 *
 * @package AdvancedProductFilter
 */

namespace AdvancedProductFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots plugin services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Plugin main file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin base URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version = '';

	/**
	 * Shop filters service.
	 *
	 * @var ShopFilters|null
	 */
	private $shop_filters = null;

	/**
	 * Style settings service.
	 *
	 * @var StyleSettings|null
	 */
	private $style_settings = null;

	/**
	 * Filter settings service.
	 *
	 * @var FilterSettings|null
	 */
	private $filter_settings = null;

	/**
	 * Analytics service.
	 *
	 * @var Analytics|null
	 */
	private $analytics = null;

	/**
	 * Admin menu service.
	 *
	 * @var AdminMenu|null
	 */
	private $admin_menu = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Main plugin file.
	 */
	private function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_url  = plugin_dir_url( $plugin_file );
		$this->version     = defined( 'APF_VERSION' ) ? APF_VERSION : '1.0.0';
	}

	/**
	 * Get singleton instance.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @return self
	 */
	public static function instance( string $plugin_file ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
		}

		return self::$instance;
	}

	/**
	 * Boot services.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->shop_filters instanceof ShopFilters ) {
			return;
		}

		$this->shop_filters = new ShopFilters( $this->plugin_url, $this->version );
		$this->shop_filters->register_hooks();

		$this->admin_menu = new AdminMenu();
		$this->admin_menu->register_hooks();

		$this->style_settings = new StyleSettings();
		$this->style_settings->register_hooks();

		$this->filter_settings = new FilterSettings();
		$this->filter_settings->register_hooks();

		$this->analytics = new Analytics();
		$this->analytics->register_hooks();
	}
}
