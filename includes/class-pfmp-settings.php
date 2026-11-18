<?php
/**
 * Option registration and sanitization.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

/**
 * Registers the one option this plugin stores and cleans every field before
 * it is saved. The field markup lives in PFMP_Builder, the settings screen.
 */
final class PFMP_Settings {

    public const GROUP = 'pfmp_settings_group';

    public function __construct() {
        add_action('admin_init', array($this, 'register'));
    }

    public function register(): void {
        register_setting(self::GROUP, PFMP_OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => pfmp_defaults(),
            'show_in_rest'      => false,
        ));
    }

    /**
     * Validate and clean every field before it is stored. Every value is
     * type-checked first, so a crafted request (an array where a string is
     * expected, say) is dropped instead of causing a fatal error.
     *
     * @param mixed $input Raw submitted value.
     * @return array<string, mixed>
     */
    public function sanitize($input): array {
        $input = is_array($input) ? $input : array();

        // Import: settings pasted as JSON replace the form's values.
        $import = trim(self::str($input['import_json'] ?? ''));
        if ($import !== '') {
            $decoded = json_decode(wp_unslash($import), true);
            if (is_array($decoded)) {
                unset($decoded['import_json'], $decoded['reset_insights']);
                add_settings_error(PFMP_OPTION, 'pfmp_imported', __('Settings imported.', 'pages-for-mailerpress'), 'success');
                return $this->sanitize($decoded);
            }
            add_settings_error(PFMP_OPTION, 'pfmp_import_failed', __('The imported settings were not valid JSON, so nothing was imported.', 'pages-for-mailerpress'));
        }

        if (!empty($input['reset_insights'])) {
            PFMP_Insights::reset();
        }

        $current = pfmp_get_settings();
        $d       = pfmp_defaults();
        $out     = array();

        $out['enabled']  = self::flag($input, 'enabled');
        $out['layout']   = self::str($input['layout'] ?? '') === 'left' ? 'left' : 'centered';
        $out['offramp']  = self::flag($input, 'offramp');
        $out['takeover'] = self::flag($input, 'takeover');

        $out['logo_id']  = absint(self::str($input['logo_id'] ?? ''));
        $out['logo_url'] = esc_url_raw(trim(self::str($input['logo_url'] ?? '')));
        $out['pub_name'] = array_key_exists('pub_name', $input) ? sanitize_text_field(self::str($input['pub_name'])) : $current['pub_name'];
        $out['tagline']  = sanitize_text_field(self::str($input['tagline'] ?? ''));
        $out['preset']   = 'custom';

        $palette_in     = is_array($input['palette'] ?? null) ? $input['palette'] : array();
        $overrides_in   = is_array($palette_in['overrides'] ?? null) ? $palette_in['overrides'] : array();
        $out['palette'] = array(
            'accent'    => sanitize_hex_color(self::str($palette_in['accent'] ?? '')) ?: $d['palette']['accent'],
            'overrides' => array(),
        );
        foreach (array_keys(PFMP_Palette::tokens()) as $token) {
            $out['palette']['overrides'][$token] = sanitize_hex_color(self::str($overrides_in[$token] ?? '')) ?: '';
        }
        $out['dark_mode'] = self::choice($input['dark_mode'] ?? '', array('off', 'auto', 'on'), 'off');

        $style_in = is_array($input['style'] ?? null) ? $input['style'] : array();
        $bg_in    = is_array($style_in['background_image'] ?? null) ? $style_in['background_image'] : array();
        $radius   = self::str($style_in['radius'] ?? '');
        $out['style'] = array(
            'radius'           => $radius === '' ? $d['style']['radius'] : max(0, min(32, absint($radius))),
            'shadow'           => self::choice($style_in['shadow'] ?? '', array_keys(pfmp_shadow_presets()), $d['style']['shadow']),
            'background_image' => array(
                'id'     => absint(self::str($bg_in['id'] ?? '')),
                'url'    => esc_url_raw(trim(self::str($bg_in['url'] ?? ''))),
                'repeat' => self::flag($bg_in, 'repeat'),
                'cover'  => self::flag($bg_in, 'cover'),
            ),
        );

        $supported = array_keys(pfmp_supported_languages());
        $langs     = is_array($input['languages'] ?? null) ? array_map(static fn(mixed $v): string => self::str($v), $input['languages']) : array();
        $langs     = array_values(array_intersect($supported, array_map('sanitize_key', $langs)));
        if (!in_array('en', $langs, true)) {
            array_unshift($langs, 'en'); // English is always available as the base.
        }
        $out['lang_switcher']  = self::flag($input, 'lang_switcher');
        $out['switcher_style'] = self::choice($input['switcher_style'] ?? '', array('menu', 'buttons'), 'menu');
        $out['languages']      = $langs;
        $out['default_lang']   = self::choice($input['default_lang'] ?? '', array_merge(array('locale'), $supported), 'locale');
        $out['allow_url_lang'] = self::flag($input, 'allow_url_lang');

        $headlines_in     = is_array($input['headlines'] ?? null) ? $input['headlines'] : array();
        $out['headlines'] = array();
        foreach (array_keys(pfmp_headline_defaults()) as $state) {
            $out['headlines'][$state] = mb_substr(sanitize_text_field(self::str($headlines_in[$state] ?? '')), 0, 120);
        }

        $out['list_details'] = array();
        $details_in          = is_array($input['list_details'] ?? null) ? $input['list_details'] : array();
        foreach ($details_in as $id => $row) {
            $id = absint($id);
            if ($id === 0 || !is_array($row)) {
                continue;
            }
            $desc = mb_substr(sanitize_text_field(self::str($row['desc'] ?? '')), 0, 160);
            $when = mb_substr(sanitize_text_field(self::str($row['when'] ?? '')), 0, 30);
            if ($desc !== '' || $when !== '') {
                $out['list_details'][$id] = array('desc' => $desc, 'when' => $when);
            }
        }

        $footer_in = is_array($input['footer'] ?? null) ? $input['footer'] : array();
        $contact   = trim(self::str($footer_in['contact'] ?? ''));
        $out['footer'] = array(
            'show'    => self::flag($footer_in, 'show'),
            'privacy' => esc_url_raw(trim(self::str($footer_in['privacy'] ?? ''))),
            'contact' => is_email($contact) ? sanitize_email($contact) : esc_url_raw($contact),
        );

        $out['reasons']     = self::flag($input, 'reasons');
        $out['undo']        = self::flag($input, 'undo');
        $out['archive_url'] = esc_url_raw(trim(self::str($input['archive_url'] ?? '')));
        $out['insights']    = self::flag($input, 'insights');

        // Custom CSS is printed in a <style> block on public pages. Only
        // users who may post unfiltered HTML can change it; for everyone
        // else (on multisite, site admins) the saved value is kept.
        $out['custom_css'] = current_user_can('unfiltered_html')
            ? wp_strip_all_tags(self::str($input['custom_css'] ?? ''))
            : (string) $current['custom_css'];
        $out['uninstall_cleanup'] = self::flag($input, 'uninstall_cleanup');

        return $out;
    }

    /**
     * A submitted scalar as a string; anything else (arrays, objects) as ''.
     */
    private static function str(mixed $value): string {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A checkbox value as 0 or 1.
     *
     * @param array<string|int, mixed> $input
     */
    private static function flag(array $input, string $key): int {
        return empty($input[$key]) ? 0 : 1;
    }

    /**
     * One of a fixed set of values, else the fallback.
     *
     * @param string[] $allowed
     */
    private static function choice(mixed $value, array $allowed, string $fallback): string {
        $value = sanitize_key(self::str($value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
