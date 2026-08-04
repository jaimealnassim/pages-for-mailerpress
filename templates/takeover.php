<?php
/**
 * Full-page (takeover) template for MailerPress pages.
 *
 * Renders the subscription/unsubscribe page on its own, with no theme header,
 * footer, or sidebars. wp_head()/wp_footer() still fire so MailerPress (and
 * our own) assets load; the theme's stylesheets are dequeued separately in
 * PFMP_Frontend::strip_theme_assets().
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('pfmp-takeover'); ?>>
    <main class="pfmp-takeover-main">
        <?php
        while (have_posts()) {
            the_post();
            the_content();
        }
        ?>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
