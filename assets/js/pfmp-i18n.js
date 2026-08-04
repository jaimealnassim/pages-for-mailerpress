/* Pages for MailerPress: on-page language switching, no reload.
 * Translates MailerPress's default English strings via a shipped dictionary
 * and flips direction for right-to-left languages. */
(function () {
  'use strict';
  if (typeof window.PFMP === 'undefined') { return; }

  var cfg   = window.PFMP;
  var wrap  = document.querySelector('.pfmp-wrap');
  if (!wrap) { return; }

  var langs = Array.isArray(cfg.languages) ? cfg.languages : ['en'];
  var rtl   = Array.isArray(cfg.rtl) ? cfg.rtl : [];
  var dict  = cfg.dict || {};

  // Snapshot translatable text nodes once (skip the switcher, scripts, styles).
  var nodes = [];
  var walker = document.createTreeWalker(wrap, NodeFilter.SHOW_TEXT, {
    acceptNode: function (node) {
      if (!node.nodeValue || !node.nodeValue.trim()) { return NodeFilter.FILTER_REJECT; }
      var p = node.parentNode;
      while (p && p !== wrap) {
        if (p.classList && p.classList.contains('pfmp-lang')) { return NodeFilter.FILTER_REJECT; }
        var tag = p.nodeName;
        if (tag === 'SCRIPT' || tag === 'STYLE') { return NodeFilter.FILTER_REJECT; }
        p = p.parentNode;
      }
      return NodeFilter.FILTER_ACCEPT;
    }
  });
  var n;
  while ((n = walker.nextNode())) {
    nodes.push({ node: n, raw: n.nodeValue, en: n.nodeValue.trim() });
  }

  function has(lang) { return langs.indexOf(lang) !== -1; }

  function apply(lang, persist) {
    if (!has(lang)) { lang = 'en'; }
    var table = dict[lang] || null;

    nodes.forEach(function (item) {
      if (lang === 'en' || !table) {
        item.node.nodeValue = item.raw;
        return;
      }
      var t = table[item.en];
      item.node.nodeValue = t ? item.raw.replace(item.en, t) : item.raw;
    });

    wrap.setAttribute('dir', rtl.indexOf(lang) !== -1 ? 'rtl' : 'ltr');
    wrap.setAttribute('lang', lang);

    var buttons = wrap.querySelectorAll('.pfmp-lang');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].classList.toggle('is-on', buttons[i].getAttribute('data-lang') === lang);
    }
    // Only remember an explicit choice, so browser detection stays live otherwise.
    if (persist) {
      try { localStorage.setItem('pfmp_lang', lang); } catch (e) {}
    }
  }

  function fromUrl() {
    if (!cfg.allowUrl) { return null; }
    var m = window.location.search.match(/[?&]lang=([a-z]{2})/i);
    return m ? m[1].toLowerCase() : null;
  }

  function fromBrowser() {
    var list = (navigator.languages && navigator.languages.length)
      ? navigator.languages
      : [navigator.language || ''];
    for (var i = 0; i < list.length; i++) {
      var code = (list[i] || '').slice(0, 2).toLowerCase();
      if (has(code)) { return code; }
    }
    return null;
  }

  function fromDefault() {
    // Admin-chosen fallback: the WP site locale, or a specific language.
    var d = cfg.defaultLang;
    if (d === 'locale') {
      if (cfg.siteLocale && has(cfg.siteLocale)) { return cfg.siteLocale; }
      return null;
    }
    return has(d) ? d : null;
  }

  function resolveInitial() {
    // 1) explicit ?lang override, 2) a choice the reader made before,
    // 3) the browser's language, 4) the admin fallback (WP locale or picked),
    // 5) the site locale, 6) English.
    var url = fromUrl();
    if (url) { return url; }

    var stored = null;
    try { stored = localStorage.getItem('pfmp_lang'); } catch (e) {}
    if (stored && has(stored)) { return stored; }

    return fromBrowser()
        || fromDefault()
        || (has(cfg.siteLocale) ? cfg.siteLocale : 'en');
  }

  wrap.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.pfmp-lang') : null;
    if (btn) { apply(btn.getAttribute('data-lang'), true); }
  });

  apply(resolveInitial(), false);
})();
