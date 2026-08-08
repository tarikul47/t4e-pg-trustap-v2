<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://onlytarikul.com
 * @since             1.0.0
 * @package           T4e_Pg_Trustap
 *
 * @wordpress-plugin
 * Plugin Name:       Talent4energy Vendor Payment - Trustap
 * Plugin URI:        https://talent4energy.com
 * Description:       WCFM Marketplace Trustap vendor payment gateway.
 * Version:           1.0.0
 * Author:            Tarikul Islam
 * Author URI:        https://onlytarikul.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       t4e-pg-trustap
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('T4E_PG_TRUSTAP_VERSION', '1.0.0');

/**
 * Define constant 
 */
define('WCFMTrustap_TOKEN', 'wcfmtrustap'); // Unique identifier for your plugin
define('WCFMTrustap_TEXT_DOMAIN', 'wcfm-pg-trustap'); // Text domain for translations
define('WCFMTrustap_VERSION', '1.0.0'); // Current version of your plugin
define('WCFMTrustap_SERVER_URL', 'https://wclovers.com'); // Or your plugin's server URL if applicable
define('WCFMTrustap_GATEWAY', 'trustap'); // Unique slug for your payment gateway
define('WCFMTrustap_GATEWAY_LABEL', 'Trustap'); // User-friendly label for your payment gateway


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-t4e-pg-trustap-activator.php
 */
function activate_t4e_pg_trustap()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap-activator.php';
	T4e_Pg_Trustap_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-t4e-pg-trustap-deactivator.php
 */
function deactivate_t4e_pg_trustap()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap-deactivator.php';
	T4e_Pg_Trustap_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_t4e_pg_trustap');
register_deactivation_hook(__FILE__, 'deactivate_t4e_pg_trustap');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
// Moved requirements to run_t4e_pg_trustap() to ensure parent plugin autoloader is loaded.

/**
 * Summary of t4e_pg_trustap_set_testmode updated 
 * Set Global Testmode 
 * @return void
 */
function t4e_pg_trustap_set_testmode() {
    $trustap_settings = get_option('woocommerce_trustap_settings');
    $GLOBALS['testmode'] = (isset($trustap_settings['testmode']) && $trustap_settings['testmode'] === 'yes');
}
add_action('plugins_loaded', 't4e_pg_trustap_set_testmode', 0);


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function run_t4e_pg_trustap()
{
    if (!class_exists('WCFMmp')) {
        return;
    }

    require plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap-core.php';
    require plugin_dir_path(__FILE__) . 'includes/class-wcfm-trustap-api.php';
    require plugin_dir_path(__FILE__) . 'includes/class-wcfm-trustap-helper.php';
    require plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap-oauth-handler.php';
    require plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap-controller.php';
    require plugin_dir_path(__FILE__) . 'includes/class-trustap-user-manager.php';
    require plugin_dir_path(__FILE__) . 'includes/class-t4e-pg-trustap.php';

    // Register the cron handler
    add_action('t4e_pg_trustap_automated_claim_cron', array('T4e_Pg_Trustap_Core', 't4e_cron_automated_claim_check'));

    $plugin = new T4e_Pg_Trustap();
    $plugin->run();
}
add_action('plugins_loaded', 'run_t4e_pg_trustap', 20);

