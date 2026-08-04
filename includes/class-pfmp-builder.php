<?php
defined('ABSPATH') || exit;

/**
 * The plugin's one and only admin screen: a simulated live preview on the
 * left, built from the exact same markup and CSS the real pages use, and an
 * accordion of every setting (Presets, Colors, Card style, General,
 * Branding, Languages, Advanced) on the right, all in one form.
 *
 * The preview does not load the live site. It renders MailerPress's real
 * markup structure with placeholder data, using the plugin's own stylesheet,
 * so it looks exactly like the real page without depending on that page's
 * routing, permalinks, or any other site-specific thing that could fail.
 */
class PFMP_Builder {

    const PAGE = 'pages-for-mailerpress';

    /** @var PFMP_Settings */
    private $settings;

    public function __construct(PFMP_Settings $settings) {
        $this->settings = $settings;
        add_action('admin_menu', array($this, 'menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_filter('plugin_action_links_' . plugin_basename(PFMP_FILE), array($this, 'action_link'));
    }

    public function menu() {
        $title = __('Pages for MailerPress', 'pages-for-mailerpress');
        $cap   = 'manage_options';

        $parent_exists = isset($GLOBALS['admin_page_hooks']['mailerpress']);
        if ($parent_exists) {
            add_submenu_page('mailerpress', $title, __('Pages', 'pages-for-mailerpress'), $cap, self::PAGE, array($this, 'render'));
        } else {
            add_options_page($title, $title, $cap, self::PAGE, array($this, 'render'));
        }
    }

    public static function url() {
        $base = isset($GLOBALS['admin_page_hooks']['mailerpress']) ? 'admin.php' : 'options-general.php';
        return admin_url($base . '?page=' . self::PAGE);
    }

    public function action_link($links) {
        $link = '<a href="' . esc_url(self::url()) . '">' . esc_html__('Settings', 'pages-for-mailerpress') . '</a>';
        array_unshift($links, $link);
        return $links;
    }

    public function assets($hook) {
        if (strpos((string) $hook, self::PAGE) === false) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_style('pfmp-admin', PFMP_URL . 'assets/css/pfmp-admin.css', array(), PFMP_VERSION);
        // The real front-end stylesheet, so the simulated preview is styled
        // identically to the live pages, not an admin-side approximation.
        wp_enqueue_style('pfmp-frontend', PFMP_URL . 'assets/css/pfmp-frontend.css', array(), PFMP_VERSION);
        wp_enqueue_style('pfmp-builder', PFMP_URL . 'assets/css/pfmp-builder.css', array('wp-color-picker', 'pfmp-admin', 'pfmp-frontend'), PFMP_VERSION);
        wp_enqueue_script('pfmp-palette', PFMP_URL . 'assets/js/pfmp-palette.js', array(), PFMP_VERSION, true);
        wp_enqueue_script('pfmp-admin', PFMP_URL . 'assets/js/pfmp-admin.js', array('jquery', 'wp-color-picker'), PFMP_VERSION, true);
        wp_enqueue_script('pfmp-builder', PFMP_URL . 'assets/js/pfmp-builder.js', array('jquery', 'wp-color-picker', 'pfmp-palette', 'pfmp-admin'), PFMP_VERSION, true);

        wp_localize_script('pfmp-admin', 'PFMPAdmin', array(
            'choose'  => __('Select logo', 'pages-for-mailerpress'),
            'useThis' => __('Use this logo', 'pages-for-mailerpress'),
        ));

        $shadow_css = array();
        foreach (pfmp_shadow_presets() as $key => $preset) {
            $shadow_css[$key] = $preset['css'];
        }
        $live_urls = array();
        foreach (array_keys(pfmp_builder_states()) as $action) {
            $live_urls[$action] = pfmp_url_for_action($action);
        }
        $lang_natives = array();
        foreach (pfmp_supported_languages() as $code => $meta) {
            $lang_natives[$code] = $meta['native'];
        }
        wp_localize_script('pfmp-builder', 'PFMPBuilder', array(
            'shadows'     => $shadow_css,
            'liveUrls'    => $live_urls,
            'languages'   => $lang_natives,
            'i18nAuto'    => __('Auto', 'pages-for-mailerpress'),
            'i18nCustom'  => __('Custom', 'pages-for-mailerpress'),
            'chooseImage' => __('Select background image', 'pages-for-mailerpress'),
            'useImage'    => __('Use this image', 'pages-for-mailerpress'),
        ));
    }

    /**
     * The live URL for a given state, if MailerPress has a page resolvable
     * for it. Used only for the small optional "View live page" link, never
     * for the preview itself.
     */
    private function live_url($action) {
        return pfmp_url_for_action($action);
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $o       = pfmp_get_settings();
        $presets = PFMP_Palette::presets();
        $tokens  = PFMP_Palette::tokens();
        $states  = pfmp_builder_states();
        $auto    = PFMP_Palette::autoFlags($o['palette']['overrides']);
        ?>
        <div class="wrap pfmp-builder">
            <div class="pfmp-builder__top">
                <div>
                    <h1><?php esc_html_e('Pages for MailerPress', 'pages-for-mailerpress'); ?></h1>
                    <p class="pfmp-builder__sub"><?php esc_html_e('Style, translate, and preview the subscription and unsubscribe pages MailerPress shows your readers. Nothing here changes how MailerPress sends or stores data.', 'pages-for-mailerpress'); ?></p>
                </div>
            </div>

            <div class="pfmp-builder__toolbar">
                <div class="pfmp-builder__states" role="tablist">
                    <?php foreach ($states as $key => $label) : ?>
                        <button type="button" role="tab" aria-selected="<?php echo $key === 'manage' ? 'true' : 'false'; ?>" class="pfmp-builder__state <?php echo $key === 'manage' ? 'is-on' : ''; ?>" data-state="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="pfmp-builder__devices" role="group" aria-label="<?php esc_attr_e('Preview size', 'pages-for-mailerpress'); ?>">
                    <button type="button" class="pfmp-builder__device is-on" aria-pressed="true" data-device="desktop" aria-label="<?php esc_attr_e('Desktop', 'pages-for-mailerpress'); ?>">&#128421;</button>
                    <button type="button" class="pfmp-builder__device" aria-pressed="false" data-device="mobile" aria-label="<?php esc_attr_e('Mobile', 'pages-for-mailerpress'); ?>">&#128241;</button>
                </div>
            </div>

            <form id="pfmp-builder-form" method="post" action="options.php">
                <?php settings_fields(PFMP_Settings::GROUP); ?>

                <div class="pfmp-builder__layout">
                    <div class="pfmp-builder__stage">
                        <div class="pfmp-builder__frame-wrap" data-device="desktop">
                            <div class="pfmp-builder__canvas" id="pfmp-preview-canvas">
                                <?php foreach ($states as $key => $label) :
                                    $content = self::mock_content($key);
                                    echo '<div class="pfmp-preview-panel' . ($key === 'manage' ? ' is-active' : '') . '" data-preview-state="' . esc_attr($key) . '">'
                                        . PFMP_Frontend::render_wrapped($content, $key, true)
                                        . '</div>';
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p class="pfmp-builder__caption">
                            <?php esc_html_e('Simulated preview, built from the real page markup and styles. Changes apply instantly here and are only saved when you click Save.', 'pages-for-mailerpress'); ?>
                            <?php $live = $this->live_url('manage'); if ($live !== '') : ?>
                                <span class="pfmp-builder__caption-sep">&#183;</span> <a href="<?php echo esc_url($live); ?>" target="_blank" rel="noopener" id="pfmp-view-live"><?php esc_html_e('View the live page', 'pages-for-mailerpress'); ?></a>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="pfmp-builder__rail">
                        <details class="pfmp-accordion" open>
                            <summary><?php esc_html_e('Presets', 'pages-for-mailerpress'); ?></summary>
                            <div class="pfmp-builder__presets">
                                <input type="hidden" id="pfmp-preset-input" name="<?php echo esc_attr(PFMP_OPTION . '[preset]'); ?>" value="<?php echo esc_attr($o['preset']); ?>">
                                <?php foreach ($presets as $key => $preset) : ?>
                                    <button type="button" aria-pressed="<?php echo $o['preset'] === $key ? 'true' : 'false'; ?>" class="pfmp-builder__preset <?php echo $o['preset'] === $key ? 'is-on' : ''; ?>" data-preset="<?php echo esc_attr($key); ?>" data-accent="<?php echo esc_attr($preset['accent']); ?>" style="--swatch:<?php echo esc_attr($preset['accent']); ?>">
                                        <span class="pfmp-builder__swatch"></span>
                                        <?php echo esc_html($preset['label']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </details>

                        <?php $this->settings->render_identity_section($o); ?>

                        <details class="pfmp-accordion" open>
                            <summary><?php esc_html_e('Colors', 'pages-for-mailerpress'); ?></summary>
                            <div class="pfmp-builder__field">
                                <label for="pfmp-accent"><?php esc_html_e('Brand accent', 'pages-for-mailerpress'); ?></label>
                                <input type="text" id="pfmp-accent" class="pfmp-builder__color" name="<?php echo esc_attr(PFMP_OPTION . '[palette][accent]'); ?>" value="<?php echo esc_attr($o['palette']['accent']); ?>" data-default-color="<?php echo esc_attr(pfmp_defaults()['palette']['accent']); ?>">
                                <p class="description"><?php esc_html_e('Used to generate the accent, background tint, borders, and highlighted areas.', 'pages-for-mailerpress'); ?></p>
                            </div>

                            <h3 class="pfmp-builder__subhead"><?php esc_html_e('Page colors', 'pages-for-mailerpress'); ?></h3>
                            <?php foreach ($tokens as $key => $label) : ?>
                                <div class="pfmp-builder__token" data-token="<?php echo esc_attr($key); ?>">
                                    <span class="pfmp-builder__token-label"><?php echo esc_html($label); ?></span>
                                    <span class="pfmp-builder__token-state"><?php echo $auto[$key] ? esc_html__('Auto', 'pages-for-mailerpress') : esc_html__('Custom', 'pages-for-mailerpress'); ?></span>
                                    <input type="text" class="pfmp-builder__color pfmp-builder__color--token" data-token-input="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(PFMP_OPTION . '[palette][overrides][' . $key . ']'); ?>" value="<?php echo esc_attr($o['palette']['overrides'][$key]); ?>" placeholder="<?php esc_attr_e('Auto', 'pages-for-mailerpress'); ?>">
                                    <button type="button" class="pfmp-builder__clear" data-clear="<?php echo esc_attr($key); ?>"><?php esc_html_e('Clear', 'pages-for-mailerpress'); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </details>

                        <details class="pfmp-accordion">
                            <summary><?php esc_html_e('Card style', 'pages-for-mailerpress'); ?></summary>
                            <div class="pfmp-builder__field">
                                <label for="pfmp-radius"><?php esc_html_e('Corner radius', 'pages-for-mailerpress'); ?> <span id="pfmp-radius-value" class="pfmp-builder__value"><?php echo esc_html($o['style']['radius']); ?>px</span></label>
                                <input type="range" id="pfmp-radius" min="0" max="32" step="1" name="<?php echo esc_attr(PFMP_OPTION . '[style][radius]'); ?>" value="<?php echo esc_attr($o['style']['radius']); ?>">
                            </div>

                            <div class="pfmp-builder__field">
                                <label for="pfmp-shadow"><?php esc_html_e('Shadow', 'pages-for-mailerpress'); ?></label>
                                <select id="pfmp-shadow" name="<?php echo esc_attr(PFMP_OPTION . '[style][shadow]'); ?>">
                                    <?php foreach (pfmp_shadow_presets() as $key => $preset) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($o['style']['shadow'], $key); ?>><?php echo esc_html($preset['label']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="pfmp-builder__field">
                                <label><?php esc_html_e('Background image', 'pages-for-mailerpress'); ?></label>
                                <div class="pfmp-builder__bg-row">
                                    <div class="pfmp-builder__bg-prev" id="pfmp-bg-prev">
                                        <?php if (!empty($o['style']['background_image']['url'])) : ?>
                                            <img src="<?php echo esc_url($o['style']['background_image']['url']); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <div class="pfmp-builder__bg-actions">
                                        <button type="button" class="button" id="pfmp-bg-pick"><?php esc_html_e('Select image', 'pages-for-mailerpress'); ?></button>
                                        <button type="button" class="button-link-delete" id="pfmp-bg-clear" <?php echo empty($o['style']['background_image']['url']) ? 'style="display:none"' : ''; ?>><?php esc_html_e('Remove', 'pages-for-mailerpress'); ?></button>
                                    </div>
                                </div>
                                <input type="hidden" id="pfmp-bg-id"  name="<?php echo esc_attr(PFMP_OPTION . '[style][background_image][id]'); ?>"  value="<?php echo esc_attr($o['style']['background_image']['id']); ?>">
                                <input type="hidden" id="pfmp-bg-url" name="<?php echo esc_attr(PFMP_OPTION . '[style][background_image][url]'); ?>" value="<?php echo esc_attr($o['style']['background_image']['url']); ?>">
                                <label class="pfmp-builder__inline-check"><input type="checkbox" id="pfmp-bg-cover" name="<?php echo esc_attr(PFMP_OPTION . '[style][background_image][cover]'); ?>" value="1" <?php checked(!empty($o['style']['background_image']['cover'])); ?>> <?php esc_html_e('Fill the screen', 'pages-for-mailerpress'); ?></label>
                                <label class="pfmp-builder__inline-check"><input type="checkbox" id="pfmp-bg-repeat" name="<?php echo esc_attr(PFMP_OPTION . '[style][background_image][repeat]'); ?>" value="1" <?php checked(!empty($o['style']['background_image']['repeat'])); ?>> <?php esc_html_e('Tile instead', 'pages-for-mailerpress'); ?></label>
                                <p class="description"><?php esc_html_e('Most visible with Full-page mode on, where it fills the space around the card.', 'pages-for-mailerpress'); ?></p>
                            </div>
                        </details>

                        <?php
                        $this->settings->render_pages_section($o);
                        $this->settings->render_languages_section($o);
                        $this->settings->render_advanced_section($o);
                        ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Placeholder content for a given state, mirroring MailerPress's real
     * markup and classes exactly (.mailerpress-manage-subscription,
     * .mailerpress-form-line, etc.) so the plugin's real stylesheet applies
     * identically to this preview and to the live page. Not MailerPress's
     * actual copy: only the structure and classes need to match for the
     * preview to be visually accurate, and using our own placeholder text
     * avoids the preview going stale if MailerPress's wording changes.
     */
    private static function mock_content($action) {
        switch ($action) {
            case 'confirm':
                return '<p>' . esc_html__('You have successfully confirmed your subscription.', 'pages-for-mailerpress') . '</p>';

            case 'confirm_unsubscribe':
                return '<p>' . esc_html__('Are you sure you want to unsubscribe from these emails?', 'pages-for-mailerpress')
                     . '<br><a href="#" onclick="return false">' . esc_html__('Yes, unsubscribe me', 'pages-for-mailerpress') . '</a></p>';

            case 'unsubscribe':
                return '<p>' . esc_html__('You have successfully unsubscribed from our emails.', 'pages-for-mailerpress') . '</p>';

            case 'manage':
            default:
                return self::mock_manage_form();
        }
    }

    private static function mock_manage_form() {
        $lists = array(
            array('label' => __('Weekly Digest', 'pages-for-mailerpress'), 'checked' => true),
            array('label' => __('Product Updates', 'pages-for-mailerpress'), 'checked' => true),
            array('label' => __('Special Offers', 'pages-for-mailerpress'), 'checked' => false),
        );

        $html = '<form class="mailerpress-manage-subscription" onsubmit="return false">';
        $html .= '<p class="mailerpress-change-email-info">' . esc_html__('Uncheck lists to unsubscribe from them, then save your preferences.', 'pages-for-mailerpress') . '</p>';
        $html .= '<div class="mailerpress-form-line"><label>' . esc_html__('Email*', 'pages-for-mailerpress') . '</label><strong>reader@example.com</strong></div>';
        $html .= '<div class="mailerpress-form-line"><label for="pfmp-mock-status">' . esc_html__('Subscription status', 'pages-for-mailerpress') . '</label>'
               . '<select id="pfmp-mock-status"><option selected>' . esc_html__('Subscribed', 'pages-for-mailerpress') . '</option><option>' . esc_html__('Unsubscribed', 'pages-for-mailerpress') . '</option></select></div>';
        $html .= '<div class="mailerpress-form-line"><label>' . esc_html__('First Name', 'pages-for-mailerpress') . '</label><input type="text" value="Alex"></div>';
        $html .= '<div class="mailerpress-form-line"><label>' . esc_html__('Last Name', 'pages-for-mailerpress') . '</label><input type="text" value="Rivera"></div>';
        $html .= '<div class="mailerpress-form-line"><label>' . esc_html__('Manage your subscriptions', 'pages-for-mailerpress') . '</label>';
        foreach ($lists as $list) {
            $html .= '<div><label><input type="checkbox"' . ($list['checked'] ? ' checked' : '') . '> ' . esc_html($list['label']) . '</label></div>';
        }
        $html .= '</div>';
        $html .= '<button type="button" class="mailerpress-submit-btn">' . esc_html__('Save', 'pages-for-mailerpress') . '</button>';
        $html .= '</form>';

        return $html;
    }
}
