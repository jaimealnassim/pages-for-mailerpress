<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Only clears data if the admin opted in via the Advanced tab.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

$settings = get_option('pfmp_settings');
if (is_array($settings) && !empty($settings['uninstall_cleanup'])) {
    delete_option('pfmp_settings');
}
