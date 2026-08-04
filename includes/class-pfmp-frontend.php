<?php
defined('ABSPATH') || exit;

/**
 * Everything the reader sees: scoped styles, the branded header, the language
 * switcher, and the optional unsubscribe off-ramp. All of it layers on top of
 * MailerPress's existing output.
 */
class PFMP_Frontend {

    private $wrapped = false;

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'assets'));
        add_filter('the_content', array($this, 'wrap'), 20);
        add_filter('body_class', array($this, 'body_class'));
        // Full-page mode.
        add_filter('template_include', array($this, 'takeover_template'), 99);
        add_action('wp_enqueue_scripts', array($this, 'strip_theme_assets'), 9999);
    }

    private function active() {
        $o = pfmp_get_settings();
        if (!pfmp_is_mailerpress_page()) {
            return false;
        }
        return !empty($o['enabled']);
    }

    private function takeover_on() {
        $o = pfmp_get_settings();
        return $this->active() && !empty($o['takeover']);
    }

    public function body_class($classes) {
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
     */
    public function takeover_template($template) {
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
    public function strip_theme_assets() {
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
            $src = isset($style->src) ? (string) $style->src : '';
            if ($src === '') {
                continue;
            }
            foreach ($theme_uris as $uri) {
                if (strpos($src, $uri) === 0) {
                    wp_dequeue_style($handle);
                    break;
                }
            }
        }
    }

    public function assets() {
        if (!$this->active()) {
            return;
        }
        $o       = pfmp_get_settings();
        $palette = PFMP_Palette::resolve($o['palette']['accent'], $o['palette']['overrides']);

        wp_enqueue_style('pfmp-frontend', PFMP_URL . 'assets/css/pfmp-frontend.css', array(), PFMP_VERSION);
        wp_add_inline_style('pfmp-frontend', self::palette_css($palette) . self::style_css($o['style']) . self::custom_css($o));

        if (!empty($o['lang_switcher'])) {
            wp_enqueue_script('pfmp-i18n', PFMP_URL . 'assets/js/pfmp-i18n.js', array(), PFMP_VERSION, true);
            wp_localize_script('pfmp-i18n', 'PFMP', $this->js_config($o));
        }
    }

    /**
     * Build the inline CSS: :root carries the one variable full-page mode
     * needs on <body> (an ancestor of .pfmp-wrap, so it can't read the
     * scoped vars below); .pfmp-wrap carries the rest, scoped so it can
     * never leak into the surrounding theme.
     *
     * Public and static so the Builder's simulated preview can generate the
     * exact same CSS for its own mock .pfmp-wrap, keeping the two in sync.
     */
    public static function palette_css($palette) {
        $root = sprintf(':root{--pfmp-canvas:%s;}', esc_attr($palette['background']));
        $wrap = sprintf(
            '.pfmp-wrap{--pfmp-ink:%1$s;--pfmp-button-text:%2$s;--pfmp-canvas:%3$s;--pfmp-paper:%4$s;--pfmp-text:%5$s;--pfmp-soft:%6$s;--pfmp-muted:%6$s;--pfmp-line:%7$s;--pfmp-line-2:%7$s;}',
            esc_attr($palette['accent']),
            esc_attr($palette['buttonText']),
            esc_attr($palette['background']),
            esc_attr($palette['surface']),
            esc_attr($palette['text']),
            esc_attr($palette['muted']),
            esc_attr($palette['border'])
        );
        return $root . $wrap;
    }

    public static function custom_css($o) {
        return !empty($o['custom_css']) ? "\n" . wp_strip_all_tags($o['custom_css']) : '';
    }

    /**
     * Card radius, shadow, and background image, as :root variables since
     * the background image needs to reach <body> in full-page mode, the
     * same ancestor problem as --pfmp-canvas above.
     */
    public static function style_css($style) {
        $shadows = pfmp_shadow_presets();
        // $shadow always comes from our own fixed pfmp_shadow_presets() list keyed
        // by a validated 'shadow' choice (see sanitize_style()), never from raw
        // user input, so it's safe to place directly inside a <style> block.
        $shadow = isset($shadows[$style['shadow']]) ? $shadows[$style['shadow']]['css'] : $shadows['soft']['css'];

        $css = sprintf(
            ':root{--pfmp-radius:%1$dpx;--pfmp-shadow:%2$s;}',
            (int) $style['radius'],
            $shadow
        );

        $bg = $style['background_image'];
        if (!empty($bg['url']) && wp_http_validate_url($bg['url'])) {
            $css .= sprintf(
                ':root{--pfmp-bg-image:url(%1$s);--pfmp-bg-repeat:%2$s;--pfmp-bg-size:%3$s;}',
                wp_json_encode(esc_url($bg['url']), JSON_UNESCAPED_SLASHES),
                !empty($bg['repeat']) ? 'repeat' : 'no-repeat',
                !empty($bg['cover']) ? 'cover' : 'auto'
            );
        }

        return $css;
    }

    private function js_config($o) {
        $rtl = array();
        foreach (pfmp_supported_languages() as $code => $meta) {
            if (!empty($meta['rtl'])) {
                $rtl[] = $code;
            }
        }
        return array(
            'languages'   => array_values((array) $o['languages']),
            'defaultLang' => $o['default_lang'],
            'siteLocale'  => substr(get_locale(), 0, 2),
            'allowUrl'    => !empty($o['allow_url_lang']),
            'rtl'         => $rtl,
            'dict'        => pfmp_dictionary(),
        );
    }

    /**
     * Wrap MailerPress's output with a branded, centered container. This is
     * the the_content filter adapter: it only fires inside a normal Loop.
     * The Builder's simulated preview uses render_wrapped() directly on its
     * own mock markup instead, for a pixel-accurate, dependency-free preview.
     */
    public function wrap($content) {
        if ($this->wrapped || !$this->active() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $this->wrapped = true;
        return self::render_wrapped($content);
    }

    /**
     * The actual wrapping: header, language bar, the content itself, and
     * the unsubscribe off-ramp. Pure function of the current settings and
     * request, safe to call directly without a resolved main-query post,
     * so both real pages and the Builder's mock preview share it.
     */
    public static function render_wrapped($content, $action = null, $preview = false) {
        $o      = pfmp_get_settings();
        $action = $action ?? pfmp_current_action();
        $layout = ($o['layout'] === 'left') ? 'pfmp-left' : 'pfmp-centered';
        $state  = $action ? ' pfmp-state-' . sanitize_html_class($action) : '';

        $header  = self::header($o);
        $langbar = !empty($o['lang_switcher']) ? self::langbar($o) : '';
        $offramp = self::offramp($o, $action, $preview);

        return '<div class="pfmp-wrap ' . esc_attr($layout . $state) . '" dir="ltr">'
             . $header
             . $langbar
             . '<div class="pfmp-body">' . $content . '</div>'
             . $offramp
             . '</div>';
    }

    public static function header($o) {
        $mark = '';
        if (!empty($o['logo_url'])) {
            $mark = '<img class="pfmp-logo" src="' . esc_url($o['logo_url']) . '" alt="' . esc_attr($o['pub_name']) . '">';
        } else {
            $letter = pfmp_first_char($o['pub_name']);
            $mark   = '<span class="pfmp-mark" aria-hidden="true">' . esc_html($letter) . '</span>';
        }

        // Always render both, even empty, so JS has a stable element to
        // update live rather than needing to insert/remove nodes.
        $name = '<div class="pfmp-pub">' . esc_html($o['pub_name']) . '</div>';
        $tag  = '<div class="pfmp-tag">' . esc_html($o['tagline']) . '</div>';

        return '<div class="pfmp-head">' . $mark . $name . $tag . '</div>';
    }

    public static function langbar($o) {
        $supported = pfmp_supported_languages();
        $buttons   = '';
        foreach ((array) $o['languages'] as $code) {
            if (!isset($supported[$code])) {
                continue;
            }
            $buttons .= sprintf(
                '<button type="button" class="pfmp-lang" data-lang="%1$s">%2$s</button>',
                esc_attr($code),
                esc_html($supported[$code]['native'])
            );
        }
        if ($buttons === '') {
            return '';
        }
        return '<div class="pfmp-langbar" role="group" aria-label="' . esc_attr__('Language', 'pages-for-mailerpress') . '">' . $buttons . '</div>';
    }

    public static function offramp($o, $action, $preview = false) {
        if ($action !== 'confirm_unsubscribe') {
            return '';
        }
        $href = '#';
        if (!$preview) {
            $manage = pfmp_manage_url();
            if ($manage === '') {
                return '';
            }
            $href = esc_url($manage);
        }
        $hidden = empty($o['offramp']) ? ' style="display:none"' : '';
        return '<p class="pfmp-offramp"' . $hidden . '>'
             . '<span>' . esc_html__('Prefer fewer emails instead?', 'pages-for-mailerpress') . '</span> '
             . '<a href="' . $href . '"' . ($preview ? ' onclick="return false"' : '') . '>' . esc_html__('Choose which threads you get', 'pages-for-mailerpress') . ' &rarr;</a>'
             . '</p>';
    }
}
