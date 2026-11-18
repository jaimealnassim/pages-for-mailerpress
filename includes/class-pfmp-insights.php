<?php
/**
 * Anonymous counts of what readers do on the pages.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

/**
 * Records monthly totals of a few reader actions (pages viewed, reasons
 * given, "fewer emails" and "resubscribe" clicks, languages chosen). Only
 * counts are stored: no email address, IP address, cookie or other personal
 * data. The endpoint takes no nonce on purpose, because these pages are often
 * cached; every value is checked against a fixed list, and a site-wide cap
 * stops anyone from flooding it.
 */
final class PFMP_Insights {

    public const OPTION = 'pfmp_stats';
    public const ACTION = 'pfmp_track';

    /** Months of history kept. */
    private const KEEP_MONTHS = 13;

    /** Most events accepted per hour, across the whole site. */
    private const HOURLY_CAP = 1000;

    public function __construct() {
        add_action('wp_ajax_' . self::ACTION, array($this, 'handle'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array($this, 'handle'));
    }

    /**
     * Valid events and the keys each accepts.
     *
     * @return array<string, string[]>
     */
    public static function events(): array {
        return array(
            'view'        => array_keys(pfmp_builder_states()),
            'reason'      => array_keys(pfmp_reason_options()),
            'lang'        => array_keys(pfmp_supported_languages()),
            'fewer'       => array(''),
            'keep'        => array(''),
            'resubscribe' => array(''),
        );
    }

    public function handle(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- anonymous counter on cacheable pages; inputs are whitelisted below.
        $event = isset($_POST['event']) && is_string($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : '';
        $key   = isset($_POST['key']) && is_string($_POST['key']) ? sanitize_key(wp_unslash($_POST['key'])) : '';
        // phpcs:enable

        if (!self::record($event, $key)) {
            wp_send_json_error(null, 400);
        }
        wp_send_json_success();
    }

    /**
     * Add one to an event's count for this month.
     */
    public static function record(string $event, string $key = ''): bool {
        if (empty(pfmp_get_settings()['insights'])) {
            return false;
        }
        $events = self::events();
        if (!isset($events[$event]) || !in_array($key, $events[$event], true)) {
            return false;
        }

        $hour  = 'pfmp_track_' . gmdate('YmdH');
        $count = (int) get_transient($hour);
        if ($count >= self::HOURLY_CAP) {
            return false;
        }
        set_transient($hour, $count + 1, HOUR_IN_SECONDS);

        $stats = self::all();
        $month = gmdate('Y-m');
        $slot  = $key === '' ? '_' : $key;
        $stats[$month][$event][$slot] = (int) ($stats[$month][$event][$slot] ?? 0) + 1;

        ksort($stats);
        $stats = array_slice($stats, -self::KEEP_MONTHS, null, true);
        update_option(self::OPTION, $stats, false);
        return true;
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    public static function all(): array {
        $stats = get_option(self::OPTION, array());
        return is_array($stats) ? $stats : array();
    }

    /**
     * One month's counts, or an empty month.
     *
     * @return array<string, array<string, int>>
     */
    public static function month(string $month): array {
        return self::all()[$month] ?? array();
    }

    /**
     * Sum of one event in a month, across its keys (or just one key).
     */
    public static function total(string $month, string $event, ?string $key = null): int {
        $counts = self::month($month)[$event] ?? array();
        if ($key !== null) {
            return (int) ($counts[$key === '' ? '_' : $key] ?? 0);
        }
        return (int) array_sum($counts);
    }

    public static function reset(): void {
        delete_option(self::OPTION);
    }
}
