# Pages for MailerPress

**Repository:** [github.com/jaimealnassim/pages-for-mailerpress](https://github.com/jaimealnassim/pages-for-mailerpress)

A companion plugin for [MailerPress](https://mailerpress.com) that restyles, translates, and
live-previews the subscription and unsubscribe pages MailerPress shows your readers, logo,
centered layout, color and card-style controls, and an on-page 30-language switcher, all
layered on top of MailerPress's own markup with no core files touched.

**This plugin requires MailerPress Pro.** See [Background](#background) below for why.

## Background

This started as an internal plugin, built to restyle MailerPress's subscription and unsubscribe
pages before MailerPress itself offered any way to do that. We shared an early build directly
with the MailerPress team as a demonstration.

We held off publishing it while MailerPress built out their own equivalent feature. That
happened in **MailerPress Pro 2.0.7**, which added a live-preview "MailerPress Pages" style
editor of its own: presets, one-color palette derivation, corner radius, shadow, and a
background image.

Now that MailerPress Pro has shipped its own version of this, we're publishing ours too, but out
of respect for their team and the feature they built, **this plugin now requires MailerPress Pro
to be installed, active, and licensed**. It does nothing at all without it. This isn't a free
substitute for their work, it's an alternative built alongside it, and it leans on Pro's own
`mailerpress_get_page()` API rather than reimplementing MailerPress's page-resolution logic
ourselves.

What this plugin adds on top of what Pro already offers:

- **A 30-language, no-reload switcher.** Readers can change the page's language on the spot,
  including full right-to-left layout for Arabic, Hebrew, and Persian. Pro's style editor does
  not offer translation.
- **Full-page mode**, which removes the surrounding theme's header, footer, and stylesheets
  entirely, so the page is just the card.
- **An accordion-based settings screen** that keeps every control, presets, colors, card style,
  branding, languages, page behavior, in one continuous flow next to a live, same-page preview
  that can never 404, since it doesn't depend on fetching the real page.

## Requirements

- WordPress 7.1+
- PHP 8.3+
- [MailerPress](https://mailerpress.com) (free)
- **MailerPress Pro** (paid, licensed separately from MailerPress)

WordPress will not activate the plugin without MailerPress (the `Requires Plugins` header). That
header can only name plugins in the WordPress.org directory, and MailerPress Pro is sold separately,
so the plugin checks for Pro itself: without it, it shows a notice explaining what's missing and does
nothing else.

## Installation

1. Clone or download this repository:
   `git clone https://github.com/jaimealnassim/pages-for-mailerpress.git`
2. Zip the `pages-for-mailerpress` folder, or copy it directly into `wp-content/plugins/`.
3. Activate **MailerPress**, **MailerPress Pro**, and then **Pages for MailerPress**, in that
   order.
4. Go to **MailerPress → Pages** (or **Settings → Pages for MailerPress** if the MailerPress
   admin menu isn't present) to open the Builder.

## What it does

- Detects MailerPress's manage/subscribe page and unsubscribe page (custom pages you've picked,
  or the page MailerPress creates automatically) via `mailerpress_get_page()`.
- Wraps the `[mailerpress_pages]` shortcode output in a branded, centered (or left-aligned) card,
  styled entirely through the classes MailerPress already outputs
  (`.mailerpress-manage-subscription`, `.mailerpress-form-line`, `.mailerpress-submit-btn`, etc.).
- Derives a full color palette, background, card background, text, muted text, borders, from a
  single accent color, with per-token manual overrides.
- Simulates all five MailerPress page states (Manage, Confirmed, Re-engagement, Confirm
  unsubscribe, Unsubscribed) in the Builder using MailerPress's real markup and copy, MailerPress's
  own page stylesheet, and this plugin's real stylesheet, so the preview is accurate without ever
  loading the live page. The language buttons in the preview really translate it.
- Translates MailerPress's default page strings into 30 languages, entirely in the browser, no
  page reload. Text MailerPress adds after the page loads, like "Saving…" and the message after
  Save, is translated as it appears, and so is the page title in the browser tab.

## Works with MailerPress 2.2

Tested with MailerPress 2.2.1 and MailerPress Pro 2.2.1.

- Hooks MailerPress's own `mailerpress_pages_rendered_content` filter (falls back to
  `the_content` on versions before 2.0.7, or force either path with the `pfmp_legacy_wrap`
  filter).
- Styles the new subscription list cards through MailerPress's own `--mailerpress-pages-*`
  color variables, so they follow your brand colors.
- Supports the new re-engagement confirmation page.
- **If MailerPress Pro's page design is turned on**, this plugin steps back and adds only the
  language buttons and translations on top of Pro's design (translate-only mode). Turn Pro's
  design off to use this plugin's card, colors, and branding instead.
- The "Prefer fewer emails instead?" link carries the reader's access token, so the manage page
  opens for them instead of showing a "page not found" error. It only shows up when the token is
  there.

## For developers

Checked with WordPress Coding Standards (security, i18n, PHP 8.3 compatibility) and PHPStan
level 6 against the WordPress stubs.

- `pfmp_dictionary` filter: add or change translations. Receives
  `array( 'es' => array( 'English string' => 'Translation' ), ... )`.
- `pfmp_legacy_wrap` filter: force the old `the_content` wrapping on or off.
- `pfmp_track` AJAX action: the anonymous Insights counter. It only accepts a fixed list of
  events and keys, and caps itself at 1,000 events an hour.

## New in 0.9

- **Reader pages redesigned** for phone, tablet and desktop, with a headline on every page, a
  compact language menu, a two-option Subscribed/Unsubscribed switch, dark mode, and footer links.
- **Before they leave:** a "Get fewer emails instead" option, optional one-tap reasons, and a
  Resubscribe button on the Unsubscribed page (the plugin carries the reader's access token onto
  MailerPress's unsubscribe link so the next page can offer it).
- **Newsletter details:** a description and a "how often" label for each MailerPress list.
- **Insights:** monthly totals only, no personal data, stored in the `pfmp_stats` option.
- **Settings screen rebuilt:** grouped sections with summaries, color pickers with readability
  scores, search, preview in any language at desktop, tablet or phone size in light or dark, a
  change counter with Discard, and import/export.

## What it doesn't do

- It doesn't touch how MailerPress sends email, stores contacts, or manages lists.
- It doesn't unlock, modify, or interact with MailerPress Pro's licensing in any way. It simply
  checks whether Pro is active, the same way it checks for MailerPress itself.
- It isn't distributed through, or affiliated with, MailerPress or Team MailerPress.

## License

GPLv3 or later. See [LICENSE](LICENSE).

## Support

This is an internally-maintained plugin, not a supported product. Issues and pull requests are
welcome at [github.com/jaimealnassim/pages-for-mailerpress/issues](https://github.com/jaimealnassim/pages-for-mailerpress/issues),
but there's no guaranteed response time.
