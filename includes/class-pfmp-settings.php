<?php
defined('ABSPATH') || exit;

/**
 * Option registration and sanitization, plus the General/Branding/Languages/
 * Advanced field markup. Rendered inside PFMP_Builder's single admin page,
 * not its own menu item, so the plugin has exactly one place to live.
 */
class PFMP_Settings {

    const GROUP = 'pfmp_settings_group';

    public function __construct() {
        add_action('admin_init', array($this, 'register'));
    }

    public function register() {
        register_setting(self::GROUP, PFMP_OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => pfmp_defaults(),
        ));
    }

    /**
     * Validate and clean every field before it is stored. Everything lives
     * on one form now, so this always has the full picture; unset checkbox
     * fields still fall back to the current saved value first, then the
     * default, so a stray partial submission (e.g. from the REST API)
     * can't silently zero settings it never touched.
     */
    public function sanitize($input) {
        $current = pfmp_get_settings();
        $d       = pfmp_defaults();
        $out     = array();

        $out['enabled']  = empty($input['enabled']) ? 0 : 1;
        $out['layout']   = (isset($input['layout']) && $input['layout'] === 'left') ? 'left' : 'centered';
        $out['offramp']  = empty($input['offramp']) ? 0 : 1;
        $out['takeover'] = empty($input['takeover']) ? 0 : 1;

        $out['logo_id']  = isset($input['logo_id']) ? absint($input['logo_id']) : 0;
        $out['logo_url'] = isset($input['logo_url']) ? esc_url_raw(trim($input['logo_url'])) : '';
        $out['pub_name'] = isset($input['pub_name']) ? sanitize_text_field($input['pub_name']) : $current['pub_name'];
        $out['tagline']  = isset($input['tagline']) ? sanitize_text_field($input['tagline']) : '';

        $out['lang_switcher'] = empty($input['lang_switcher']) ? 0 : 1;

        $allowed = array_keys(pfmp_supported_languages());
        $langs   = isset($input['languages']) && is_array($input['languages']) ? $input['languages'] : array();
        $langs   = array_values(array_intersect($allowed, array_map('sanitize_key', $langs)));
        if (!in_array('en', $langs, true)) {
            array_unshift($langs, 'en'); // English is always available as the base.
        }
        $out['languages'] = $langs;

        $def_choices = array_merge(array('locale'), array_keys(pfmp_supported_languages()));
        $out['default_lang']   = (isset($input['default_lang']) && in_array($input['default_lang'], $def_choices, true)) ? $input['default_lang'] : 'locale';
        $out['allow_url_lang'] = empty($input['allow_url_lang']) ? 0 : 1;

        $out['assets_scoped']     = empty($input['assets_scoped']) ? 0 : 1;
        $out['custom_css']        = isset($input['custom_css']) ? wp_strip_all_tags($input['custom_css']) : '';
        $out['uninstall_cleanup'] = empty($input['uninstall_cleanup']) ? 0 : 1;

        $presets       = array_keys(PFMP_Palette::presets());
        $out['preset'] = (isset($input['preset']) && in_array($input['preset'], $presets, true)) ? $input['preset'] : 'custom';

        $accent_in      = isset($input['palette']['accent']) ? $input['palette']['accent'] : '';
        $out['palette'] = array(
            'accent'    => $this->clean_hex($accent_in, $d['palette']['accent']),
            'overrides' => array(),
        );
        $overrides_in = isset($input['palette']['overrides']) && is_array($input['palette']['overrides'])
            ? $input['palette']['overrides']
            : array();
        foreach (array_keys(PFMP_Palette::tokens()) as $token) {
            $value = isset($overrides_in[$token]) ? sanitize_hex_color($overrides_in[$token]) : '';
            $out['palette']['overrides'][$token] = $value ? $value : '';
        }

        $shadows      = array_keys(pfmp_shadow_presets());
        $style_in     = isset($input['style']) && is_array($input['style']) ? $input['style'] : array();
        $out['style'] = array(
            'radius' => isset($style_in['radius']) ? max(0, min(32, absint($style_in['radius']))) : $d['style']['radius'],
            'shadow' => (isset($style_in['shadow']) && in_array($style_in['shadow'], $shadows, true)) ? $style_in['shadow'] : $d['style']['shadow'],
            'background_image' => array(
                'id'     => isset($style_in['background_image']['id']) ? absint($style_in['background_image']['id']) : 0,
                'url'    => isset($style_in['background_image']['url']) ? esc_url_raw(trim($style_in['background_image']['url'])) : '',
                'repeat' => empty($style_in['background_image']['repeat']) ? 0 : 1,
                'cover'  => empty($style_in['background_image']['cover']) ? 0 : 1,
            ),
        );

        return $out;
    }

    private function clean_hex($value, $fallback) {
        $value = sanitize_hex_color($value);
        return $value ? $value : $fallback;
    }

    /* --------------------------------------------------------------------- */
    /* Field markup: General / Branding / Languages / Advanced               */
    /* --------------------------------------------------------------------- */

    private function name($key) {
        return PFMP_OPTION . '[' . $key . ']';
    }

    private function checkbox($o, $key, $label, $desc = '', $id = '') {
        $id_attr = $id ? ' id="' . esc_attr($id) . '"' : '';
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s%5$s> %3$s</label>%4$s',
            esc_attr($this->name($key)),
            checked(!empty($o[$key]), true, false),
            wp_kses_post($label),
            $desc ? '<p class="description">' . wp_kses_post($desc) . '</p>' : '',
            $id_attr
        );
    }

    /**
     * Each of these renders one <details> accordion section. Split out so
     * the Builder can interleave them with its own Presets/Colors/Card
     * style sections in one continuous, logically-ordered list rather than
     * two visually separate stacks. Expected to be called from inside an
     * already-open <form> on the Builder page; none of them include a
     * wrapping <div class="wrap"> or a submit button.
     */
    public function render_pages_section($o) {
        $custom_pages = pfmp_mailerpress_custom_pages();
        $page_rows    = array(
            'manage'      => array('label' => __('Manage / subscribe page', 'pages-for-mailerpress'), 'action' => 'manage'),
            'unsubscribe' => array('label' => __('Unsubscribe page', 'pages-for-mailerpress'),         'action' => 'confirm_unsubscribe'),
        );
        ?>
        <details class="pfmp-accordion">
            <summary><?php esc_html_e('Pages and behavior', 'pages-for-mailerpress'); ?></summary>
            <table class="form-table" role="presentation"><tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Enhanced pages', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'enabled', __('Apply the modern design to MailerPress pages', 'pages-for-mailerpress'), __('When off, MailerPress falls back to its default page output.', 'pages-for-mailerpress')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Pages in use', 'pages-for-mailerpress'); ?></th>
                    <td>
                        <?php if (!function_exists('mailerpress_get_page')) : ?>
                            <p class="description"><?php esc_html_e('MailerPress isn\'t active, so pages can\'t be resolved yet.', 'pages-for-mailerpress'); ?></p>
                        <?php else : ?>
                            <ul class="pfmp-pagelist">
                                <?php foreach ($page_rows as $role => $row) :
                                    $url    = pfmp_url_for_action($row['action']);
                                    $is_custom = !empty($custom_pages[$role]);
                                    ?>
                                    <li>
                                        <strong><?php echo esc_html($row['label']); ?></strong>
                                        <?php if ($url !== '') : ?>
                                            <code><?php echo esc_html(wp_make_link_relative(remove_query_arg(array('action'), $url))); ?></code>
                                            <span class="description" style="display:inline"><?php echo $is_custom ? esc_html__('(your page)', 'pages-for-mailerpress') : esc_html__('(MailerPress default)', 'pages-for-mailerpress'); ?></span>
                                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'pages-for-mailerpress'); ?></a>
                                        <?php else : ?>
                                            <span class="description" style="display:inline"><?php esc_html_e('Not resolved yet.', 'pages-for-mailerpress'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="description"><?php esc_html_e('Read directly from MailerPress\'s own settings, whether that\'s a page you picked or the page MailerPress creates automatically.', 'pages-for-mailerpress'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Layout', 'pages-for-mailerpress'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="<?php echo esc_attr($this->name('layout')); ?>" value="centered" <?php checked($o['layout'], 'centered'); ?>> <?php esc_html_e('Centered', 'pages-for-mailerpress'); ?> <span class="description" style="display:inline"><?php esc_html_e('(recommended)', 'pages-for-mailerpress'); ?></span></label>
                            <label><input type="radio" name="<?php echo esc_attr($this->name('layout')); ?>" value="left" <?php checked($o['layout'], 'left'); ?>> <?php esc_html_e('Left-aligned', 'pages-for-mailerpress'); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Unsubscribe off-ramp', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'offramp', __('Offer &#8220;choose fewer emails&#8221; before unsubscribing', 'pages-for-mailerpress'), __('Adds a link from the confirm page to the manage page, so readers can reduce rather than leave.', 'pages-for-mailerpress')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Full-page mode', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'takeover', __('Take over the whole page', 'pages-for-mailerpress'), __('Renders these pages on their own, without the theme\'s header, footer, or stylesheets, so the reader sees only this card. Turn on if your theme chrome is showing around the page.', 'pages-for-mailerpress')); ?></td>
                </tr>
            </tbody></table>
        </details>
        <?php
    }

    /**
     * Logo, publication name, and tagline: the identity half of branding.
     * Meant to sit directly beside the Builder's own Colors and Card style
     * sections, since together they're all "how the page looks."
     */
    public function render_identity_section($o) {
        ?>
        <details class="pfmp-accordion" open>
            <summary><?php esc_html_e('Logo and name', 'pages-for-mailerpress'); ?></summary>
            <table class="form-table" role="presentation"><tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Logo', 'pages-for-mailerpress'); ?></th>
                    <td>
                        <div class="pfmp-logo-row">
                            <div class="pfmp-logo-prev">
                                <?php if (!empty($o['logo_url'])) : ?>
                                    <img src="<?php echo esc_url($o['logo_url']); ?>" alt="">
                                <?php else : ?>
                                    <span><?php echo esc_html(pfmp_first_char($o['pub_name'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="pfmp-logo-pick"><?php esc_html_e('Select logo', 'pages-for-mailerpress'); ?></button>
                            <button type="button" class="button-link-delete" id="pfmp-logo-clear" <?php echo empty($o['logo_url']) ? 'style="display:none"' : ''; ?>><?php esc_html_e('Remove', 'pages-for-mailerpress'); ?></button>
                        </div>
                        <input type="hidden" id="pfmp-logo-id"  name="<?php echo esc_attr($this->name('logo_id')); ?>"  value="<?php echo esc_attr($o['logo_id']); ?>">
                        <input type="hidden" id="pfmp-logo-url" name="<?php echo esc_attr($this->name('logo_url')); ?>" value="<?php echo esc_attr($o['logo_url']); ?>">
                        <p class="description"><?php esc_html_e('Shown at the top of every page. PNG or SVG, around 120px tall.', 'pages-for-mailerpress'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="pfmp-pub"><?php esc_html_e('Publication name', 'pages-for-mailerpress'); ?></label></th>
                    <td><input type="text" id="pfmp-pub" class="regular-text" name="<?php echo esc_attr($this->name('pub_name')); ?>" value="<?php echo esc_attr($o['pub_name']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="pfmp-tag"><?php esc_html_e('Tagline', 'pages-for-mailerpress'); ?></label></th>
                    <td>
                        <input type="text" id="pfmp-tag" class="regular-text" name="<?php echo esc_attr($this->name('tagline')); ?>" value="<?php echo esc_attr($o['tagline']); ?>">
                        <p class="description"><?php esc_html_e('Small line under the name. Leave blank to hide.', 'pages-for-mailerpress'); ?></p>
                    </td>
                </tr>
            </tbody></table>
        </details>
        <?php
    }

    public function render_languages_section($o) {
        $supported = pfmp_supported_languages();
        ?>
        <details class="pfmp-accordion">
            <summary><?php esc_html_e('Languages', 'pages-for-mailerpress'); ?></summary>
            <table class="form-table" role="presentation"><tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Language switcher', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'lang_switcher', __('Let readers switch language on the page', 'pages-for-mailerpress'), __('Shows a row of language buttons. Switching is instant, and the page does not reload.', 'pages-for-mailerpress'), 'pfmp-langtoggle'); ?></td>
                </tr>
                <tr class="pfmp-langdep">
                    <th scope="row"><?php esc_html_e('Offered languages', 'pages-for-mailerpress'); ?></th>
                    <td>
                        <fieldset class="pfmp-lang-grid">
                            <?php foreach ($supported as $code => $meta) :
                                $checked = in_array($code, (array) $o['languages'], true);
                                $is_en   = ($code === 'en');
                                ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($this->name('languages') . '[]'); ?>" value="<?php echo esc_attr($code); ?>" <?php checked($checked || $is_en); ?> <?php disabled($is_en); ?>>
                                    <?php echo esc_html($meta['native'] . ' (' . $meta['label'] . ')'); ?>
                                    <?php if ($is_en) : ?><span class="description" style="display:inline"><?php esc_html_e('(site default)', 'pages-for-mailerpress'); ?></span><?php endif; ?>
                                    <?php if (!empty($meta['rtl'])) : ?><span class="description" style="display:inline"><?php esc_html_e('(RTL)', 'pages-for-mailerpress'); ?></span><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description"><?php esc_html_e('30 languages ship with the plugin, translating MailerPress\'s default strings in the browser with no reload. Right-to-left languages (Arabic, Hebrew, Persian) flip the layout automatically. List and contact names come from the database as stored.', 'pages-for-mailerpress'); ?></p>
                    </td>
                </tr>
                <tr class="pfmp-langdep">
                    <th scope="row"><label for="pfmp-deflang"><?php esc_html_e('Fallback language', 'pages-for-mailerpress'); ?></label></th>
                    <td>
                        <select id="pfmp-deflang" name="<?php echo esc_attr($this->name('default_lang')); ?>">
                            <option value="locale" <?php selected($o['default_lang'], 'locale'); ?>><?php esc_html_e('WordPress site language', 'pages-for-mailerpress'); ?></option>
                            <?php foreach ($supported as $code => $meta) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($o['default_lang'], $code); ?>><?php echo esc_html($meta['native'] . ' (' . $meta['label'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Each visitor sees their browser language automatically when you offer it. This is what they see when their browser language is not one you offer.', 'pages-for-mailerpress'); ?></p>
                    </td>
                </tr>
                <tr class="pfmp-langdep">
                    <th scope="row"><?php esc_html_e('URL override', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'allow_url_lang', __('Allow a <code>?lang=</code> value in the link to preset the language', 'pages-for-mailerpress'), __('Handy for sending an Arabic list a link that opens in Arabic.', 'pages-for-mailerpress')); ?></td>
                </tr>
            </tbody></table>
        </details>
        <?php
    }

    public function render_advanced_section($o) {
        ?>
        <details class="pfmp-accordion">
            <summary><?php esc_html_e('Advanced', 'pages-for-mailerpress'); ?></summary>
            <table class="form-table" role="presentation"><tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Asset loading', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'assets_scoped', __('Load styles and scripts only on MailerPress pages', 'pages-for-mailerpress'), __('Keeps the rest of your site untouched. Recommended.', 'pages-for-mailerpress')); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="pfmp-css"><?php esc_html_e('Custom CSS', 'pages-for-mailerpress'); ?></label></th>
                    <td>
                        <textarea id="pfmp-css" class="large-text code" rows="8" name="<?php echo esc_attr($this->name('custom_css')); ?>" placeholder=".mailerpress-manage-subscription .button { letter-spacing:.02em; }"><?php echo esc_textarea($o['custom_css']); ?></textarea>
                        <p class="description"><?php esc_html_e('Targets the classes MailerPress outputs. Loaded last, so it wins.', 'pages-for-mailerpress'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Uninstall', 'pages-for-mailerpress'); ?></th>
                    <td><?php $this->checkbox($o, 'uninstall_cleanup', __('Delete all of this plugin\'s settings when it is removed', 'pages-for-mailerpress'), __('Off by default, so your design survives a reinstall.', 'pages-for-mailerpress')); ?></td>
                </tr>
            </tbody></table>
        </details>
        <?php
    }
}
