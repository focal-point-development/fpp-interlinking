<?php
/**
 * Plugin Name:       FPP Interlinking
 * Plugin URI:        https://developer.wordpress.org/plugins/
 * Description:       Automate SEO internal linking by mapping keywords to target URLs. Configured keywords are automatically replaced with anchor links in posts and pages, with support for per-keyword overrides, nofollow/new-tab settings, case sensitivity, post exclusions, and self-link prevention.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            FPP
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fpp-interlinking
 * Domain Path:       /languages
 *
 * @package FPP_Interlinking
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'FPP_INTERLINKING_VERSION', '1.1.0' );
define( 'FPP_INTERLINKING_DB_VERSION', '1.1.0' );
define( 'FPP_INTERLINKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPP_INTERLINKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FPP_INTERLINKING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FPP_INTERLINKING_MAX_REPLACEMENTS_LIMIT', 100 );

/**
 * Autoload plugin classes.
 */
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-activator.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-deactivator.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-db.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-admin.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-replacer.php';

/**
 * Lifecycle hooks.
 */
register_activation_hook( __FILE__, array( 'FPP_Interlinking_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FPP_Interlinking_Deactivator', 'deactivate' ) );

/**
 * Check DB schema version on every load and run migrations when needed.
 *
 * Uses dbDelta() which is safe to re-run – it only applies incremental changes.
 *
 * @since 1.0.0
 */
function fpp_interlinking_check_db_version() {
	$installed_version = get_option( 'fpp_interlinking_db_version', '0' );
	if ( version_compare( $installed_version, FPP_INTERLINKING_DB_VERSION, '<' ) ) {
		FPP_Interlinking_Activator::activate();
		update_option( 'fpp_interlinking_db_version', FPP_INTERLINKING_DB_VERSION, false );
	}
}
add_action( 'plugins_loaded', 'fpp_interlinking_check_db_version' );

/**
 * Add a "Settings" link on the Plugins list page.
 *
 * @since 1.1.0
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function fpp_interlinking_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=fpp-interlinking' ) ),
		esc_html__( 'Settings', 'fpp-interlinking' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . FPP_INTERLINKING_PLUGIN_BASENAME, 'fpp_interlinking_plugin_action_links' );

/**
 * Initialize admin functionality.
 */
if ( is_admin() ) {
	new FPP_Interlinking_Admin();
}

/**
 * Initialize front-end content replacement.
 */
if ( ! is_admin() ) {
	new FPP_Interlinking_Replacer();
}
