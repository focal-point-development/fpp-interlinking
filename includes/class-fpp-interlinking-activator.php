<?php
/**
 * Fired during plugin activation.
 *
 * Creates the custom database table and sets default option values.
 * Uses dbDelta() so the method is safe to call on every upgrade as well.
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Activator {

	/**
	 * Run activation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_table();
		self::set_default_options();
	}

	/**
	 * Create or update the keywords table using dbDelta().
	 *
	 * dbDelta() compares the desired schema against the existing table and
	 * only applies incremental changes, making this safe for upgrades.
	 *
	 * @since 1.0.0
	 */
	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'fpp_interlinking_keywords';
		$charset_collate = $wpdb->get_charset_collate();

		// NOTE: dbDelta() requires:
		//  - Two spaces between PRIMARY KEY and opening parenthesis.
		//  - Each column on its own line.
		//  - KEY statements must use a name.
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

	/**
	 * Set default option values.
	 *
	 * Uses add_option() which only writes if the option does not exist yet,
	 * preserving user-configured values across re-activations.
	 *
	 * Third argument = deprecated, fourth = autoload.
	 * Settings that are only needed on specific pages use autoload = false
	 * to avoid loading them into the alloptions cache on every request.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Core replacement settings – loaded on frontend, autoload = true.
		add_option( 'fpp_interlinking_max_replacements', 1, '', true );
		add_option( 'fpp_interlinking_nofollow', 0, '', true );
		add_option( 'fpp_interlinking_new_tab', 1, '', true );
		add_option( 'fpp_interlinking_case_sensitive', 0, '', true );

		// Excluded posts – loaded on frontend, autoload = true.
		add_option( 'fpp_interlinking_excluded_posts', '', '', true );

		// DB version tracking – admin-only, autoload = false.
		add_option( 'fpp_interlinking_db_version', FPP_INTERLINKING_VERSION, '', false );

		// AI settings – admin-only, autoload = false.
		add_option( 'fpp_interlinking_ai_provider', 'openai', '', false );
		add_option( 'fpp_interlinking_ai_model', 'gpt-4o-mini', '', false );
		add_option( 'fpp_interlinking_ai_max_tokens', 2000, '', false );
	}
}
