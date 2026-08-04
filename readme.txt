=== Pages for MailerPress ===
Contributors: nahnuplugins
Tags: mailerpress, newsletter, unsubscribe, translation, rtl
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.6.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Restyle and translate the MailerPress subscription and unsubscribe pages, with an on-page 30-language switcher. Requires MailerPress Pro.

== Description ==

Pages for MailerPress is a companion plugin for MailerPress and MailerPress
Pro. It requires both to be installed, active, and licensed; it does nothing
on its own. It gives the four pages MailerPress shows your readers a modern,
branded, centered look. Those are manage subscription, confirm unsubscribe,
unsubscribed, and subscribed. It also lets readers switch language on the page
with no reload, including right-to-left support for Arabic, Hebrew, and
Persian.

It works entirely on top of the markup MailerPress already outputs. No MailerPress
files are edited, and nothing here changes how MailerPress sends email or stores data.

Features:

* A modern, centered card design applied to all four MailerPress page states.
* Your logo, publication name, and tagline at the top of every page.
* The Builder: a simulated preview, built from MailerPress's real markup and
  the plugin's actual stylesheet, next to an accordion of presets, one-color
  palette derivation, and card style. Every change applies instantly to the
  preview; nothing is saved until you click Save.
* Card style controls in the Builder: corner radius, shadow, and an optional
  background image.
* On-page language switcher covering 30 languages, with instant, no-reload
  switching and right-to-left layout for Arabic, Hebrew, and Persian.
* Optional full-page mode that hides the theme header, footer, and CSS.
* Optional "choose fewer emails" off-ramp linking the confirm-unsubscribe page to
  the manage page.
* Assets load only on MailerPress pages. Optional custom CSS.
* No external requests: type uses the visitor's system fonts, and no third-party
  services are contacted.

== How it works ==

The plugin detects a MailerPress page (by the [mailerpress_pages] shortcode or the
pages configured in MailerPress), enqueues a scoped stylesheet that targets the
classes MailerPress outputs (for example .mailerpress-manage-subscription and
.mailerpress-submit-btn), and wraps the output with a branded, centered container.

The language switcher translates MailerPress's default English strings in the
browser, with no page reload, using a dictionary shipped with the plugin. If your
MailerPress runs in a non-English base language, those strings won't match and are
left as-is. List names and contact names come from the database and are shown
exactly as stored.

== Installation ==

1. Upload the `pages-for-mailerpress` folder to `/wp-content/plugins/`, or install the
   zip from Plugins -> Add New -> Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Go to Settings -> Pages for MailerPress (or MailerPress -> Pages) to set your logo,
   colors, and languages.

== Frequently Asked Questions ==

= Does this require MailerPress? =

Yes, both MailerPress and MailerPress Pro. It only styles and translates
MailerPress's pages, so it has no effect without them installed, active, and
licensed.

= Does it change how MailerPress sends email or stores subscribers? =

No. It only affects how the subscription and unsubscribe pages look and read. All
sending, storage, and list logic stays with MailerPress.

= Does the plugin make any external requests? =

No. Type uses the visitor's own system fonts, and the plugin contacts no third-party
services. Nothing is loaded from an outside URL.

= How does translation work without a page reload? =

The plugin ships a dictionary of MailerPress's default page strings in 30 languages.
When a reader taps a language, the text is swapped in the browser and, for
right-to-left languages, the layout is mirrored. No page reload and no external
translation service.

= Some text on the page did not translate. Why? =

The dictionary matches MailerPress's default English strings. If your MailerPress
runs in another base language, or you have customized those strings, they will not
match and are left untouched. List names and subscriber names are stored data and
are always shown as saved.

= Will it work with my theme? =

The styling is scoped to the MailerPress pages and layers over MailerPress's own
markup, so it stays out of the way of the rest of your site.

== Changelog ==

= 0.6.3 =
Internal project. First made public in this version.

