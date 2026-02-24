<?php
/**
 * Fired when the plugin is uninstalled (deleted from the admin).
 *
 * Removes all data created by the plugin:
 *  - The custom database table.
 *  - All wp_options entries.
 *  - The transient cache.
 *
 * On multisite, cleanup runs for every site in the network.
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

// Safety: only run through WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for a single site.
 *
 * @since 1.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return void
 */
function fpp_interlinking_uninstall_site() {
	global $wpdb;

	// Drop the keywords table.
	$table_name = $wpdb->prefix . 'fpp_interlinking_keywords';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Remove options.
	delete_option( 'fpp_interlinking_max_replacements' );
	delete_option( 'fpp_interlinking_nofollow' );
	delete_option( 'fpp_interlinking_new_tab' );
	delete_option( 'fpp_interlinking_case_sensitive' );
	delete_option( 'fpp_interlinking_excluded_posts' );
	delete_option( 'fpp_interlinking_db_version' );

	// AI options.
	delete_option( 'fpp_interlinking_ai_api_key' );
	delete_option( 'fpp_interlinking_ai_provider' );
	delete_option( 'fpp_interlinking_ai_model' );
	delete_option( 'fpp_interlinking_ai_max_tokens' );

	// Remove transients.
	delete_transient( 'fpp_interlinking_keywords_cache' );
}

// Multisite: iterate every site. Single-site: run once.
if ( is_multisite() ) {
	$site_ids = get_sites( array(
		'fields' => 'ids',
		'number' => 0,
	) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		fpp_interlinking_uninstall_site();
		restore_current_blog();
	}
} else {
	fpp_interlinking_uninstall_site();
}
