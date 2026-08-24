<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * administrative area. This file also includes all of the plugin dependencies.
 *
 * @link              http://example.com
 * @since             1.3.9
 * @package           WP_Race_Results
 *
 * @wordpress-plugin
 * Plugin Name:       WP Race Results
 * Plugin URI:        http://example.com/plugin-name-uri/
 * Description:       A plugin to manage race results.
 * Version:           1.3.9
 * Author:            HansTech
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-race-results
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('WPRR_VERSION', '1.3.9');

/**
 * The code that runs during plugin activation.
 */
function activate_wp_race_results()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wp-race-results-activator.php';
    WP_Race_Results_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wp_race_results()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wp-race-results-deactivator.php';
    WP_Race_Results_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wp_race_results');
register_deactivation_hook(__FILE__, 'deactivate_wp_race_results');

/**
 * Initialize Plugin Update Checker
 */
if (file_exists(plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php')) {
    require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
    $wprr_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/Hans-tech-coder/wp-plugin-race-result',
        __FILE__,
        'wp-race-results'
    );
    // Set branch to main or master if needed
    $wprr_update_checker->setBranch('main');
}

/**
 * Initialize the plugin core
 */
require_once plugin_dir_path(__FILE__) . 'includes/class-wprr-core.php';

function wprr_init()
{
    new WPRR_Core();
}

wprr_init();

