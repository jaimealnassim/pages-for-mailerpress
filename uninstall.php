<?php
/**
 * Runs when the plugin is deleted from the Plugins screen. Only clears data
 * if the admin opted in under Advanced.
 *
 * @package PagesForMailerPress
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$pfmp_settings = get_option('pfmp_settings');
if (is_array($pfmp_settings) && !empty($pfmp_settings['uninstall_cleanup'])) {
    delete_option('pfmp_settings');
    delete_option('pfmp_stats');
}
