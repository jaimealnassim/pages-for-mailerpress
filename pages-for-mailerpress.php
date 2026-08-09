<?php
/**
 * Plugin Name:       Pages for MailerPress
 * Plugin URI:        https://github.com/jaimealnassim/pages-for-mailerpress
 * Description:        Restyles and translates the MailerPress subscription, confirmation, and unsubscribe pages: logo, centered layout, and an on-page 30-language switcher. Rides on the classes MailerPress already outputs; no core files touched. Requires MailerPress Pro.
 * Version:           0.6.5
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Requires Plugins:  mailerpress,mailerpress-pro
 * Author:            Nahnu Plugins
 * Author URI:        https://nahnuplugins.com
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       pages-for-mailerpress
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

define('PFMP_VERSION', '0.6.5');
define('PFMP_FILE', __FILE__);
define('PFMP_DIR', plugin_dir_path(__FILE__));
define('PFMP_URL', plugin_dir_url(__FILE__));
define('PFMP_OPTION', 'pfmp_settings');

require_once PFMP_DIR . 'includes/helpers.php';
require_once PFMP_DIR . 'includes/class-pfmp-palette.php';
require_once PFMP_DIR . 'includes/class-pfmp-settings.php';
require_once PFMP_DIR . 'includes/class-pfmp-builder.php';
require_once PFMP_DIR . 'includes/class-pfmp-frontend.php';

/**
 * True once both MailerPress and MailerPress Pro are active. Pro is a
 * genuine requirement, not a soft nudge: this plugin's Builder previews and
 * styles pages the same way regardless of which MailerPress tier is
 * running, but we only want it doing that on sites that are actually
 * licensed for Pro, so it simply does nothing without it.
 */
function pfmp_dependencies_met() {
    $has_mailerpress = function_exists('mailerpress_get_page') || defined('MAILERPRESS_VERSION');
    $has_pro         = defined('MAILERPRESS_PRO_VERSION');
    return $has_mailerpress && $has_pro;
}

/**
 * Boot the plugin once WordPress, MailerPress, and MailerPress Pro are all
 * loaded. Does nothing at all otherwise; see pfmp_dependency_notice().
 */
function pfmp_boot() {
    if (!pfmp_dependencies_met()) {
        return;
    }
    $settings = new PFMP_Settings();
    new PFMP_Builder($settings);
    new PFMP_Frontend();
}
add_action('plugins_loaded', 'pfmp_boot');

/**
 * Explains what's missing when the plugin can't do anything yet: MailerPress
 * itself, MailerPress Pro, or both.
 */
function pfmp_dependency_notice() {
    if (!current_user_can('activate_plugins') || pfmp_dependencies_met()) {
        return;
    }
    $has_mailerpress = function_exists('mailerpress_get_page') || defined('MAILERPRESS_VERSION');
    $has_pro         = defined('MAILERPRESS_PRO_VERSION');

    if (!$has_mailerpress) {
        $message = __('Pages for MailerPress is active, but MailerPress was not detected. Install and activate MailerPress, and MailerPress Pro, for this plugin to have any effect.', 'pages-for-mailerpress');
    } else {
        $message = __('Pages for MailerPress is active, but MailerPress Pro was not detected. This plugin requires MailerPress Pro, in addition to MailerPress, to have any effect.', 'pages-for-mailerpress');
    }

    echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
}
add_action('admin_notices', 'pfmp_dependency_notice');
