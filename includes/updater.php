<?php
/**
 * Updates from the Nahnu update server, for installs from GitHub. The slug
 * is the plugin folder name, pages-for-mailerpress. Builds for WordPress.org
 * leave this file and the class out, since WordPress.org updates plugins
 * itself.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

if (!defined('NAHNU_UPDATER_WORKER_URL')) {
    define('NAHNU_UPDATER_WORKER_URL', 'https://nahnu-updates.nahnucdn.com'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the name the Nahnu updater class reads.
}
require_once __DIR__ . '/class-nahnu-updater.php';
Nahnu_Updater::register(PFMP_FILE);
