<?php
/**
 * The plugin's admin screen.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

/**
 * The plugin's one admin screen: a live preview built from the real page
 * markup and stylesheet, next to every setting, grouped into Design, Content,
 * Readers and Advanced, with one Save button in a bar at the bottom. Works on
 * desktop, tablet and phone.
 */
final class PFMP_Builder {

    public const PAGE = 'pages-for-mailerpress';

    public function __construct() {
        add_action('admin_menu', array($this, 'menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_filter('plugin_action_links_' . plugin_basename(PFMP_FILE), array($this, 'action_link'));
    }

    public function menu(): void {
        $title = __('Pages for MailerPress', 'pages-for-mailerpress');
        if (isset($GLOBALS['admin_page_hooks']['mailerpress'])) {
            add_submenu_page('mailerpress', $title, __('Pages', 'pages-for-mailerpress'), 'manage_options', self::PAGE, array($this, 'render'));
        } else {
            add_options_page($title, $title, 'manage_options', self::PAGE, array($this, 'render'));
        }
    }

    public static function url(): string {
        $base = isset($GLOBALS['admin_page_hooks']['mailerpress']) ? 'admin.php' : 'options-general.php';
        return admin_url($base . '?page=' . self::PAGE);
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function action_link($links): array {
        $links = (array) $links;
        array_unshift($links, '<a href="' . esc_url(self::url()) . '">' . esc_html__('Settings', 'pages-for-mailerpress') . '</a>');
        return $links;
    }

    /**
     * @param string $hook
     */
    public function assets($hook): void {
        if (!str_contains((string) $hook, self::PAGE)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('pfmp-admin', PFMP_URL . 'assets/css/pfmp-admin.css', array(), PFMP_VERSION);
        // MailerPress's own page stylesheet (it draws the 2.2 list cards),
        // then the real front-end stylesheet, so the preview matches the live
        // pages exactly.
        PFMP_Frontend::enqueue_mailerpress_css();
        wp_enqueue_style('pfmp-frontend', PFMP_URL . 'assets/css/pfmp-frontend.css', array(), PFMP_VERSION);
        wp_enqueue_style('pfmp-builder', PFMP_URL . 'assets/css/pfmp-builder.css', array('pfmp-admin', 'pfmp-frontend'), PFMP_VERSION);

        $footer = array('in_footer' => true);
        wp_enqueue_script('pfmp-palette', PFMP_URL . 'assets/js/pfmp-palette.js', array(), PFMP_VERSION, $footer);
        wp_enqueue_script('pfmp-admin', PFMP_URL . 'assets/js/pfmp-admin.js', array('jquery'), PFMP_VERSION, $footer);
        wp_enqueue_script('pfmp-frontend', PFMP_URL . 'assets/js/pfmp-frontend.js', array(), PFMP_VERSION, $footer);
        wp_enqueue_script('pfmp-builder', PFMP_URL . 'assets/js/pfmp-builder.js', array('jquery', 'pfmp-palette', 'pfmp-admin', 'pfmp-frontend'), PFMP_VERSION, $footer);

        $o = pfmp_get_settings();
        wp_localize_script('pfmp-admin', 'PFMPAdmin', array(
            'choose'  => __('Select logo', 'pages-for-mailerpress'),
            'useThis' => __('Use this logo', 'pages-for-mailerpress'),
            'pubName' => (string) $o['pub_name'],
        ));

        $live_urls = array();
        foreach (array_keys(pfmp_builder_states()) as $action) {
            $live_urls[$action] = pfmp_url_for_action($action);
        }
        $natives    = wp_list_pluck(pfmp_supported_languages(), 'native');
        $dictionary = pfmp_dictionary();

        wp_localize_script('pfmp-builder', 'PFMPBuilder', array(
            'shadows'     => wp_list_pluck(pfmp_shadow_presets(), 'css'),
            'liveUrls'    => $live_urls,
            'languages'   => $natives,
            'headlines'   => pfmp_headline_defaults(),
            'privacyUrl'  => (string) get_privacy_policy_url(),
            'chooseImage' => __('Select background image', 'pages-for-mailerpress'),
            'useImage'    => __('Use this image', 'pages-for-mailerpress'),
            'copied'      => __('Copied', 'pages-for-mailerpress'),
            'i18n'        => array(
                'languages' => array_keys($natives),
                'rtl'       => pfmp_rtl_languages(),
                'dict'      => empty($dictionary) ? new stdClass() : $dictionary,
            ),
            'unsavedNone' => __('No unsaved changes', 'pages-for-mailerpress'),
            'unsavedOne'  => __('1 unsaved change', 'pages-for-mailerpress'),
            /* translators: %d: number of changed settings, 2 or more. */
            'unsavedMany' => __('%d unsaved changes', 'pages-for-mailerpress'),
            'auto'        => __('Auto', 'pages-for-mailerpress'),
            'custom'      => __('Custom', 'pages-for-mailerpress'),
            'low'         => __('Low', 'pages-for-mailerpress'),
            'off'         => __('Off', 'pages-for-mailerpress'),
            'menu'        => __('Menu', 'pages-for-mailerpress'),
            'buttons'     => __('Buttons', 'pages-for-mailerpress'),
            /* translators: %d: number of languages. */
            'nLanguages'  => __('%d languages', 'pages-for-mailerpress'),
            /* translators: %d: number of edited headlines. */
            'nEdited'     => __('%d edited', 'pages-for-mailerpress'),
            /* translators: %d: number of described newsletters. */
            'nLists'      => __('%d described', 'pages-for-mailerpress'),
            'builtIn'     => __('Built-in', 'pages-for-mailerpress'),
            'centered'    => __('Centered', 'pages-for-mailerpress'),
            'left'        => __('Left-aligned', 'pages-for-mailerpress'),
            'darkOff'     => __('Light', 'pages-for-mailerpress'),
            'darkAuto'    => __('Follows device', 'pages-for-mailerpress'),
            'darkOn'      => __('Dark', 'pages-for-mailerpress'),
            'fullPage'    => __('Full page', 'pages-for-mailerpress'),
            'inTheme'     => __('Inside your theme', 'pages-for-mailerpress'),
            'reasons'     => __('Reasons', 'pages-for-mailerpress'),
            'fewer'       => __('Fewer emails', 'pages-for-mailerpress'),
            'undo'        => __('Undo', 'pages-for-mailerpress'),
            'privacy'     => __('Privacy', 'pages-for-mailerpress'),
            'contact'     => __('Contact', 'pages-for-mailerpress'),
            'hidden'      => __('Hidden', 'pages-for-mailerpress'),
            'none'        => __('None', 'pages-for-mailerpress'),
            'counting'    => __('Counting', 'pages-for-mailerpress'),
        ));
    }

    /* --------------------------------------------------------------------- */
    /* Small field helpers                                                   */
    /* --------------------------------------------------------------------- */

    private static function name(string ...$keys): string {
        return PFMP_OPTION . '[' . implode('][', $keys) . ']';
    }

    private static function open_section(string $id, string $title, bool $open = false): void {
        printf(
            '<details class="pfmp-acc" id="pfmp-sec-%1$s" data-section="%1$s"%2$s><summary><span class="pfmp-acc__title">%3$s</span><span class="pfmp-acc__meta" data-meta="%1$s"></span><svg class="pfmp-acc__chev" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg></summary><div class="pfmp-acc__body">',
            esc_attr($id),
            $open ? ' open' : '',
            esc_html($title)
        );
    }

    private static function close_section(): void {
        echo '</div></details>';
    }

    private static function group(string $label): void {
        printf('<h2 class="pfmp-group">%s</h2>', esc_html($label));
    }

    private static function toggle(string $name, bool $checked, string $label, string $desc = '', string $id = ''): void {
        printf(
            '<label class="pfmp-toggle"><input type="checkbox" role="switch" name="%1$s" value="1"%2$s%3$s><span class="pfmp-toggle__ui" aria-hidden="true"></span><span class="pfmp-toggle__text"><span class="pfmp-toggle__label">%4$s</span>%5$s</span></label>',
            esc_attr($name),
            $id !== '' ? ' id="' . esc_attr($id) . '"' : '',
            checked($checked, true, false),
            wp_kses($label, array('code' => array())),
            $desc !== '' ? '<span class="pfmp-toggle__desc">' . esc_html($desc) . '</span>' : ''
        );
    }

    /**
     * @param array<string, string> $attrs Extra input attributes.
     */
    private static function text(string $id, string $name, string $value, string $label, string $desc = '', array $attrs = array()): void {
        $type = $attrs['type'] ?? 'text';
        unset($attrs['type']);
        $extra = '';
        foreach ($attrs as $key => $val) {
            $extra .= ' ' . esc_attr($key) . '="' . esc_attr($val) . '"';
        }
        printf(
            '<div class="pfmp-field"><label for="%1$s">%2$s</label><input type="%6$s" id="%1$s" name="%3$s" value="%4$s"%5$s>%7$s</div>',
            esc_attr($id),
            esc_html($label),
            esc_attr($name),
            esc_attr($value),
            $extra, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each key and value escaped with esc_attr() above.
            esc_attr($type),
            $desc !== '' ? '<p class="pfmp-desc">' . esc_html($desc) . '</p>' : ''
        );
    }

    /**
     * @param array<string, string> $choices value => label
     */
    private static function segmented(string $name, string $value, string $label, array $choices): void {
        echo '<fieldset class="pfmp-segmented"><legend>' . esc_html($label) . '</legend><div class="pfmp-segmented__opts">';
        foreach ($choices as $val => $text) {
            printf(
                '<label><input type="radio" name="%1$s" value="%2$s"%3$s><span>%4$s</span></label>',
                esc_attr($name),
                esc_attr($val),
                checked($value, $val, false),
                esc_html($text)
            );
        }
        echo '</div></fieldset>';
    }

    /* --------------------------------------------------------------------- */
    /* The screen                                                            */
    /* --------------------------------------------------------------------- */

    public function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $o      = pfmp_get_settings();
        $states = pfmp_builder_states();
        $live   = pfmp_live_page_count();
        $url    = pfmp_url_for_action('manage');
        ?>
        <div class="wrap pfmp-builder" data-view="settings">
            <div class="pfmp-top">
                <div class="pfmp-top__brand">
                    <span class="pfmp-top__mark" aria-hidden="true">P</span>
                    <div>
                        <div class="pfmp-top__title">
                            <h1><?php esc_html_e('Pages for MailerPress', 'pages-for-mailerpress'); ?></h1>
                            <span class="pfmp-live <?php echo $live > 0 ? 'is-on' : 'is-off'; ?>">
                                <span class="pfmp-live__dot" aria-hidden="true"></span>
                                <?php
                                if ($live > 0) {
                                    /* translators: %d: number of pages. */
                                    echo esc_html(sprintf(_n('Live on %d page', 'Live on %d pages', $live, 'pages-for-mailerpress'), $live));
                                } else {
                                    esc_html_e('Off', 'pages-for-mailerpress');
                                }
                                ?>
                            </span>
                        </div>
                        <p class="pfmp-top__sub"><?php esc_html_e('Style, translate, and preview the pages MailerPress shows your readers.', 'pages-for-mailerpress'); ?></p>
                    </div>
                </div>
                <div class="pfmp-top__links">
                    <?php if ($url !== '') : ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" id="pfmp-view-live"><?php esc_html_e('View live page', 'pages-for-mailerpress'); ?> <span aria-hidden="true">&#8599;</span></a>
                    <?php endif; ?>
                    <a href="https://github.com/jaimealnassim/pages-for-mailerpress#readme" target="_blank" rel="noopener"><?php esc_html_e('Help', 'pages-for-mailerpress'); ?></a>
                </div>
            </div>

            <?php settings_errors(PFMP_OPTION); ?>
            <?php if (isset($_GET['settings-updated']) && empty(get_settings_errors(PFMP_OPTION))) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'pages-for-mailerpress'); ?></p></div>
            <?php endif; ?>

            <?php if (pfmp_pro_design_enabled()) : ?>
                <div class="notice notice-info inline pfmp-notice">
                    <p>
                        <strong><?php esc_html_e('MailerPress Pro page design is on.', 'pages-for-mailerpress'); ?></strong>
                        <?php esc_html_e('Your pages use the design from MailerPress Pro, and this plugin adds only the language control and translations. The design settings here apply when Pro page design is off.', 'pages-for-mailerpress'); ?>
                        <a href="<?php echo esc_url(pfmp_pro_design_settings_url()); ?>"><?php esc_html_e('Open MailerPress page design', 'pages-for-mailerpress'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="pfmp-mtabs" role="tablist" aria-label="<?php esc_attr_e('View', 'pages-for-mailerpress'); ?>">
                <button type="button" role="tab" aria-selected="true" data-view="settings"><?php esc_html_e('Settings', 'pages-for-mailerpress'); ?></button>
                <button type="button" role="tab" aria-selected="false" data-view="preview"><?php esc_html_e('Preview', 'pages-for-mailerpress'); ?></button>
            </div>

            <form id="pfmp-builder-form" method="post" action="options.php">
                <?php settings_fields(PFMP_Settings::GROUP); ?>

                <div class="pfmp-layout">
                    <?php $this->render_preview($states); ?>
                    <aside class="pfmp-rail" aria-label="<?php esc_attr_e('Settings', 'pages-for-mailerpress'); ?>">
                        <div class="pfmp-search">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                            <label class="screen-reader-text" for="pfmp-search"><?php esc_html_e('Search settings', 'pages-for-mailerpress'); ?></label>
                            <input type="search" id="pfmp-search" placeholder="<?php esc_attr_e('Search settings', 'pages-for-mailerpress'); ?>" autocomplete="off">
                        </div>
                        <p class="pfmp-search-empty" hidden><?php esc_html_e('No settings match.', 'pages-for-mailerpress'); ?></p>
                        <?php
                        $this->render_design($o);
                        $this->render_content($o);
                        $this->render_readers($o);
                        $this->render_advanced($o);
                        ?>
                    </aside>
                </div>

                <div class="pfmp-savebar">
                    <div class="pfmp-savebar__state">
                        <span class="pfmp-savebar__dot" aria-hidden="true"></span>
                        <span id="pfmp-dirty-text" role="status"><?php esc_html_e('No unsaved changes', 'pages-for-mailerpress'); ?></span>
                        <button type="button" class="button-link" id="pfmp-discard" hidden><?php esc_html_e('Discard', 'pages-for-mailerpress'); ?></button>
                    </div>
                    <?php submit_button(__('Save changes', 'pages-for-mailerpress'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * @param array<string, string> $states
     */
    private function render_preview(array $states): void {
        ?>
        <section class="pfmp-preview" aria-label="<?php esc_attr_e('Preview', 'pages-for-mailerpress'); ?>">
            <div class="pfmp-toolbar">
                <div class="pfmp-tabs" role="tablist" aria-label="<?php esc_attr_e('Page', 'pages-for-mailerpress'); ?>">
                    <?php foreach ($states as $key => $label) : ?>
                        <button type="button" role="tab" class="pfmp-tab" aria-selected="<?php echo $key === 'manage' ? 'true' : 'false'; ?>" data-state="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="pfmp-toolbar__tools">
                    <label for="pfmp-pv-lang"><?php esc_html_e('Preview in', 'pages-for-mailerpress'); ?></label>
                    <select id="pfmp-pv-lang"></select>
                    <div class="pfmp-iconset" role="group" aria-label="<?php esc_attr_e('Preview size', 'pages-for-mailerpress'); ?>">
                        <button type="button" data-device="desktop" aria-pressed="true" aria-label="<?php esc_attr_e('Desktop', 'pages-for-mailerpress'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></button>
                        <button type="button" data-device="tablet" aria-pressed="false" aria-label="<?php esc_attr_e('Tablet', 'pages-for-mailerpress'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M11 18h2"/></svg></button>
                        <button type="button" data-device="phone" aria-pressed="false" aria-label="<?php esc_attr_e('Phone', 'pages-for-mailerpress'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg></button>
                    </div>
                    <div class="pfmp-iconset" role="group" aria-label="<?php esc_attr_e('Appearance', 'pages-for-mailerpress'); ?>">
                        <button type="button" data-appearance="light" aria-pressed="true" aria-label="<?php esc_attr_e('Light', 'pages-for-mailerpress'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/></svg></button>
                        <button type="button" data-appearance="dark" aria-pressed="false" aria-label="<?php esc_attr_e('Dark', 'pages-for-mailerpress'); ?>"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/></svg></button>
                    </div>
                </div>
            </div>

            <div class="pfmp-stage" id="pfmp-stage">
                <div class="pfmp-frame" data-device="desktop">
                    <div class="pfmp-canvas" id="pfmp-preview-canvas">
                        <?php foreach (array_keys($states) as $key) : ?>
                            <div class="pfmp-preview-panel<?php echo $key === 'manage' ? ' is-active' : ''; ?>" data-preview-state="<?php echo esc_attr($key); ?>">
                                <?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in render_wrapped() and mock_content(); wp_kses_post() would strip the form controls the preview needs.
                                echo PFMP_Frontend::render_wrapped(self::mock_content($key), $key, true);
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <p class="pfmp-caption"><?php esc_html_e('Live preview. Changes show here right away and go live when you save.', 'pages-for-mailerpress'); ?></p>
        </section>
        <?php
    }

    /**
     * @param array<string, mixed> $o
     */
    private function render_design(array $o): void {
        self::group(__('Design', 'pages-for-mailerpress'));

        self::open_section('theme', __('Theme', 'pages-for-mailerpress'), true);
        $rows = array_merge(array('accent' => __('Brand accent', 'pages-for-mailerpress')), PFMP_Palette::tokens());
        echo '<div class="pfmp-colors">';
        foreach ($rows as $key => $label) {
            $is_accent = $key === 'accent';
            $value     = $is_accent ? (string) $o['palette']['accent'] : (string) $o['palette']['overrides'][$key];
            $name      = $is_accent ? self::name('palette', 'accent') : self::name('palette', 'overrides', $key);
            printf(
                '<div class="pfmp-color" data-color="%1$s">'
                . '<input type="color" class="pfmp-color__swatch" id="pfmp-c-%1$s-swatch" aria-label="%2$s" value="%3$s">'
                . '<label class="pfmp-color__label" for="pfmp-c-%1$s">%4$s</label>'
                . '<input type="text" class="pfmp-color__hex code" id="pfmp-c-%1$s" name="%5$s" value="%6$s" maxlength="7" spellcheck="false" autocomplete="off"%7$s>'
                . '<span class="pfmp-color__badge" data-badge></span>'
                . '%8$s'
                . '</div>',
                esc_attr($key),
                /* translators: %s: color name, like "Text". */
                esc_attr(sprintf(__('%s color picker', 'pages-for-mailerpress'), $label)),
                esc_attr($value !== '' ? $value : '#ffffff'),
                esc_html($label),
                esc_attr($name),
                esc_attr($value),
                $is_accent ? ' required pattern="#([0-9a-fA-F]{3}){1,2}"' : ' pattern="#([0-9a-fA-F]{3}){1,2}"',
                $is_accent ? '' : '<button type="button" class="button-link pfmp-color__reset" data-reset hidden>' . esc_html__('Use auto', 'pages-for-mailerpress') . '</button>'
            );
        }
        echo '</div>';
        echo '<p class="pfmp-desc">' . esc_html__('Pick your brand accent and the rest is worked out from it. Set any other color to override it; the badge shows how readable text is on the card.', 'pages-for-mailerpress') . '</p>';
        self::segmented(self::name('dark_mode'), (string) $o['dark_mode'], __('Dark mode', 'pages-for-mailerpress'), array(
            'off'  => __('Off', 'pages-for-mailerpress'),
            'auto' => __('Follow device', 'pages-for-mailerpress'),
            'on'   => __('Always', 'pages-for-mailerpress'),
        ));
        self::close_section();

        self::open_section('card', __('Card and layout', 'pages-for-mailerpress'));
        self::segmented(self::name('layout'), (string) $o['layout'], __('Layout', 'pages-for-mailerpress'), array(
            'centered' => __('Centered', 'pages-for-mailerpress'),
            'left'     => __('Left-aligned', 'pages-for-mailerpress'),
        ));
        ?>
        <div class="pfmp-field">
            <label for="pfmp-radius"><?php esc_html_e('Corner radius', 'pages-for-mailerpress'); ?> <span id="pfmp-radius-value" class="pfmp-value"><?php echo esc_html((string) $o['style']['radius']); ?>px</span></label>
            <input type="range" id="pfmp-radius" min="0" max="32" step="1" name="<?php echo esc_attr(self::name('style', 'radius')); ?>" value="<?php echo esc_attr((string) $o['style']['radius']); ?>">
        </div>
        <div class="pfmp-field">
            <label for="pfmp-shadow"><?php esc_html_e('Shadow', 'pages-for-mailerpress'); ?></label>
            <select id="pfmp-shadow" name="<?php echo esc_attr(self::name('style', 'shadow')); ?>">
                <?php foreach (pfmp_shadow_presets() as $key => $preset) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($o['style']['shadow'], $key); ?>><?php echo esc_html($preset['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pfmp-field">
            <span class="pfmp-label"><?php esc_html_e('Background image', 'pages-for-mailerpress'); ?></span>
            <div class="pfmp-media">
                <div class="pfmp-media__prev" id="pfmp-bg-prev">
                    <?php if (!empty($o['style']['background_image']['url'])) : ?>
                        <img src="<?php echo esc_url($o['style']['background_image']['url']); ?>" alt="">
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="pfmp-bg-pick"><?php esc_html_e('Select image', 'pages-for-mailerpress'); ?></button>
                <button type="button" class="button-link button-link-delete" id="pfmp-bg-clear" <?php echo empty($o['style']['background_image']['url']) ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'pages-for-mailerpress'); ?></button>
            </div>
            <input type="hidden" id="pfmp-bg-id" name="<?php echo esc_attr(self::name('style', 'background_image', 'id')); ?>" value="<?php echo esc_attr((string) $o['style']['background_image']['id']); ?>">
            <input type="hidden" id="pfmp-bg-url" name="<?php echo esc_attr(self::name('style', 'background_image', 'url')); ?>" value="<?php echo esc_attr((string) $o['style']['background_image']['url']); ?>">
            <p class="pfmp-desc"><?php esc_html_e('Fills the space around the card in full-page mode.', 'pages-for-mailerpress'); ?></p>
        </div>
        <?php
        self::toggle(self::name('style', 'background_image', 'cover'), !empty($o['style']['background_image']['cover']), __('Stretch the image to fill the screen', 'pages-for-mailerpress'), '', 'pfmp-bg-cover');
        self::toggle(self::name('style', 'background_image', 'repeat'), !empty($o['style']['background_image']['repeat']), __('Tile the image instead', 'pages-for-mailerpress'), '', 'pfmp-bg-repeat');
        self::close_section();
    }

    /**
     * @param array<string, mixed> $o
     */
    private function render_content(array $o): void {
        self::group(__('Content', 'pages-for-mailerpress'));

        self::open_section('brand', __('Logo and name', 'pages-for-mailerpress'));
        ?>
        <div class="pfmp-field">
            <span class="pfmp-label"><?php esc_html_e('Logo', 'pages-for-mailerpress'); ?></span>
            <div class="pfmp-media">
                <div class="pfmp-logo-prev pfmp-media__prev">
                    <?php if (!empty($o['logo_url'])) : ?>
                        <img src="<?php echo esc_url($o['logo_url']); ?>" alt="">
                    <?php else : ?>
                        <span><?php echo esc_html(pfmp_first_char($o['pub_name'])); ?></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="pfmp-logo-pick"><?php esc_html_e('Select logo', 'pages-for-mailerpress'); ?></button>
                <button type="button" class="button-link button-link-delete" id="pfmp-logo-clear" <?php echo empty($o['logo_url']) ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'pages-for-mailerpress'); ?></button>
            </div>
            <input type="hidden" id="pfmp-logo-id" name="<?php echo esc_attr(self::name('logo_id')); ?>" value="<?php echo esc_attr((string) $o['logo_id']); ?>">
            <input type="hidden" id="pfmp-logo-url" name="<?php echo esc_attr(self::name('logo_url')); ?>" value="<?php echo esc_attr((string) $o['logo_url']); ?>">
            <p class="pfmp-desc"><?php esc_html_e('PNG or SVG, about 80px tall. Without one, the first letter of the name is shown.', 'pages-for-mailerpress'); ?></p>
        </div>
        <?php
        self::text('pfmp-pub', self::name('pub_name'), (string) $o['pub_name'], __('Publication name', 'pages-for-mailerpress'));
        self::text('pfmp-tag', self::name('tagline'), (string) $o['tagline'], __('Tagline', 'pages-for-mailerpress'), __('A short line under the name. Leave empty to hide it.', 'pages-for-mailerpress'));
        self::close_section();

        self::open_section('text', __('Page text', 'pages-for-mailerpress'));
        echo '<p class="pfmp-desc">' . esc_html__('The headline at the top of each page. Leave a field empty to use the built-in headline, which is translated into every language. Your own text is shown as you wrote it, in every language.', 'pages-for-mailerpress') . '</p>';
        $defaults = pfmp_headline_defaults();
        foreach (pfmp_builder_states() as $state => $label) {
            self::text('pfmp-hl-' . $state, self::name('headlines', $state), (string) ($o['headlines'][$state] ?? ''), $label, '', array(
                'placeholder'   => $defaults[$state],
                'data-headline' => $state,
                'maxlength'     => '120',
            ));
        }
        self::close_section();

        self::open_section('lists', __('Newsletter details', 'pages-for-mailerpress'));
        $lists = pfmp_mailerpress_lists();
        if (empty($lists)) {
            echo '<p class="pfmp-desc">' . esc_html__('No MailerPress lists yet. Create lists in MailerPress, then describe them here.', 'pages-for-mailerpress') . '</p>';
        } else {
            echo '<p class="pfmp-desc">' . esc_html__('A line about each newsletter and how often it arrives, shown on the Manage page so readers know what they are unchecking. Shown as written in every language.', 'pages-for-mailerpress') . '</p>';
            foreach ($lists as $id => $list_name) {
                $row = $o['list_details'][$id] ?? array('desc' => '', 'when' => '');
                printf(
                    '<fieldset class="pfmp-listrow" data-list="%1$d"><legend>%2$s</legend><div class="pfmp-listrow__fields">'
                    . '<div class="pfmp-field"><label for="pfmp-ld-%1$d-desc">%3$s</label><input type="text" id="pfmp-ld-%1$d-desc" name="%4$s" value="%5$s" maxlength="160" data-list-desc="%1$d" placeholder="%6$s"></div>'
                    . '<div class="pfmp-field pfmp-field--short"><label for="pfmp-ld-%1$d-when">%7$s</label><input type="text" id="pfmp-ld-%1$d-when" name="%8$s" value="%9$s" maxlength="30" data-list-when="%1$d" placeholder="%10$s"></div>'
                    . '</div></fieldset>',
                    (int) $id,
                    esc_html($list_name),
                    esc_html__('Description', 'pages-for-mailerpress'),
                    esc_attr(self::name('list_details', (string) $id, 'desc')),
                    esc_attr((string) ($row['desc'] ?? '')),
                    esc_attr__('What readers get', 'pages-for-mailerpress'),
                    esc_html__('How often', 'pages-for-mailerpress'),
                    esc_attr(self::name('list_details', (string) $id, 'when')),
                    esc_attr((string) ($row['when'] ?? '')),
                    esc_attr__('Weekly', 'pages-for-mailerpress')
                );
            }
        }
        self::close_section();

        self::open_section('footer', __('Footer links', 'pages-for-mailerpress'));
        self::toggle(self::name('footer', 'show'), !empty($o['footer']['show']), __('Show footer links', 'pages-for-mailerpress'), '', 'pfmp-foot-show');
        $privacy = (string) get_privacy_policy_url();
        self::text(
            'pfmp-foot-privacy',
            self::name('footer', 'privacy'),
            (string) $o['footer']['privacy'],
            __('Privacy policy link', 'pages-for-mailerpress'),
            $privacy !== '' ? __('Leave empty to use your WordPress privacy page.', 'pages-for-mailerpress') : __('Your site has no WordPress privacy page yet, so add a link here to show one.', 'pages-for-mailerpress'),
            array('type' => 'url', 'placeholder' => $privacy !== '' ? $privacy : 'https://')
        );
        self::text('pfmp-foot-contact', self::name('footer', 'contact'), (string) $o['footer']['contact'], __('Contact link or email', 'pages-for-mailerpress'), '', array('placeholder' => 'hello@example.com'));
        self::close_section();
    }

    /**
     * @param array<string, mixed> $o
     */
    private function render_readers(array $o): void {
        self::group(__('Readers', 'pages-for-mailerpress'));

        self::open_section('languages', __('Languages', 'pages-for-mailerpress'));
        self::toggle(self::name('lang_switcher'), !empty($o['lang_switcher']), __('Let readers switch language', 'pages-for-mailerpress'), __('Instant, with no page reload. Right-to-left languages flip the layout.', 'pages-for-mailerpress'), 'pfmp-langtoggle');
        echo '<div class="pfmp-langdep">';
        self::segmented(self::name('switcher_style'), (string) $o['switcher_style'], __('Style', 'pages-for-mailerpress'), array(
            'menu'    => __('Menu', 'pages-for-mailerpress'),
            'buttons' => __('Row of buttons', 'pages-for-mailerpress'),
        ));
        echo '<fieldset class="pfmp-lang-grid"><legend>' . esc_html__('Offered languages', 'pages-for-mailerpress') . '</legend>';
        foreach (pfmp_supported_languages() as $code => $meta) {
            $is_en = $code === 'en';
            printf(
                '<label><input type="checkbox" name="%1$s" value="%2$s"%3$s%4$s> <span lang="%2$s">%5$s</span> <span class="pfmp-muted">%6$s</span></label>',
                esc_attr(self::name('languages') . '[]'),
                esc_attr($code),
                checked($is_en || in_array($code, (array) $o['languages'], true), true, false),
                $is_en ? ' disabled' : '',
                esc_html($meta['native']),
                esc_html($meta['label'] . ($meta['rtl'] ? ', RTL' : ''))
            );
        }
        echo '</fieldset>';
        ?>
        <div class="pfmp-field">
            <label for="pfmp-deflang"><?php esc_html_e('Fallback language', 'pages-for-mailerpress'); ?></label>
            <select id="pfmp-deflang" name="<?php echo esc_attr(self::name('default_lang')); ?>">
                <option value="locale" <?php selected($o['default_lang'], 'locale'); ?>><?php esc_html_e('WordPress site language', 'pages-for-mailerpress'); ?></option>
                <?php foreach (pfmp_supported_languages() as $code => $meta) : ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($o['default_lang'], $code); ?>><?php echo esc_html($meta['native'] . ' (' . $meta['label'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="pfmp-desc"><?php esc_html_e('Readers see their browser language when you offer it, and this one when you do not.', 'pages-for-mailerpress'); ?></p>
        </div>
        <?php
        self::toggle(self::name('allow_url_lang'), !empty($o['allow_url_lang']), __('Allow <code>?lang=</code> in links to pick the language', 'pages-for-mailerpress'), __('Handy for sending one list a link that opens in its language.', 'pages-for-mailerpress'));
        echo '</div>';
        self::close_section();

        self::open_section('leave', __('Before they leave', 'pages-for-mailerpress'));
        self::toggle(self::name('offramp'), !empty($o['offramp']), __('Offer fewer emails first', 'pages-for-mailerpress'), __('Links to the Manage page, where readers can keep only some newsletters.', 'pages-for-mailerpress'), 'pfmp-f-offramp');
        self::toggle(self::name('reasons'), !empty($o['reasons']), __('Ask why they are leaving', 'pages-for-mailerpress'), __('Optional one-tap reasons. Only totals are kept; see Insights.', 'pages-for-mailerpress'), 'pfmp-f-reasons');
        self::toggle(self::name('undo'), !empty($o['undo']), __('Offer Resubscribe after unsubscribing', 'pages-for-mailerpress'), __('For readers who clicked by mistake.', 'pages-for-mailerpress'), 'pfmp-f-undo');
        self::text('pfmp-archive', self::name('archive_url'), (string) $o['archive_url'], __('Link to your latest issue', 'pages-for-mailerpress'), __('Shown after unsubscribing and after confirming. Leave empty to hide.', 'pages-for-mailerpress'), array('type' => 'url', 'placeholder' => 'https://'));
        self::close_section();

        self::open_section('insights', __('Insights', 'pages-for-mailerpress'));
        self::toggle(self::name('insights'), !empty($o['insights']), __('Count what readers do on these pages', 'pages-for-mailerpress'), __('Totals per month only. No email addresses, IP addresses or cookies are stored.', 'pages-for-mailerpress'), 'pfmp-f-insights');
        $this->render_insights_table();
        self::toggle(self::name('reset_insights'), false, __('Clear all counts when I save', 'pages-for-mailerpress'));
        self::close_section();
    }

    private function render_insights_table(): void {
        $this_month = gmdate('Y-m');
        $last_month = gmdate('Y-m', (int) strtotime('first day of last month'));
        $rows       = array(
            array(__('Saw the unsubscribe page', 'pages-for-mailerpress'), 'view', 'confirm_unsubscribe'),
            array(__('Chose fewer emails', 'pages-for-mailerpress'), 'fewer', ''),
            array(__('Kept their subscription', 'pages-for-mailerpress'), 'keep', ''),
            array(__('Unsubscribed', 'pages-for-mailerpress'), 'view', 'unsubscribe'),
            array(__('Resubscribed', 'pages-for-mailerpress'), 'resubscribe', ''),
        );
        foreach (pfmp_reason_options() as $key => $label) {
            /* translators: %s: a reason, like "Too many emails". */
            $rows[] = array(sprintf(__('Reason: %s', 'pages-for-mailerpress'), $label), 'reason', $key);
        }
        ?>
        <table class="pfmp-stats widefat striped">
            <thead><tr>
                <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Action', 'pages-for-mailerpress'); ?></span></th>
                <th scope="col"><?php esc_html_e('This month', 'pages-for-mailerpress'); ?></th>
                <th scope="col"><?php esc_html_e('Last month', 'pages-for-mailerpress'); ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as [$label, $event, $key]) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td><?php echo esc_html(number_format_i18n(PFMP_Insights::total($this_month, $event, $key))); ?></td>
                        <td><?php echo esc_html(number_format_i18n(PFMP_Insights::total($last_month, $event, $key))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $langs = PFMP_Insights::month($this_month)['lang'] ?? array();
        arsort($langs);
        if (!empty($langs)) {
            $natives = wp_list_pluck(pfmp_supported_languages(), 'native');
            $parts   = array();
            foreach (array_slice($langs, 0, 5, true) as $code => $count) {
                $parts[] = ($natives[$code] ?? $code) . ' ' . number_format_i18n((int) $count);
            }
            echo '<p class="pfmp-desc">' . esc_html__('Languages chosen this month:', 'pages-for-mailerpress') . ' ' . esc_html(implode(', ', $parts)) . '</p>';
        }
    }

    /**
     * @param array<string, mixed> $o
     */
    private function render_advanced(array $o): void {
        self::group(__('Advanced', 'pages-for-mailerpress'));

        self::open_section('pages', __('Pages and behavior', 'pages-for-mailerpress'));
        self::toggle(self::name('enabled'), !empty($o['enabled']), __('Use this design on MailerPress pages', 'pages-for-mailerpress'), __('When off, MailerPress shows its default pages.', 'pages-for-mailerpress'), 'pfmp-f-enabled');
        self::toggle(self::name('takeover'), !empty($o['takeover']), __('Full-page mode', 'pages-for-mailerpress'), __('Shows the pages on their own, without your theme\'s header, footer or styles.', 'pages-for-mailerpress'), 'pfmp-f-takeover');
        $custom = pfmp_mailerpress_custom_pages();
        $roles  = array(
            'manage'      => array(__('Manage page', 'pages-for-mailerpress'), 'manage'),
            'unsubscribe' => array(__('Unsubscribe page', 'pages-for-mailerpress'), 'confirm_unsubscribe'),
        );
        echo '<ul class="pfmp-pagelist">';
        foreach ($roles as $role => [$label, $action]) {
            $url = pfmp_url_for_action($action);
            echo '<li><strong>' . esc_html($label) . '</strong> ';
            if ($url !== '') {
                echo '<code>' . esc_html(wp_make_link_relative(remove_query_arg('action', $url))) . '</code> ';
                echo '<span class="pfmp-muted">' . esc_html(!empty($custom[$role]) ? __('your page', 'pages-for-mailerpress') : __('MailerPress default', 'pages-for-mailerpress')) . '</span> ';
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('View', 'pages-for-mailerpress') . '</a>';
            } else {
                echo '<span class="pfmp-muted">' . esc_html__('Not set up in MailerPress yet.', 'pages-for-mailerpress') . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
        self::close_section();

        self::open_section('code', __('Custom CSS, import and export', 'pages-for-mailerpress'));
        ?>
        <div class="pfmp-field">
            <label for="pfmp-css"><?php esc_html_e('Custom CSS', 'pages-for-mailerpress'); ?></label>
            <textarea id="pfmp-css" class="code" rows="6" name="<?php echo esc_attr(self::name('custom_css')); ?>" <?php disabled(!current_user_can('unfiltered_html')); ?> placeholder=".pfmp-title { letter-spacing: 0; }"><?php echo esc_textarea((string) $o['custom_css']); ?></textarea>
            <p class="pfmp-desc"><?php esc_html_e('Loaded last on the pages, so it wins.', 'pages-for-mailerpress'); ?></p>
        </div>
        <div class="pfmp-field">
            <label for="pfmp-export"><?php esc_html_e('Export', 'pages-for-mailerpress'); ?></label>
            <textarea id="pfmp-export" class="code" rows="3" readonly><?php echo esc_textarea((string) wp_json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></textarea>
            <p><button type="button" class="button" id="pfmp-export-copy"><?php esc_html_e('Copy settings', 'pages-for-mailerpress'); ?></button></p>
        </div>
        <div class="pfmp-field">
            <label for="pfmp-import"><?php esc_html_e('Import', 'pages-for-mailerpress'); ?></label>
            <textarea id="pfmp-import" class="code" rows="3" name="<?php echo esc_attr(self::name('import_json')); ?>" placeholder="<?php esc_attr_e('Paste exported settings, then save.', 'pages-for-mailerpress'); ?>"></textarea>
        </div>
        <?php
        self::toggle(self::name('uninstall_cleanup'), !empty($o['uninstall_cleanup']), __('Delete this plugin\'s settings and counts when it is deleted', 'pages-for-mailerpress'));
        self::close_section();
    }

    /* --------------------------------------------------------------------- */
    /* Preview content                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Placeholder content for a given state, using MailerPress 2.2's real
     * markup, classes, and English copy. The copy is MailerPress's own wording
     * on purpose: the preview's language control translates it with the same
     * dictionary the live pages use. These strings are MailerPress's, not this
     * plugin's, so they are not passed through this plugin's translation
     * functions.
     */
    private static function mock_content(string $action): string {
        return match ($action) {
            'confirm'              => '<p>' . esc_html('We have added you to our list. You will receive our next newsletter.') . '</p>',
            'reengagement_confirm' => '<p>' . esc_html('Your subscription has been confirmed.') . '</p>',
            'confirm_unsubscribe'  => '<p>' . esc_html('Just click this link to unsubscribe from our emails.')
                                    . '<br><a class="button" href="#">' . esc_html('Yes, unsubscribe me') . '</a></p>',
            'unsubscribe'          => '<p>' . esc_html('You have successfully unsubscribed from our emails.') . '</p>',
            default                => self::mock_manage_form(),
        };
    }

    private static function mock_manage_form(): string {
        // A <div>, not a <form>: this sits inside the settings <form>, and a
        // nested form would cut the outer one short and break Save. The
        // inputs have no name, so they are never saved. The ids match
        // MailerPress's real ones so the same layout rules apply.
        $lists = pfmp_mailerpress_lists();
        if (empty($lists)) {
            $lists = array(
                1 => __('Weekly Digest', 'pages-for-mailerpress'),
                2 => __('Product Updates', 'pages-for-mailerpress'),
                3 => __('Special Offers', 'pages-for-mailerpress'),
            );
        }

        $html  = '<div class="mailerpress-manage-subscription">';
        $html .= '<p class="mailerpress-change-email-info">' . esc_html('Uncheck lists to unsubscribe from them, then save your preferences.') . '</p>';
        $html .= '<div class="mailerpress-form-line"><label>' . esc_html('Email*') . '</label><strong>reader@example.com</strong></div>';
        $html .= '<div class="mailerpress-form-line"><label for="subscription_status">' . esc_html('Subscription status') . '</label>'
               . '<select id="subscription_status"><option value="subscribed" selected>' . esc_html('Subscribed') . '</option><option value="unsubscribed">' . esc_html('Unsubscribed') . '</option></select></div>';
        $html .= '<div class="mailerpress-form-line"><label for="contact_first_name">' . esc_html('First name') . '</label><input id="contact_first_name" type="text" value="Alex"></div>';
        $html .= '<div class="mailerpress-form-line"><label for="contact_last_name">' . esc_html('Last name') . '</label><input id="contact_last_name" type="text" value="Rivera"></div>';
        $html .= '<div class="mailerpress-form-line"><fieldset class="mailerpress-subscription-list-group">';
        $html .= '<legend>' . esc_html('Manage your subscriptions') . '</legend><div class="mailerpress-subscription-list-options">';
        $n = 0;
        foreach ($lists as $id => $name) {
            $input_id = 'pfmp-mock-list-' . (int) $id;
            $html .= '<label class="mailerpress-subscription-list-option" for="' . esc_attr($input_id) . '">'
                   . '<input id="' . esc_attr($input_id) . '" class="mailerpress-subscription-list-input" type="checkbox" value="' . (int) $id . '"' . ($n < 2 ? ' checked' : '') . '>'
                   . '<span class="mailerpress-subscription-list-indicator" aria-hidden="true"></span>'
                   . '<span class="mailerpress-subscription-list-copy">'
                   . '<span class="mailerpress-subscription-list-name">' . esc_html($name) . '</span>'
                   . '<span class="mailerpress-subscription-list-status">'
                   . '<span class="mailerpress-subscription-list-status-on">' . esc_html('Subscribed') . '</span>'
                   . '<span class="mailerpress-subscription-list-status-off">' . esc_html('Not subscribed') . '</span>'
                   . '</span></span></label>';
            ++$n;
        }
        $html .= '</div></fieldset></div>';
        $html .= '<div class="mailerpress-form-line"><button type="button" class="button mailerpress-submit-btn">' . esc_html('Save') . '</button></div>';
        $html .= '</div>';

        return $html;
    }
}
