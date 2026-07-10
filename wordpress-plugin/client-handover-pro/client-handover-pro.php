<?php
/**
 * Plugin Name:       Client Handover Pro
 * Plugin URI:        https://badhonstudio.com/client-handover-pro
 * Description:       Deliver every WordPress website like a professional agency. Run a launch checklist, generate a client-friendly dashboard, white label the admin, and export a polished handover package — all in one click.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Badhon Studio
 * Author URI:        https://badhonstudio.com
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       client-handover-pro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CHP_VERSION', '1.0.0' );
define( 'CHP_PLUGIN_FILE', __FILE__ );
define( 'CHP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CHP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the plugin.
 */
require_once CHP_PLUGIN_DIR . 'includes/class-chp-plugin.php';

/**
 * Boots the plugin once all plugins are loaded, so third-party plugin
 * detection (SEO, caching, forms, SMTP, security) inside the checklist
 * module can rely on is_plugin_active() being reliable.
 */
function chp_run_plugin() {
	return CHP_Plugin::instance();
}
add_action( 'plugins_loaded', 'chp_run_plugin' );

/**
 * Activation: seed default options and roles so the plugin is usable
 * immediately without a setup wizard.
 */
function chp_activate_plugin() {
	require_once CHP_PLUGIN_DIR . 'includes/class-chp-activator.php';
	CHP_Activator::activate();
}
register_activation_hook( __FILE__, 'chp_activate_plugin' );

/**
 * Deactivation: clear scheduled events only. Data is preserved so the
 * user can reactivate without losing checklist history or vault data.
 */
function chp_deactivate_plugin() {
	require_once CHP_PLUGIN_DIR . 'includes/class-chp-activator.php';
	CHP_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'chp_deactivate_plugin' );
