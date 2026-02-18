<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Activator {

	public static function activate() {
		self::create_table();
		self::set_default_options();
	}

	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'fpp_interlinking_keywords';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword varchar(255) NOT NULL,
			target_url varchar(2083) NOT NULL,
			nofollow tinyint(1) NOT NULL DEFAULT 0,
			new_tab tinyint(1) NOT NULL DEFAULT 1,
			max_replacements int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY keyword_idx (keyword)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function set_default_options() {
		add_option( 'fpp_interlinking_max_replacements', 1 );
		add_option( 'fpp_interlinking_nofollow', 0 );
		add_option( 'fpp_interlinking_new_tab', 1 );
		add_option( 'fpp_interlinking_case_sensitive', 0 );
		add_option( 'fpp_interlinking_excluded_posts', '' );
		add_option( 'fpp_interlinking_db_version', FPP_INTERLINKING_VERSION );
	}
}
