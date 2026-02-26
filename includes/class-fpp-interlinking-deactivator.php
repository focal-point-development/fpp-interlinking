<?php
/**
 * Fired during plugin deactivation.
 *
 * Clears the transient cache so stale data is not served if the plugin
 * is re-activated later.  No user data is removed – that only happens
 * on full uninstall (see uninstall.php).
 *
 * @since   1.0.0
 * @package FPP_Interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPP_Interlinking_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		delete_transient( 'fpp_interlinking_keywords_cache' );

		// v3.0.0: Clear analytics cron.
		wp_clear_scheduled_hook( 'fpp_interlinking_purge_analytics' );
	}
}
