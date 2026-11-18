<?php
/**
 * Plugin Name:       Pages for MailerPress
 * Plugin URI:        https://github.com/jaimealnassim/pages-for-mailerpress
 * Description:       Restyles and translates the MailerPress subscription, confirmation, and unsubscribe pages: logo, centered layout, and an on-page 30-language switcher. Rides on the classes MailerPress already outputs; no core files touched. Requires MailerPress Pro.
 * Version:           0.9.2
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * Requires Plugins:  mailerpress
 * Author:            Nahnu Plugins
 * Author URI:        https://nahnuplugins.com
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       pages-for-mailerpress
 * Domain Path:       /languages
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

define('PFMP_VERSION', '0.9.2');
define('PFMP_FILE', __FILE__);
define('PFMP_DIR', plugin_dir_path(__FILE__));
define('PFMP_URL', plugin_dir_url(__FILE__));
define('PFMP_OPTION', 'pfmp_settings');

// Updates for GitHub installs (see includes/updater.php). WordPress.org
// builds leave that file out.
if (file_exists(PFMP_DIR . 'includes/updater.php')) {
    require_once PFMP_DIR . 'includes/updater.php';
}

require_once PFMP_DIR . 'includes/helpers.php';
require_once PFMP_DIR . 'includes/class-pfmp-palette.php';
require_once PFMP_DIR . 'includes/class-pfmp-settings.php';
require_once PFMP_DIR . 'includes/class-pfmp-builder.php';
require_once PFMP_DIR . 'includes/class-pfmp-frontend.php';
require_once PFMP_DIR . 'includes/class-pfmp-insights.php';

/**
 * Is MailerPress itself active?
 */
function pfmp_has_mailerpress(): bool {
    return function_exists('mailerpress_get_page') || defined('MAILERPRESS_VERSION');
}

/**
 * Is MailerPress Pro active?
 */
function pfmp_has_mailerpress_pro(): bool {
    return defined('MAILERPRESS_PRO_VERSION');
}

/**
 * True once both MailerPress and MailerPress Pro are active. Pro is a real
 * requirement, not a soft nudge: without it the plugin does nothing.
 *
 * The Requires Plugins header lists only MailerPress, because WordPress.org
 * plugins may only depend on plugins hosted there, and MailerPress Pro is
 * sold separately. Pro is checked here instead, with a notice explaining it.
 */
function pfmp_dependencies_met(): bool {
    return pfmp_has_mailerpress() && pfmp_has_mailerpress_pro();
}

/**
 * Boot once WordPress, MailerPress, and MailerPress Pro are all loaded. Does
 * nothing otherwise; see pfmp_dependency_notice().
 */
function pfmp_boot(): void {
    if (!pfmp_dependencies_met()) {
        return;
    }
    new PFMP_Settings();
    new PFMP_Builder();
    new PFMP_Frontend();
    new PFMP_Insights();
}
add_action('plugins_loaded', 'pfmp_boot');

/**
 * Explains what's missing when the plugin can't do anything yet.
 */
function pfmp_dependency_notice(): void {
    if (!current_user_can('activate_plugins') || pfmp_dependencies_met()) {
        return;
    }

    $message = pfmp_has_mailerpress()
        ? __('Pages for MailerPress is active, but MailerPress Pro was not detected. This plugin requires MailerPress Pro, in addition to MailerPress, to have any effect.', 'pages-for-mailerpress')
        : __('Pages for MailerPress is active, but MailerPress was not detected. Install and activate MailerPress, and MailerPress Pro, for this plugin to have any effect.', 'pages-for-mailerpress');

    printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message));
}
add_action('admin_notices', 'pfmp_dependency_notice');
