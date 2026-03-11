<?php
/**
 * Uninstall cleanup for Woo Filter Studio.
 *
 * @package WooFilters
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = get_option( 'wf_filter_options', array() );
$delete  = is_array( $options ) && isset( $options['delete_data_on_uninstall'] ) && 'yes' === $options['delete_data_on_uninstall'];

if ( ! $delete ) {
	return;
}

delete_option( 'wf_filter_options' );
delete_option( 'wf_style_options' );
delete_option( 'wf_analytics_data' );
delete_option( 'wf_cache_last_changed' );
