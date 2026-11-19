/* Pages for MailerPress: the reader pages' behavior.
 *
 * - Language switching with no reload: MailerPress's English strings are
 *   swapped using a dictionary shipped with the plugin, right-to-left
 *   languages flip the layout, and text MailerPress adds later (the "Saving…"
 *   label, the message after Save) is translated as it appears.
 * - The language menu, the two-option status switch, newsletter descriptions,
 *   the "before you go" options and links that need the reader's access
 *   token.
 * - Anonymous counts for the settings screen's Insights, when switched on.
 *
 * Everything works without this script too; it only enhances.
 * Exposes window.PFMPI18n and window.PFMPEnhance for the settings preview. */
(function (global, doc) {
  'use strict';

  // Text that belongs to the site owner or the reader, never translated.
  var SKIP = '.pfmp-langbtn, .pfmp-lang, .pfmp-pub, .pfmp-tag, .pfmp-custom, .pfmp-list-desc, .pfmp-list-when, ' +
    '.mailerpress-subscription-list-name, .mailerpress-form-line > strong, script, style, textarea, code';

  var has = function (obj, key) { return Object.prototype.hasOwnProperty.call(obj, key); };

  /* ------------------------------------------------------------------ */
  /* Translation engine                                                  */
  /* ------------------------------------------------------------------ */

  function create(cfg) {
    cfg = cfg || {};
    var langs   = Array.isArray(cfg.languages) && cfg.languages.length ? cfg.languages : ['en'];
    var rtl     = Array.isArray(cfg.rtl) ? cfg.rtl : [];
    var dict    = cfg.dict || {};
    var persist = cfg.persist !== false;
    var current = 'en';
    var scopes  = [];
    var extras  = [];            // text nodes outside the scopes (theme page heading)
    var records = new WeakMap(); // text node -> { raw, en, shown }

    // Every translation maps back to its English source, so text that was
    // already translated can still switch to a third language.
    var reverse = {};
    var english = {};
    Object.keys(dict).forEach(function (lang) {
      var table = dict[lang] || {};
      Object.keys(table).forEach(function (en) {
        english[en] = true;
        if (!has(reverse, table[en])) { reverse[table[en]] = en; }
      });
    });

    function offered(lang) { return lang === 'en' || langs.indexOf(lang) !== -1; }
    function isKey(text) { return !!text && (english[text] === true || has(reverse, text)); }

    // English wins when text is both an English string and some language's
    // translation of another one.
    function sourceOf(text) {
      if (english[text] === true) { return text; }
      return has(reverse, text) ? reverse[text] : text;
    }

    function skipped(node, scope) {
      var el = node.parentElement;
      return !el || !!el.closest(SKIP) || (scope && !scope.contains(el));
    }

    function translateNode(node) {
      var text = node.nodeValue;
      if (!text || !text.trim()) { return; }
      var rec = records.get(node);
      if (!rec || rec.shown !== text) {
        var trimmed = text.trim();
        var en = sourceOf(trimmed);
        rec = { raw: text.replace(trimmed, en), en: en, shown: text };
        records.set(node, rec);
      }
      var out = rec.raw;
      var table = current !== 'en' ? dict[current] : null;
      if (table && has(table, rec.en)) { out = rec.raw.replace(rec.en, function () { return table[rec.en]; }); }
      if (node.nodeValue !== out) { node.nodeValue = out; }
      rec.shown = out;
    }

    function walk(scope) {
      var tw = doc.createTreeWalker(scope, NodeFilter.SHOW_TEXT, null);
      var n;
      while ((n = tw.nextNode())) {
        if (!skipped(n, scope)) { translateNode(n); }
      }
    }

    function syncButtons() {
      scopes.forEach(function (scope) {
        var buttons = scope.querySelectorAll('.pfmp-lang');
        var name = '';
        for (var i = 0; i < buttons.length; i++) {
          var on = buttons[i].getAttribute('data-lang') === current;
          buttons[i].classList.toggle('is-on', on);
          buttons[i].setAttribute(buttons[i].getAttribute('role') === 'option' ? 'aria-selected' : 'aria-pressed', on ? 'true' : 'false');
          if (on) { name = buttons[i].textContent; }
        }
        var labels = scope.querySelectorAll('[data-pfmp-lang-current]');
        for (var j = 0; j < labels.length; j++) {
          if (name && labels[j].textContent !== name) { labels[j].textContent = name; }
        }
      });
    }

    var originalTitle = doc.title;
    function translateTitle() {
      if (!cfg.translateTitle) { return; }
      var title = originalTitle;
      var table = current !== 'en' ? dict[current] : null;
      if (table) {
        Object.keys(table).forEach(function (en) {
          if (en.length > 12 && title.indexOf(en) !== -1) { title = title.replace(en, function () { return table[en]; }); }
        });
      }
      doc.title = title;
    }

    function apply(lang, remember) {
      if (!offered(lang)) { lang = 'en'; }
      current = lang;
      scopes.forEach(function (scope) {
        walk(scope);
        scope.setAttribute('dir', rtl.indexOf(lang) !== -1 ? 'rtl' : 'ltr');
        scope.setAttribute('lang', lang);
      });
      extras.forEach(translateNode);
      translateTitle();
      syncButtons();
      if (remember) {
        if (persist) { try { localStorage.setItem('pfmp_lang', lang); } catch (e) {} }
        if (typeof cfg.onChoose === 'function') { cfg.onChoose(lang); }
      }
    }

    var observer = global.MutationObserver ? new MutationObserver(function (mutations) {
      mutations.forEach(function (m) {
        var scope = null;
        for (var i = 0; i < scopes.length; i++) { if (scopes[i].contains(m.target)) { scope = scopes[i]; break; } }
        if (!scope) { return; }
        if (m.type === 'characterData') {
          if (!skipped(m.target, scope)) { translateNode(m.target); }
          return;
        }
        Array.prototype.forEach.call(m.addedNodes, function (added) {
          if (added.nodeType === 3) {
            if (!skipped(added, scope)) { translateNode(added); }
          } else if (added.nodeType === 1) {
            walk(added);
          }
        });
        if (m.addedNodes.length) { syncButtons(); }
      });
    }) : null;

    function attach(scope) {
      if (!scope || scopes.indexOf(scope) !== -1) { return; }
      scopes.push(scope);
      if (observer) { observer.observe(scope, { childList: true, subtree: true, characterData: true }); }
      scope.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.pfmp-lang') : null;
        if (btn && scope.contains(btn)) { apply(btn.getAttribute('data-lang'), true); }
      });
    }

    // The theme's page heading can show one of MailerPress's strings, outside
    // the card. Translate it too when it matches exactly.
    function adoptHeadings() {
      var heads = doc.querySelectorAll('h1, .entry-title, .wp-block-post-title');
      for (var i = 0; i < heads.length; i++) {
        var inScope = scopes.some(function (s) { return s.contains(heads[i]); });
        if (inScope) { continue; }
        var tw = doc.createTreeWalker(heads[i], NodeFilter.SHOW_TEXT, null);
        var n;
        while ((n = tw.nextNode())) {
          if (isKey(n.nodeValue.trim())) { extras.push(n); }
        }
      }
    }

    function fromUrl() {
      if (!cfg.allowUrl) { return null; }
      var m = global.location.search.match(/[?&]lang=([a-z]{2})/i);
      return m ? m[1].toLowerCase() : null;
    }

    function fromBrowser() {
      var list = (global.navigator.languages && global.navigator.languages.length)
        ? global.navigator.languages
        : [global.navigator.language || ''];
      for (var i = 0; i < list.length; i++) {
        var code = (list[i] || '').slice(0, 2).toLowerCase();
        if (offered(code)) { return code; }
      }
      return null;
    }

    function fromDefault() {
      var d = cfg.defaultLang;
      if (d === 'locale') { return cfg.siteLocale && offered(cfg.siteLocale) ? cfg.siteLocale : null; }
      return offered(d) ? d : null;
    }

    // ?lang= override, then the reader's earlier choice, then their browser
    // language, then the admin's fallback, then the site language, then English.
    function initial() {
      var url = fromUrl();
      if (url && offered(url)) { return url; }
      var stored = null;
      if (persist) { try { stored = localStorage.getItem('pfmp_lang'); } catch (e) {} }
      if (stored && offered(stored)) { return stored; }
      return fromBrowser() || fromDefault() || (offered(cfg.siteLocale) ? cfg.siteLocale : 'en');
    }

    return {
      attach: attach,
      adoptHeadings: adoptHeadings,
      set: function (lang) { apply(lang, false); },
      choose: function (lang) { apply(lang, true); },
      refresh: function () { apply(current, false); },
      current: function () { return current; },
      setLanguages: function (list) { langs = list && list.length ? list : ['en']; if (!offered(current)) { apply('en', false); } },
      initial: initial
    };
  }

  /* ------------------------------------------------------------------ */
  /* Enhancements                                                        */
  /* ------------------------------------------------------------------ */

  var menuDocBound = false;

  // The language menu: a button that opens a popup of languages. On the live
  // pages it's a modal <dialog> (a bottom sheet on phones), which traps focus,
  // closes on Escape and dims the page. In the settings preview it opens
  // inside the card instead, so it doesn't cover the admin screen.
  var X_ICON = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  var dialogSeq = 0;

  function closeMenus(except) {
    var open = doc.querySelectorAll('.pfmp-langdialog[open]');
    for (var i = 0; i < open.length; i++) {
      if (open[i] !== except && open[i].pfmpClose) { open[i].pfmpClose(false); }
    }
  }

  function menus(root) {
    var all = root.querySelectorAll('.pfmp-langmenu');
    for (var i = 0; i < all.length; i++) {
      (function (menu) {
        if (menu.getAttribute('data-pfmp-ready')) { return; }
        var btn = menu.querySelector('.pfmp-langbtn');
        var list = menu.querySelector('.pfmp-langlist');
        if (!btn || !list) { return; }
        menu.setAttribute('data-pfmp-ready', '1');

        var id = 'pfmp-langdialog-' + (++dialogSeq);
        var dialog = doc.createElement('dialog');
        dialog.className = 'pfmp-langdialog';
        dialog.id = id;
        dialog.setAttribute('aria-labelledby', id + '-title');
        var inline = !!menu.closest('.pfmp-canvas') || typeof dialog.showModal !== 'function';
        if (inline) { dialog.classList.add('pfmp-inline'); }
        dialog.innerHTML = '<div class="pfmp-langdialog__head"><p class="pfmp-langdialog__title" id="' + id + '-title">Language</p>' +
          '<button type="button" class="pfmp-langclose">' + X_ICON + '<span class="pfmp-sr">Close</span></button></div>';

        // A plain list of toggle buttons: each one says whether it's chosen.
        list.removeAttribute('role');
        list.hidden = false;
        var opts = list.querySelectorAll('.pfmp-lang');
        for (var k = 0; k < opts.length; k++) {
          opts[k].removeAttribute('role');
          opts[k].removeAttribute('aria-selected');
          opts[k].setAttribute('aria-pressed', opts[k].classList.contains('is-on') ? 'true' : 'false');
        }
        dialog.appendChild(list);
        menu.appendChild(dialog);

        btn.setAttribute('aria-haspopup', 'dialog');
        btn.setAttribute('aria-controls', id);
        btn.setAttribute('aria-expanded', 'false');

        var options = function () { return Array.prototype.slice.call(list.querySelectorAll('.pfmp-lang')); };

        function open() {
          closeMenus(dialog);
          if (inline) { dialog.setAttribute('open', ''); } else { dialog.showModal(); }
          btn.setAttribute('aria-expanded', 'true');
          var on = list.querySelector('.pfmp-lang.is-on') || options()[0];
          if (on) { on.focus(); }
        }
        function close(refocus) {
          if (!dialog.hasAttribute('open')) { return; }
          if (inline) { dialog.removeAttribute('open'); } else { dialog.close(); }
          btn.setAttribute('aria-expanded', 'false');
          if (refocus) { btn.focus(); }
        }
        dialog.pfmpClose = close;

        btn.addEventListener('click', function () {
          if (dialog.hasAttribute('open')) { close(false); } else { open(); }
        });
        // Escape on a modal dialog fires "cancel"; handle it ourselves so
        // the button state and focus stay right.
        dialog.addEventListener('cancel', function (e) { e.preventDefault(); close(true); });
        dialog.addEventListener('click', function (e) {
          if (e.target.closest('.pfmp-langclose')) { close(true); return; }
          if (e.target.closest('.pfmp-lang')) { close(true); return; }
          // A click on the dimmed backdrop lands on the dialog itself.
          if (e.target === dialog && !inline) {
            var r = dialog.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) { close(true); }
          }
        });
        dialog.addEventListener('keydown', function (e) {
          var list2 = options();
          var idx = list2.indexOf(doc.activeElement);
          if (e.key === 'Escape') { e.preventDefault(); close(true); }
          else if (idx === -1) { return; }
          else if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); (list2[idx + 1] || list2[0]).focus(); }
          else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); (list2[idx - 1] || list2[list2.length - 1]).focus(); }
          else if (e.key === 'Home') { e.preventDefault(); list2[0].focus(); }
          else if (e.key === 'End') { e.preventDefault(); list2[list2.length - 1].focus(); }
        });
      })(all[i]);
    }
    if (!menuDocBound) {
      menuDocBound = true;
      // The in-card popup closes on a click anywhere else.
      doc.addEventListener('click', function (e) {
        if (!e.target.closest || e.target.closest('.pfmp-langmenu')) { return; }
        var open = doc.querySelectorAll('.pfmp-langdialog.pfmp-inline[open]');
        for (var j = 0; j < open.length; j++) { open[j].pfmpClose(false); }
      });
    }
  }

  // MailerPress's Subscribed/Unsubscribed dropdown becomes a two-option
  // switch. The dropdown stays in the form (hidden) and still submits.
  function status(root) {
    var selects = root.querySelectorAll('.mailerpress-manage-subscription select');
    for (var i = 0; i < selects.length; i++) {
      (function (select) {
        if (select.getAttribute('data-pfmp-ready') || select.options.length !== 2) { return; }
        select.setAttribute('data-pfmp-ready', '1');
        var label = select.id ? root.querySelector('label[for="' + select.id + '"]') : null;
        var seg = doc.createElement('div');
        seg.className = 'pfmp-seg';
        seg.setAttribute('role', 'radiogroup');
        if (label) {
          if (!label.id) { label.id = 'pfmp-seg-label-' + (select.id || i); }
          seg.setAttribute('aria-labelledby', label.id);
          label.removeAttribute('for');
        }
        var buttons = [];
        function sync() {
          buttons.forEach(function (b) {
            var on = b.getAttribute('data-value') === select.value;
            b.setAttribute('aria-checked', on ? 'true' : 'false');
            b.tabIndex = on ? 0 : -1;
          });
        }
        function pick(b, focus) {
          if (select.value !== b.getAttribute('data-value')) {
            select.value = b.getAttribute('data-value');
            select.dispatchEvent(new Event('change', { bubbles: true }));
          }
          sync();
          if (focus) { b.focus(); }
        }
        Array.prototype.forEach.call(select.options, function (opt, n) {
          var b = doc.createElement('button');
          b.type = 'button';
          b.setAttribute('role', 'radio');
          b.setAttribute('data-value', opt.value);
          b.textContent = opt.textContent.trim();
          b.addEventListener('click', function () { pick(b, false); });
          b.addEventListener('keydown', function (e) {
            if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(e.key) !== -1) {
              e.preventDefault();
              pick(buttons[1 - n], true);
            }
          });
          buttons.push(b);
          seg.appendChild(b);
        });
        select.classList.add('pfmp-sr');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        select.parentNode.insertBefore(seg, select.nextSibling);
        select.addEventListener('change', sync);
        sync();
      })(selects[i]);
    }
  }

  // Newsletter descriptions and "how often" labels the admin wrote.
  function lists(root, details) {
    details = details || {};
    var inputs = root.querySelectorAll('.mailerpress-subscription-list-input');
    for (var i = 0; i < inputs.length; i++) {
      var option = inputs[i].closest('.mailerpress-subscription-list-option');
      if (!option) { continue; }
      var copy = option.querySelector('.mailerpress-subscription-list-copy');
      var old = option.querySelectorAll('.pfmp-list-desc, .pfmp-list-when');
      for (var j = 0; j < old.length; j++) { old[j].parentNode.removeChild(old[j]); }
      var row = details[inputs[i].value];
      if (!row) { continue; }
      if (row.desc && copy) {
        var desc = doc.createElement('span');
        desc.className = 'pfmp-list-desc';
        desc.textContent = row.desc;
        var name = copy.querySelector('.mailerpress-subscription-list-name');
        copy.insertBefore(desc, name ? name.nextSibling : copy.firstChild);
      }
      if (row.when) {
        var when = doc.createElement('span');
        when.className = 'pfmp-list-when';
        when.textContent = row.when;
        option.appendChild(when);
      }
    }
  }

  // MailerPress's "Yes, unsubscribe me" link joins our "Keep my
  // subscription" button, and carries the reader's access token so the next
  // page can offer Resubscribe.
  function unsubscribeLink(root, cid) {
    var actions = root.querySelector('[data-pfmp-actions]');
    var link = root.querySelector('.pfmp-content a.button');
    if (!link || link.getAttribute('data-pfmp-ready')) { return; }
    link.setAttribute('data-pfmp-ready', '1');
    if (cid) { addParam(link, 'cid', cid); }
    if (!actions) { return; }
    var prev = link.previousSibling;
    while (prev && prev.nodeType === 3 && !prev.nodeValue.trim()) { prev = prev.previousSibling; }
    if (prev && prev.nodeName === 'BR') { prev.parentNode.removeChild(prev); }
    link.classList.add('pfmp-btn', 'pfmp-btn-danger');
    actions.appendChild(link);
  }

  // Reason chips: one choice, a thank-you, and a count.
  function reasons(root, onPick) {
    var groups = root.querySelectorAll('.pfmp-reasons');
    for (var i = 0; i < groups.length; i++) {
      (function (group) {
        if (group.getAttribute('data-pfmp-ready')) { return; }
        group.setAttribute('data-pfmp-ready', '1');
        group.addEventListener('click', function (e) {
          var chip = e.target.closest('.pfmp-chip');
          if (!chip || chip.getAttribute('aria-pressed') === 'true') { return; }
          var chips = group.querySelectorAll('.pfmp-chip');
          for (var j = 0; j < chips.length; j++) { chips[j].setAttribute('aria-pressed', chips[j] === chip ? 'true' : 'false'); }
          var thanks = group.querySelector('.pfmp-thanks');
          if (thanks) { thanks.hidden = false; }
          if (onPick) { onPick(chip.getAttribute('data-pfmp-reason')); }
        });
      })(groups[i]);
    }
  }

  function addParam(el, key, value) {
    try {
      var url = new URL(el.getAttribute('href'), global.location.href);
      url.searchParams.set(key, value);
      el.setAttribute('href', url.toString());
    } catch (e) {}
  }

  // Links that need the reader's access token get it from this page's
  // address, and only then are shown.
  function carryCid(root, cid) {
    if (!cid) { return; }
    var links = root.querySelectorAll('[data-pfmp-carry-cid]');
    for (var i = 0; i < links.length; i++) { addParam(links[i], 'cid', cid); }
    var waiting = root.querySelectorAll('[data-pfmp-needs-cid]');
    for (var j = 0; j < waiting.length; j++) { waiting[j].hidden = false; }
  }

  global.PFMPI18n = { create: create };
  global.PFMPEnhance = { menus: menus, status: status, lists: lists, reasons: reasons, unsubscribeLink: unsubscribeLink };

  /* ------------------------------------------------------------------ */
  /* Insights                                                            */
  /* ------------------------------------------------------------------ */

  function tracker(cfg) {
    return function (event, key) {
      if (!cfg || !cfg.track) { return; }
      key = key || '';
      var once = 'pfmp_t_' + event + '_' + key;
      try {
        if (sessionStorage.getItem(once)) { return; }
        sessionStorage.setItem(once, '1');
      } catch (e) {}
      var body = new URLSearchParams({ action: cfg.trackAction || 'pfmp_track', event: event, key: key });
      if (global.navigator.sendBeacon && global.navigator.sendBeacon(cfg.track, body)) { return; }
      if (global.fetch) { global.fetch(cfg.track, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' }).catch(function () {}); }
    };
  }

  /* ------------------------------------------------------------------ */
  /* Boot                                                                */
  /* ------------------------------------------------------------------ */

  function boot() {
    var cfg = global.PFMP || {};
    var cid = null;
    try { cid = new URLSearchParams(global.location.search).get('cid'); } catch (e) {}
    var track = tracker(cfg);
    var roots = doc.querySelectorAll('[data-pfmp-i18n]');

    for (var i = 0; i < roots.length; i++) {
      var root = roots[i];
      unsubscribeLink(root, cid);
      carryCid(root, cid);
      lists(root, cfg.lists);
      status(root);
      menus(root);
      reasons(root, function (key) { track('reason', key); });
      root.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-pfmp-track]') : null;
        if (el) { track(el.getAttribute('data-pfmp-track'), ''); }
      });
      var state = root.getAttribute('data-pfmp-state');
      if (state) { track('view', state); }
    }

    if (!cfg.i18n || !roots.length) { return; }
    cfg.translateTitle = true;
    cfg.onChoose = function (lang) { track('lang', lang); };
    var engine = create(cfg);
    for (var k = 0; k < roots.length; k++) {
      // With MailerPress Pro's design on, translate Pro's whole card,
      // including its heading, not only our switcher's container.
      var proCard = roots[k].closest ? roots[k].closest('.mailerpress-pages-template') : null;
      engine.attach(proCard || roots[k]);
    }
    engine.adoptHeadings();
    engine.set(engine.initial());
    global.PFMPEngine = engine;
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
