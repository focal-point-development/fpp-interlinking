<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'fpp_interlinking_keywords';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

delete_option( 'fpp_interlinking_max_replacements' );
delete_option( 'fpp_interlinking_nofollow' );
delete_option( 'fpp_interlinking_new_tab' );
delete_option( 'fpp_interlinking_case_sensitive' );
delete_option( 'fpp_interlinking_excluded_posts' );
delete_option( 'fpp_interlinking_db_version' );
