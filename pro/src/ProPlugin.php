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

final class ProPlugin {
	/**
	 * Boot all Pro services.
	 *
	 * @return void
	 */
	public static function boot(): void {
		// Check Freemius license.
		if ( function_exists( 'cscfp_fs' ) ) {
			$fs = cscfp_fs();
			if ( ! $fs->is_paying() && ! $fs->is_trial() ) {
				return;
			}
		}

		$services = array(
			new FilterOrdering(),
			new VisualSwatches(),
			new Presets(),
			new DateRangeFilter(),
			new AdvancedAnalytics(),
			new Comparison(),
			new ImportExport(),
			new VariationFilter(),
			new SEOFriendlyURLs(),
			new MultiCurrency(),
			new BlocksIntegration(),
			new ElementorWidget(),
		);

		foreach ( $services as $service ) {
			$service->register_hooks();
		}
	}
}
