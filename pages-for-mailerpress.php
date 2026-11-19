<?php
/**
 * Plugin Name:       Pages for MailerPress
 * Plugin URI:        https://github.com/jaimealnassim/pages-for-mailerpress
 * Description:       Restyles and translates the MailerPress subscription, confirmation, and unsubscribe pages: logo, centered layout, and an on-page 30-language switcher. Rides on the classes MailerPress already outputs; no core files touched.
 * Version:           0.9.6
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

define('PFMP_VERSION', '0.9.6');
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
 * True once MailerPress is active. MailerPress Pro is not needed; when it is
 * active and its own page design is on, the plugin adds only the language
 * control (see PFMP_Frontend::mode()).
 */
function pfmp_dependencies_met(): bool {
    return pfmp_has_mailerpress();
}

/**
 * Boot once WordPress and MailerPress are loaded. Does nothing otherwise;
 * see pfmp_dependency_notice().
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
 * Explains what's missing when MailerPress isn't active.
 */
function pfmp_dependency_notice(): void {
    if (!current_user_can('activate_plugins') || pfmp_dependencies_met()) {
        return;
    }
    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__('Pages for MailerPress is active, but MailerPress was not detected. Install and activate MailerPress for this plugin to have any effect.', 'pages-for-mailerpress')
    );
}
add_action('admin_notices', 'pfmp_dependency_notice');

/**
 * When MailerPress is missing, the settings screen still exists and explains
 * why, instead of WordPress's "Sorry, you are not allowed to access this
 * page." for a saved link or bookmark.
 */
function pfmp_missing_dependency_page(): void {
    if (pfmp_dependencies_met()) {
        return;
    }
    $title = __('Pages for MailerPress', 'pages-for-mailerpress');
    add_options_page($title, $title, 'manage_options', 'pages-for-mailerpress', static function () use ($title): void {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html__('MailerPress was not detected. Install and activate MailerPress to use this plugin.', 'pages-for-mailerpress') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('plugins.php')) . '">' . esc_html__('Go to Plugins', 'pages-for-mailerpress') . '</a></p></div>';
    });
}
add_action('admin_menu', 'pfmp_missing_dependency_page', 99);
