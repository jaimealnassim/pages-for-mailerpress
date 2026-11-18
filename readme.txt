=== Pages for MailerPress ===
Contributors: nahnuplugins
Tags: mailerpress, newsletter, unsubscribe, translation, rtl
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.9.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Restyle and translate the MailerPress subscription and unsubscribe pages, with an on-page 30-language switcher. Requires MailerPress Pro.

== Description ==

Pages for MailerPress is a companion plugin for MailerPress and MailerPress
Pro. It requires both to be installed, active, and licensed; it does nothing
on its own. It gives the pages MailerPress shows your readers a modern,
branded, centered look. Those are manage subscription, confirm unsubscribe,
unsubscribed, subscribed, and re-engagement confirmed. It also lets readers switch language on the page
with no reload, including right-to-left support for Arabic, Hebrew, and
Persian.

It works entirely on top of the markup MailerPress already outputs. No MailerPress
files are edited, and nothing here changes how MailerPress sends email or stores data.

Features:

* A modern card design for every MailerPress page, laid out for phones,
  tablets and desktops. On desktop the Manage page uses two columns.
* A headline on every page, translated, or your own wording.
* Your logo, publication name, tagline, and footer links (privacy, contact).
* Theme colors from one brand color, with color pickers for every color and a
  readability score for text. Dark mode: off, follow the device, or always.
* A compact language menu (or a row of buttons) covering 30 languages, with
  instant, no-reload switching and right-to-left layout for Arabic, Hebrew,
  and Persian.
* Before they leave: offer fewer emails instead, ask why (optional, one tap),
  and offer Resubscribe after unsubscribing, plus a link to your latest issue.
* Newsletter descriptions and "how often" labels on the Manage page.
* Insights: monthly totals of what readers do on the pages. No personal data.
* A settings screen with a live preview (desktop, tablet, phone, light, dark,
  any language), search, a change counter with Discard, and import/export.
* Works alongside MailerPress Pro's page design: when Pro's design is on, the
  plugin adds only the language control and translations.
* Optional full-page mode that hides the theme header, footer, and CSS.
* No external requests: type uses the visitor's system fonts, and no
  third-party services are contacted.

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

= I use MailerPress Pro's page design. What happens? =

Your pages keep Pro's design. This plugin adds only the language buttons and
the translations on top of it. To use this plugin's card, colors, and branding
instead, turn off page design in MailerPress Pro.

= What does Insights store? =

Only monthly totals, such as how many readers chose fewer emails or gave a
reason. No email addresses, IP addresses or cookies. Turn it off under
Readers, Insights, or clear the counts there.

= Will it work with my theme? =

The styling is scoped to the MailerPress pages and layers over MailerPress's own
markup, so it stays out of the way of the rest of your site.

== Changelog ==

= 0.9.2 =
Internal project. First made public in this version.
