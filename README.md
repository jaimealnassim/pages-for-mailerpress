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

- WordPress 6.0+
- PHP 7.4+
- [MailerPress](https://mailerpress.com) (free)
- **MailerPress Pro** (paid, licensed separately from MailerPress)

The plugin will not activate without both MailerPress and MailerPress Pro present (enforced via
the `Requires Plugins` header), and does nothing if either is deactivated afterward.

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
- Simulates all four MailerPress page states (Manage, Confirmed, Confirm unsubscribe,
  Unsubscribed) in the Builder using MailerPress's real markup and this plugin's real stylesheet,
  so the preview is accurate without ever loading the live page.
- Translates MailerPress's default page strings into 30 languages, entirely in the browser, no
  page reload.

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
