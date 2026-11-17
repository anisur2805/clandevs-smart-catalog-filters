<?php
/**
 * Uninstall cleanup for Clandevs Smart Catalog Filters.
 *
 * Runs when the plugin is deleted via the Plugins screen. Plugin data is
 * only removed when the "Delete plugin data on uninstall" setting is
 * enabled; otherwise settings and analytics are left untouched.
 *
 * @package ClandevsSmartCatalogFilters
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cscf_plugin_options = get_option( 'cscf_filter_options', array() );
$cscf_delete_data    = is_array( $cscf_plugin_options )
	&& isset( $cscf_plugin_options['delete_data_on_uninstall'] )
	&& 'yes' === $cscf_plugin_options['delete_data_on_uninstall'];

if ( ! $cscf_delete_data ) {
	return;
}

// Remove the per-user review-prompt dismissals and saved shopper presets
// (stored network-wide on multisite).
delete_metadata( 'user', 0, 'cscf_review_prompt_dismissed', '', true );
delete_metadata( 'user', 0, 'cscf_presets', '', true );

// Remove the pending analytics flush event on each site in the network.
$cscf_sites = function_exists( 'get_sites' ) ? get_sites(
	array(
		'number' => 0,
		'fields' => 'ids',
	)
) : array( get_current_blog_id() );

foreach ( $cscf_sites as $cscf_site_id ) {
	switch_to_blog( $cscf_site_id );

	// Remove the pending analytics flush events.
	wp_clear_scheduled_hook( 'cscf_flush_analytics_buffer' );
	wp_clear_scheduled_hook( 'cscfp_flush_daily_analytics' );

	// Remove plugin options.
	delete_option( 'cscf_filter_options' );
	delete_option( 'cscf_style_options' );
	delete_option( 'cscf_filter_order' );
	delete_option( 'cscf_analytics_data' );
	delete_option( 'cscf_cache_last_changed' );
	delete_option( 'cscf_activation_time' );
	delete_option( 'cscf_filter_use_count' );

	// Remove the formerly Pro add-on's options. Their keys are unchanged by
	// the merge, so a store that ran the add-on is cleaned up here too.
	delete_option( 'cscfp_analytics_daily' );
	delete_option( 'cscf_presets' );
	delete_option( 'cscf_visual_swatches' );
	delete_option( 'cscf_date_range_enabled' );
	delete_option( 'cscf_variation_filter_enabled' );
	delete_option( 'cscfp_rewrite_base' );
	delete_option( 'cscfp_pretty_urls_enabled' );
	delete_option( 'cscfp_filtered_noindex' );

	// Remove transients (their timeouts are deleted alongside them).
	delete_transient( 'cscf_analytics_buffer' );
	delete_transient( 'cscf_analytics_reset_notice' );
	delete_transient( 'cscf_styles_reset_notice' );
	delete_transient( 'cscfp_analytics_buffer' );

	restore_current_blog();
}
