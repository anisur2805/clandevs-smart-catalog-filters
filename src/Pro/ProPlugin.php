<?php
/**
 * Pro add-on boot loader.
 *
 * @package ClandevsSmartCatalogFiltersPro
 */

namespace ClandevsSmartCatalogFiltersPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots Pro services once the free plugin is available.
 */
final class ProPlugin {

	/**
	 * Whether services have already been booted.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Boot all Pro services.
	 *
	 * The Elementor widget and the Blocks integration deliberately reuse the
	 * free plugin's widget and block names: each Pro service claims the name
	 * first, and the free registration then no-ops, so the editor shows one
	 * fully configurable version rather than two competing ones.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		if ( ! self::is_active() ) {
			return;
		}

		$services = array(
			new AdvancedAnalytics(),
			new BlocksIntegration(),
			new Comparison(),
			new DateRangeFilter(),
			new ElementorWidget(),
			new ImportExport(),
			new MultiCurrency(),
			new Presets(),
			new SEOFriendlyURLs(),
			new VariationFilter(),
			new VisualSwatches(),
		);

		foreach ( $services as $service ) {
			$service->register_hooks();
		}
	}

	/**
	 * Whether Pro features are licensed and running.
	 *
	 * The autoloader registers this class whether or not Pro is licensed, so
	 * class_exists() alone is not a reliable signal. The free plugin calls this
	 * before linking to a Pro-only screen.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return Gate::can_use_pro();
	}
}
