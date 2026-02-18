<?php
/**
 * Plugin Name: FPP Interlinking
 * Plugin URI:  https://example.com/fpp-interlinking
 * Description: Automatically replaces configured keywords in post/page content with anchor links to target URLs.
 * Version:     1.0.0
 * Author:      FPP
 * License:     GPL-2.0+
 * Text Domain: fpp-interlinking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FPP_INTERLINKING_VERSION', '1.0.0' );
define( 'FPP_INTERLINKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPP_INTERLINKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FPP_INTERLINKING_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-activator.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-deactivator.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-db.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-admin.php';
require_once FPP_INTERLINKING_PLUGIN_DIR . 'includes/class-fpp-interlinking-replacer.php';

register_activation_hook( __FILE__, array( 'FPP_Interlinking_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FPP_Interlinking_Deactivator', 'deactivate' ) );

/**
 * Check DB version and run upgrade if needed.
 */
function fpp_interlinking_check_db_version() {
	$installed_version = get_option( 'fpp_interlinking_db_version', '0' );
	if ( version_compare( $installed_version, FPP_INTERLINKING_VERSION, '<' ) ) {
		FPP_Interlinking_Activator::activate();
		update_option( 'fpp_interlinking_db_version', FPP_INTERLINKING_VERSION );
	}
}
add_action( 'plugins_loaded', 'fpp_interlinking_check_db_version' );

/**
 * Initialize admin functionality.
 */
if ( is_admin() ) {
	new FPP_Interlinking_Admin();
}

/**
 * Initialize front-end content replacement.
 */
new FPP_Interlinking_Replacer();
