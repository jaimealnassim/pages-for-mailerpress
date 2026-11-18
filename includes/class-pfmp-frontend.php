<?php
/**
 * Front-end output for MailerPress pages.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

/**
 * Everything the reader sees: scoped styles, the branded header, the language
 * switcher, and the optional unsubscribe off-ramp. All of it layers on top of
 * MailerPress's existing output.
 *
 * MailerPress 2.0.7 and later pass the [mailerpress_pages] output through the
 * `mailerpress_pages_rendered_content` filter, so that's where this plugin
 * wraps it: it works the same in page builders, on MailerPress's own default
 * page, and in any theme. Older MailerPress versions fall back to the_content.
 */
final class PFMP_Frontend {

    private bool $wrapped       = false;
    private bool $assets_loaded = false;

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        // Priority 5 runs before MailerPress Pro's own design (priority 10),
        // so in translate-only mode Pro's card ends up around our switcher.
        add_filter('mailerpress_pages_rendered_content', array($this, 'wrap_rendered'), 5, 2);
        add_filter('the_content', array($this, 'wrap'), 20);
        add_filter('body_class', array($this, 'body_class'));
        // Full-page mode.
        add_filter('template_include', array($this, 'takeover_template'), 99);
        add_action('wp_enqueue_scripts', array($this, 'strip_theme_assets'), 9999);
    }

    /**
     * How this plugin behaves on the current request:
     *  - 'full'      our card, colors, branding, switcher and off-ramp
     *  - 'translate' MailerPress Pro's own design is on, so only the switcher
     *  - 'off'       disabled, or inside Pro's style editor preview
     */
    public static function mode(): string {
        $o = pfmp_get_settings();
        if (empty($o['enabled']) || pfmp_is_pro_style_editor_request()) {
            return 'off';
        }
        if (pfmp_pro_design_enabled()) {
            return !empty($o['lang_switcher']) ? 'translate' : 'off';
        }
        return 'full';
    }

    /**
     * MailerPress 2.0.7 added the rendered-content filter. On older versions
     * the_content is the only place to wrap the page. The `pfmp_legacy_wrap`
     * filter can force either path.
     */
    private static function use_legacy_wrap(): bool {
        $legacy = !defined('MAILERPRESS_VERSION') || version_compare(MAILERPRESS_VERSION, '2.0.7', '<');
        return (bool) apply_filters('pfmp_legacy_wrap', $legacy);
    }

    private function active(): bool {
        return self::mode() !== 'off' && pfmp_is_mailerpress_page();
    }

    private function takeover_on(): bool {
        return self::is_takeover();
    }

    /**
     * Is this request a MailerPress page shown in full-page mode?
     */
    public static function is_takeover(): bool {
        return self::mode() === 'full' && !empty(pfmp_get_settings()['takeover']) && pfmp_is_mailerpress_page();
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public function body_class($classes): array {
        $classes = (array) $classes;
        if ($this->active()) {
            $classes[] = 'pfmp-on';
        }
        if ($this->takeover_on()) {
            $classes[] = 'pfmp-takeover';
        }
        return $classes;
    }

    /**
     * In full-page mode, render our own bare template (no theme header/footer).
     *
     * @param string $template
     */
    public function takeover_template($template): string {
        $template = (string) $template;
        if ($this->takeover_on()) {
            $custom = PFMP_DIR . 'templates/takeover.php';
            if (file_exists($custom)) {
                return $custom;
            }
        }
        return $template;
    }

    /**
     * In full-page mode, dequeue the active theme's stylesheets (and the
     * theme.json global styles) so no theme CSS reaches the page. Plugin styles,
     * including MailerPress's and ours, are left untouched.
     */
    public function strip_theme_assets(): void {
        if (!$this->takeover_on()) {
            return;
        }
        global $wp_styles;
        if (!($wp_styles instanceof WP_Styles)) {
            return;
        }

        $theme_uris = array_filter(array(
            get_stylesheet_directory_uri(),
            get_template_directory_uri(),
        ));
        $always = array('global-styles', 'classic-theme-styles', 'wp-block-library-theme');

        foreach ($wp_styles->registered as $handle => $style) {
            if (in_array($handle, $always, true)) {
                wp_dequeue_style($handle);
                continue;
            }
            $src = is_string($style->src) ? $style->src : '';
            if ($src === '') {
                continue;
            }
            foreach ($theme_uris as $uri) {
                if (str_starts_with($src, $uri)) {
                    wp_dequeue_style($handle);
                    break;
                }
            }
        }
    }

    /**
     * Load assets in <head> when we can already tell this is a MailerPress
     * page. Pages built with a page builder keep the shortcode outside
     * post_content, so wrap_rendered() also loads them late (in the footer)
     * as a safety net.
     */
    public function assets(): void {
        if ($this->active()) {
            $this->load_assets();
        }
    }

    private function load_assets(): void {
        if ($this->assets_loaded) {
            return;
        }
        $this->assets_loaded = true;

        $o = pfmp_get_settings();

        self::enqueue_mailerpress_css();
        wp_enqueue_style('pfmp-frontend', PFMP_URL . 'assets/css/pfmp-frontend.css', array(), PFMP_VERSION);

        if (self::mode() === 'full') {
            wp_add_inline_style('pfmp-frontend', self::palette_css($o) . self::style_css($o['style']) . self::custom_css($o));
        }

        wp_enqueue_script('pfmp-frontend', PFMP_URL . 'assets/js/pfmp-frontend.js', array(), PFMP_VERSION, array('in_footer' => true));
        wp_add_inline_script('pfmp-frontend', 'window.PFMP = ' . wp_json_encode(self::js_config($o)) . ';', 'before');
    }

    /**
     * MailerPress 2.2 styles its subscription list cards in its own
     * stylesheet, which it only loads when the shortcode sits in the post
     * content. Load it whenever we wrap a page too. Same handle MailerPress
     * uses, so it never loads twice.
     */
    public static function enqueue_mailerpress_css(): void {
        if (!defined('MAILERPRESS_PLUGIN_DIR_PATH') || !defined('MAILERPRESS_PLUGIN_DIR_URL')) {
            return;
        }
        if (!file_exists(MAILERPRESS_PLUGIN_DIR_PATH . 'build/public/shortcode.css')) {
            return;
        }
        wp_enqueue_style(
            'mailerpress-shortcode-css',
            MAILERPRESS_PLUGIN_DIR_URL . 'build/public/shortcode.css',
            array(),
            defined('MAILERPRESS_VERSION') ? MAILERPRESS_VERSION : false
        );
    }

    /**
     * The color variables for one palette, as CSS declarations.
     *
     * @param array<string, string> $p
     */
    private static function palette_vars(array $p): string {
        return sprintf(
            '--pfmp-ink:%1$s;--pfmp-button-text:%2$s;--pfmp-canvas:%3$s;--pfmp-paper:%4$s;--pfmp-text:%5$s;--pfmp-soft:%6$s;--pfmp-muted:%6$s;--pfmp-line:%7$s;--pfmp-line-2:%7$s;',
            $p['accent'],
            $p['buttonText'],
            $p['background'],
            $p['surface'],
            $p['text'],
            $p['muted'],
            $p['border']
        );
    }

    /**
     * Inline palette CSS: the light palette on .pfmp-wrap, the dark one for
     * dark mode (always on, or following the reader's device), and the page
     * background on :root for full-page mode, where <body> needs it.
     * Every value comes from PFMP_Palette, which only returns hex colors.
     *
     * @param array<string, mixed> $o
     */
    public static function palette_css(array $o): string {
        $light = PFMP_Palette::resolve($o['palette']['accent'], $o['palette']['overrides']);
        $dark  = PFMP_Palette::resolve_dark($o['palette']['accent']);
        $mode  = (string) ($o['dark_mode'] ?? 'off');

        $css  = ':root{--pfmp-canvas:' . $light['background'] . ';}';
        $css .= '.pfmp-wrap{' . self::palette_vars($light) . '}';
        $css .= '.pfmp-wrap.pfmp-dark{' . self::palette_vars($dark) . '}';
        if ($mode === 'on') {
            $css .= ':root{--pfmp-canvas:' . $dark['background'] . ';}';
        } elseif ($mode === 'auto') {
            $css .= '@media (prefers-color-scheme: dark){:root{--pfmp-canvas:' . $dark['background'] . ';}.pfmp-wrap.pfmp-dark-auto{' . self::palette_vars($dark) . '}}';
        }
        return $css;
    }

    /**
     * @param array<string, mixed> $o
     */
    public static function custom_css(array $o): string {
        return !empty($o['custom_css']) ? "\n" . wp_strip_all_tags((string) $o['custom_css']) : '';
    }

    /**
     * Card radius, shadow, and background image, as :root variables since
     * the background image needs to reach <body> in full-page mode.
     *
     * @param array<string, mixed> $style
     */
    public static function style_css(array $style): string {
        $shadows = pfmp_shadow_presets();
        // Always one of our own fixed presets, never raw input.
        $shadow = $shadows[$style['shadow']]['css'] ?? $shadows['soft']['css'];

        $css = sprintf(':root{--pfmp-radius:%1$dpx;--pfmp-shadow:%2$s;}', (int) $style['radius'], $shadow);

        $bg = $style['background_image'];
        if (!empty($bg['url']) && wp_http_validate_url($bg['url'])) {
            $css .= sprintf(
                ':root{--pfmp-bg-image:url(%1$s);--pfmp-bg-repeat:%2$s;--pfmp-bg-size:%3$s;}',
                // esc_url() would turn & into &#038;, which is HTML, not CSS.
                wp_json_encode(esc_url_raw($bg['url']), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
                !empty($bg['repeat']) ? 'repeat' : 'no-repeat',
                !empty($bg['cover']) ? 'cover' : 'auto'
            );
        }
        return $css;
    }

    /**
     * Settings for assets/js/pfmp-frontend.js. Only the dictionaries for the
     * languages this site offers are sent.
     *
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    public static function js_config(array $o): array {
        $i18n    = !empty($o['lang_switcher']);
        $details = array();
        foreach ((array) $o['list_details'] as $id => $row) {
            $details[(string) $id] = array('desc' => (string) ($row['desc'] ?? ''), 'when' => (string) ($row['when'] ?? ''));
        }
        return array(
            'i18n'        => $i18n,
            'languages'   => array_values((array) $o['languages']),
            'defaultLang' => $o['default_lang'],
            'siteLocale'  => substr(determine_locale(), 0, 2),
            'allowUrl'    => !empty($o['allow_url_lang']),
            'rtl'         => pfmp_rtl_languages(),
            'dict'        => $i18n ? pfmp_dictionary_for($o['languages']) : new stdClass(),
            'lists'       => empty($details) ? new stdClass() : $details,
            'track'       => empty($o['insights']) ? '' : admin_url('admin-ajax.php'),
            'trackAction' => PFMP_Insights::ACTION,
        );
    }

    /**
     * MailerPress 2.0.7+: wrap exactly the [mailerpress_pages] output.
     *
     * @param string $content
     * @param mixed  $context Array with 'action', 'atts', 'is_preview'.
     */
    public function wrap_rendered($content, $context = array()): string {
        $content = (string) $content;
        $context = is_array($context) ? $context : array();
        if ($this->wrapped || trim($content) === '') {
            return $content;
        }
        $mode = self::mode();
        if ($mode === 'off') {
            return $content;
        }

        $action = is_string($context['action'] ?? null) ? sanitize_key($context['action']) : pfmp_current_action();
        $this->wrapped = true;
        $this->load_assets();

        return $mode === 'full'
            ? self::render_wrapped($content, $action)
            : self::render_translate_only($content);
    }

    /**
     * Older MailerPress: wrap the whole post content, inside the main loop.
     *
     * @param string $content
     */
    public function wrap($content): string {
        $content = (string) $content;
        if ($this->wrapped || !self::use_legacy_wrap() || !$this->active() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $this->wrapped = true;
        return self::mode() === 'full'
            ? self::render_wrapped($content)
            : self::render_translate_only($content);
    }

    /**
     * The whole card: header with brand and language control, the page's
     * headline, MailerPress's own content, the extras for that page, and the
     * footer. Shared by the real pages and the settings screen's preview.
     */
    public static function render_wrapped(string $content, ?string $action = null, bool $preview = false): string {
        $o      = pfmp_get_settings();
        $action = $action ?? pfmp_current_action();
        $state  = $action !== '' ? ' pfmp-state-' . sanitize_html_class($action) : '';
        $layout = $o['layout'] === 'left' ? 'pfmp-left' : 'pfmp-centered';
        $dark   = match ((string) $o['dark_mode']) {
            'on'    => ' pfmp-dark',
            'auto'  => ' pfmp-dark-auto',
            default => '',
        };
        $buttons = !empty($o['lang_switcher']) && $o['switcher_style'] === 'buttons' ? self::langbar($o) : '';
        $tag     = !$preview && self::is_takeover() ? 'h1' : 'h2';

        return '<div class="pfmp-wrap ' . esc_attr($layout . $state . $dark) . '" dir="ltr" data-pfmp-i18n data-pfmp-state="' . esc_attr($action) . '">'
             . self::header($o)
             . $buttons
             . '<div class="pfmp-body">'
             . self::headline($o, $action, $tag)
             . '<div class="pfmp-content">' . $content . '</div>'
             . self::extras($o, $action, $preview)
             . '</div>'
             . self::footer($o, $preview)
             . '</div>';
    }

    /**
     * MailerPress Pro's design is on: add only the language control, and let
     * Pro's own card wrap everything.
     */
    public static function render_translate_only(string $content): string {
        $o = pfmp_get_settings();
        if (empty($o['lang_switcher'])) {
            return $content;
        }
        $control = $o['switcher_style'] === 'buttons' ? self::langbar($o) : '<div class="pfmp-translate-bar">' . self::langmenu($o) . '</div>';
        return '<div class="pfmp-translate" dir="ltr" data-pfmp-i18n>' . $control . $content . '</div>';
    }

    /**
     * Brand on one side, the language menu on the other.
     *
     * @param array<string, mixed> $o
     */
    public static function header(array $o): string {
        if (!empty($o['logo_url'])) {
            $mark = '<img class="pfmp-logo" src="' . esc_url($o['logo_url']) . '" alt="' . esc_attr($o['pub_name']) . '">';
        } else {
            $mark = '<span class="pfmp-mark" aria-hidden="true">' . esc_html(pfmp_first_char($o['pub_name'])) . '</span>';
        }

        // Name and tagline are always rendered, even empty, so the settings
        // preview has stable elements to update; CSS hides them when empty.
        $brand = '<div class="pfmp-brand">' . $mark . '<div class="pfmp-brand-text">'
               . '<div class="pfmp-pub">' . esc_html($o['pub_name']) . '</div>'
               . '<div class="pfmp-tag">' . esc_html($o['tagline']) . '</div>'
               . '</div></div>';

        $menu = !empty($o['lang_switcher']) && $o['switcher_style'] !== 'buttons' ? self::langmenu($o) : '';

        return '<div class="pfmp-head">' . $brand . $menu . '</div>';
    }

    /**
     * The compact language menu: a globe button that opens a list. Scales to
     * all 30 languages, unlike a row of buttons.
     *
     * @param array<string, mixed> $o
     */
    public static function langmenu(array $o): string {
        $supported = pfmp_supported_languages();
        $options   = '';
        foreach ((array) $o['languages'] as $code) {
            if (!isset($supported[$code])) {
                continue;
            }
            $options .= sprintf(
                '<li role="presentation"><button type="button" class="pfmp-lang" role="option" aria-selected="false" data-lang="%1$s" lang="%1$s">%2$s</button></li>',
                esc_attr($code),
                esc_html($supported[$code]['native'])
            );
        }
        if ($options === '') {
            return '';
        }
        $globe = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/></svg>';
        $caret = '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>';

        return '<div class="pfmp-langmenu">'
             . '<button type="button" class="pfmp-langbtn" aria-haspopup="listbox" aria-expanded="false" aria-label="' . esc_attr__('Language', 'pages-for-mailerpress') . '">'
             . $globe . '<span data-pfmp-lang-current>' . esc_html($supported['en']['native']) . '</span>' . $caret
             . '</button>'
             . '<ul class="pfmp-langlist" role="listbox" hidden>' . $options . '</ul>'
             . '</div>';
    }

    /**
     * The row-of-buttons language switcher.
     *
     * @param array<string, mixed> $o
     */
    public static function langbar(array $o): string {
        $supported = pfmp_supported_languages();
        $buttons   = '';
        foreach ((array) $o['languages'] as $code) {
            if (!isset($supported[$code])) {
                continue;
            }
            $buttons .= sprintf(
                '<button type="button" class="pfmp-lang" data-lang="%1$s" lang="%1$s" aria-pressed="false">%2$s</button>',
                esc_attr($code),
                esc_html($supported[$code]['native'])
            );
        }
        if ($buttons === '') {
            return '';
        }
        return '<div class="pfmp-langbar" role="group" aria-label="' . esc_attr__('Language', 'pages-for-mailerpress') . '">' . $buttons . '</div>';
    }

    /**
     * The page's headline. The built-in ones are dictionary keys, so they
     * translate on the page; a custom one is shown as written.
     *
     * @param array<string, mixed> $o
     */
    public static function headline(array $o, string $action, string $tag = 'h2'): string {
        $defaults = pfmp_headline_defaults();
        if (!isset($defaults[$action])) {
            return '';
        }
        $custom = trim((string) ($o['headlines'][$action] ?? ''));
        $class  = $custom !== '' ? 'pfmp-title pfmp-custom' : 'pfmp-title';
        $tag    = $tag === 'h1' ? 'h1' : 'h2';
        return sprintf('<%1$s class="%2$s">%3$s</%1$s>', $tag, $class, esc_html($custom !== '' ? $custom : $defaults[$action]));
    }

    /**
     * The extras below MailerPress's content on each page. Links that need
     * the reader's access token start hidden on real pages; the script adds
     * the token from the address bar and only then shows them, so the token
     * is never written into the HTML (or into a cached copy of it).
     *
     * The English text below is dictionary keys for the reader-side
     * translation, so it is deliberately not passed through __().
     *
     * @param array<string, mixed> $o
     */
    public static function extras(array $o, string $action, bool $preview = false): string {
        $needs_cid = $preview ? '' : ' data-pfmp-needs-cid hidden';
        $manage    = $preview ? '#' : pfmp_manage_url();
        $archive   = (string) $o['archive_url'];
        $html      = '';

        switch ($action) {
            case 'confirm_unsubscribe':
                if ($manage !== '') {
                    $html .= '<a class="pfmp-option" href="' . esc_url($manage) . '" data-pfmp-carry-cid data-pfmp-track="fewer" data-pfmp-feature="offramp"' . $needs_cid . (empty($o['offramp']) ? ' style="display:none"' : '') . '>'
                           . '<span class="pfmp-option-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg></span>'
                           . '<span class="pfmp-option-text"><span class="pfmp-option-title">' . esc_html('Get fewer emails instead') . '</span>'
                           . '<span class="pfmp-option-sub">' . esc_html('Keep only the newsletters you want.') . '</span></span>'
                           . '<svg class="pfmp-option-arrow" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg>'
                           . '</a>';
                }
                if (!empty($o['reasons']) || $preview) {
                    $chips = '';
                    foreach (pfmp_reason_options() as $key => $label) {
                        $chips .= '<button type="button" class="pfmp-chip" aria-pressed="false" data-pfmp-reason="' . esc_attr($key) . '">' . esc_html($label) . '</button>';
                    }
                    $html .= '<fieldset class="pfmp-reasons" data-pfmp-feature="reasons"' . (empty($o['reasons']) ? ' style="display:none"' : '') . '>'
                           . '<legend><span>' . esc_html('Why are you leaving?') . '</span> <span class="pfmp-optional">' . esc_html('(optional)') . '</span></legend>'
                           . '<div class="pfmp-chips">' . $chips . '</div>'
                           . '<p class="pfmp-thanks" role="status" hidden>' . esc_html('Thanks for telling us.') . '</p>'
                           . '</fieldset>';
                }
                $html .= '<div class="pfmp-actions" data-pfmp-actions>'
                       . '<a class="pfmp-btn pfmp-btn-ghost" href="' . esc_url($preview ? '#' : home_url('/')) . '" data-pfmp-track="keep">' . esc_html('Keep my subscription') . '</a>'
                       . '</div>';
                break;

            case 'unsubscribe':
                $boxes = '';
                if ((!empty($o['undo']) || $preview) && $manage !== '') {
                    $boxes .= '<div class="pfmp-box" data-pfmp-feature="undo"' . $needs_cid . (empty($o['undo']) ? ' style="display:none"' : '') . '>'
                            . '<p class="pfmp-box-title">' . esc_html('Unsubscribed by mistake?') . '</p>'
                            . '<a class="pfmp-btn pfmp-btn-outline" href="' . esc_url($manage) . '" data-pfmp-carry-cid data-pfmp-track="resubscribe">' . esc_html('Resubscribe') . '</a>'
                            . '</div>';
                }
                if ($archive !== '' || $preview) {
                    $boxes .= '<div class="pfmp-box pfmp-box-soft" data-pfmp-feature="archive"' . ($archive === '' ? ' style="display:none"' : '') . '>'
                            . '<p class="pfmp-box-title">' . esc_html('Still want to read us?') . '</p>'
                            . '<p class="pfmp-box-text">' . esc_html('Every issue is on the website, no inbox needed.') . '</p>'
                            . '<a class="pfmp-link" href="' . esc_url($archive !== '' ? $archive : '#') . '" data-pfmp-archive>' . esc_html('Read the latest issue') . '</a>'
                            . '</div>';
                }
                if ($boxes !== '') {
                    $html .= '<div class="pfmp-boxes">' . $boxes . '</div>';
                }
                break;

            case 'confirm':
            case 'reengagement_confirm':
                $links = '';
                if ($archive !== '' || $preview) {
                    $links .= '<a class="pfmp-btn pfmp-btn-primary" href="' . esc_url($archive !== '' ? $archive : '#') . '" data-pfmp-feature="archive" data-pfmp-archive' . ($archive === '' ? ' style="display:none"' : '') . '>' . esc_html('Read the latest issue') . '</a>';
                }
                if ($manage !== '') {
                    $links .= '<a class="pfmp-link" href="' . esc_url($manage) . '" data-pfmp-carry-cid' . $needs_cid . '>' . esc_html('Manage your subscription') . '</a>';
                }
                if ($links !== '') {
                    $html .= '<div class="pfmp-cta">' . $links . '</div>';
                }
                break;
        }

        return $html;
    }

    /**
     * Privacy and contact links at the foot of the card. In the settings
     * preview both links always exist (hidden when unset) so they can be
     * shown live as the fields are filled in.
     *
     * @param array<string, mixed> $o
     */
    public static function footer(array $o, bool $preview = false): string {
        $items = array(
            'privacy' => array(pfmp_privacy_url(), 'Privacy policy'),
            'contact' => array(pfmp_contact_url(), 'Contact us'),
        );
        $links = array();
        foreach ($items as $key => [$url, $label]) {
            if ($url === '' && !$preview) {
                continue;
            }
            $links[] = '<a href="' . esc_url($url !== '' ? $url : '#') . '" data-pfmp-foot="' . esc_attr($key) . '"' . ($url === '' ? ' hidden' : '') . '>' . esc_html($label) . '</a>';
        }
        $visible = !empty($o['footer']['show']) && ($preview || !empty($links));
        if (!$visible && !$preview) {
            return '';
        }
        return '<p class="pfmp-foot"' . ($visible ? '' : ' hidden') . '>' . implode('', $links) . '</p>';
    }
}
