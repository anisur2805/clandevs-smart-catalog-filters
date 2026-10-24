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

// Remove the per-user review-prompt dismissals (stored network-wide on multisite).
delete_metadata( 'user', 0, 'cscf_review_prompt_dismissed', '', true );

// Remove the pending analytics flush event on each site in the network.
$cscf_sites = function_exists( 'get_sites' ) ? get_sites(
	array(
		'number' => 0,
		'fields' => 'ids',
	)
) : array( get_current_blog_id() );

foreach ( $cscf_sites as $cscf_site_id ) {
	switch_to_blog( $cscf_site_id );

	// Remove the pending analytics flush event.
	wp_clear_scheduled_hook( 'cscf_flush_analytics_buffer' );

	// Remove plugin options.
	delete_option( 'cscf_filter_options' );
	delete_option( 'cscf_style_options' );
	delete_option( 'cscf_filter_order' );
	delete_option( 'cscf_analytics_data' );
	delete_option( 'cscf_cache_last_changed' );
	delete_option( 'cscf_activation_time' );
	delete_option( 'cscf_filter_use_count' );

	// Remove transients (their timeouts are deleted alongside them).
	delete_transient( 'cscf_analytics_buffer' );
	delete_transient( 'cscf_analytics_reset_notice' );
	delete_transient( 'cscf_styles_reset_notice' );

	restore_current_blog();
}
