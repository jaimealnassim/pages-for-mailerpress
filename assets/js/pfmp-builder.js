/* Pages for MailerPress: the settings screen.
 * Every change is applied straight to the preview, which is built from the
 * real page markup and stylesheet; nothing is saved until Save. */
(function ($) {
  'use strict';

  $(function () {
    var cfg = window.PFMPBuilder || {};
    var P = window.PFMPPalette;
    var E = window.PFMPEnhance || {};
    var $builder = $('.pfmp-builder');
    var $form = $('#pfmp-builder-form');
    var $canvas = $('#pfmp-preview-canvas');
    var $stage = $('#pfmp-stage');
    var $frame = $('.pfmp-frame');
    var canvas = $canvas.get(0);
    var appearance = 'light';
    var currentState = 'manage';
    var TOKENS = ['background', 'surface', 'text', 'muted', 'border'];

    if (!$form.length || !P) { return; }

    function N() { return 'pfmp_settings[' + Array.prototype.slice.call(arguments).join('][') + ']'; }
    function $f(name) { return $form.find('[name="' + name + '"]'); }
    function checked(name) { return $f(name).filter(':checkbox').is(':checked'); }
    function radio(name) { return $f(name).filter(':checked').val() || ''; }
    function text(name) { return $.trim($f(name).val() || ''); }
    function fmt(s, n) { return String(s || '').replace('%d', n); }
    function wraps() { return $canvas.find('.pfmp-wrap'); }

    /* ---------------- colors ---------------- */

    function hexOf(key) { return $.trim($('#pfmp-c-' + key).val() || ''); }
    function palettes() {
      var accent = P.isHex(hexOf('accent')) ? hexOf('accent') : '#14392E';
      var overrides = {};
      TOKENS.forEach(function (t) { overrides[t] = P.isHex(hexOf(t)) ? hexOf(t) : ''; });
      return { light: P.resolve(accent, overrides), auto: P.resolve(accent, {}), dark: P.resolveDark(accent), overrides: overrides };
    }

    function expand(hex) {
      return hex.length === 4 ? '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3] : hex;
    }

    function badge($row, ratio) {
      var $b = $row.find('[data-badge]');
      var ok = ratio >= 4.5;
      $b.text((ok ? 'AA ' : (cfg.low || 'Low') + ' ') + ratio.toFixed(1) + ':1').toggleClass('is-low', !ok).toggleClass('is-ok', ok);
    }

    function applyPalette() {
      var pal = palettes();
      var p = appearance === 'dark' ? pal.dark : pal.light;
      var map = {
        '--pfmp-ink': p.accent, '--pfmp-button-text': p.buttonText, '--pfmp-canvas': p.background,
        '--pfmp-paper': p.surface, '--pfmp-text': p.text, '--pfmp-soft': p.muted, '--pfmp-muted': p.muted,
        '--pfmp-line': p.border, '--pfmp-line-2': p.border
      };
      wraps().each(function () {
        var el = this;
        Object.keys(map).forEach(function (k) { el.style.setProperty(k, map[k]); });
        el.classList.toggle('pfmp-dark', appearance === 'dark');
      });
      $stage.css('background-color', p.background);

      // The rows: swatch shows the color in use, placeholder the auto value.
      var L = pal.light;
      $('.pfmp-color').each(function () {
        var $row = $(this);
        var key = $row.data('color');
        var hex = hexOf(key);
        var effective = key === 'accent' ? L.accent : L[key];
        $row.find('.pfmp-color__swatch').val(expand(effective).toLowerCase());
        if (key !== 'accent') {
          $row.find('.pfmp-color__hex').attr('placeholder', pal.auto[key]);
          $row.find('[data-reset]').prop('hidden', hex === '');
        }
        if (key === 'accent') { badge($row, P.contrast(L.accent, L.surface)); }
        else if (key === 'text') { badge($row, P.contrast(L.text, L.surface)); }
        else if (key === 'muted') { badge($row, P.contrast(L.muted, L.surface)); }
        else {
          $row.find('[data-badge]').text(hex === '' ? cfg.auto : cfg.custom).removeClass('is-low is-ok');
        }
      });
    }

    $('.pfmp-color__swatch').on('input change', function () {
      var $hex = $(this).closest('.pfmp-color').find('.pfmp-color__hex');
      $hex.val(this.value.toUpperCase()).trigger('input');
    });
    $('.pfmp-color__hex').on('input change', applyPalette);
    $('[data-reset]').on('click', function () {
      $(this).closest('.pfmp-color').find('.pfmp-color__hex').val('').trigger('input');
    });

    /* ---------------- card and layout ---------------- */

    function applyStyle() {
      var radius = parseInt($('#pfmp-radius').val(), 10) || 0;
      var shadow = (cfg.shadows && cfg.shadows[$('#pfmp-shadow').val()]) || 'none';
      $('#pfmp-radius-value').text(radius + 'px');
      wraps().each(function () {
        this.style.setProperty('--pfmp-radius', radius + 'px');
        this.style.setProperty('--pfmp-shadow', shadow);
      });
      var url = $('#pfmp-bg-url').val() || '';
      if (/^https?:\/\//i.test(url) && url.indexOf('"') === -1) {
        $stage.css({
          'background-image': 'url("' + url + '")',
          'background-repeat': $('#pfmp-bg-repeat').is(':checked') ? 'repeat' : 'no-repeat',
          'background-size': $('#pfmp-bg-cover').is(':checked') ? 'cover' : 'auto',
          'background-position': 'center'
        });
      } else {
        $stage.css('background-image', 'none');
      }
    }

    function applyLayout() {
      var left = radio(N('layout')) === 'left';
      wraps().toggleClass('pfmp-left', left).toggleClass('pfmp-centered', !left);
    }

    /* ---------------- brand ---------------- */

    function applyBranding() {
      var pub = $.trim($('#pfmp-pub').val() || '');
      var tag = $.trim($('#pfmp-tag').val() || '');
      var logo = $('#pfmp-logo-url').val() || '';
      var letter = pub ? Array.from(pub)[0].toUpperCase() : 'M';
      $canvas.find('.pfmp-pub').text(pub);
      $canvas.find('.pfmp-tag').text(tag);
      $canvas.find('.pfmp-brand').each(function () {
        var $brand = $(this);
        $brand.find('.pfmp-logo, .pfmp-mark').remove();
        var $mark = logo
          ? $('<img>', { 'class': 'pfmp-logo', src: logo, alt: pub })
          : $('<span>', { 'class': 'pfmp-mark', 'aria-hidden': 'true' }).text(letter);
        $brand.prepend($mark);
      });
      if (!logo) { $('.pfmp-logo-prev span').text(letter); }
    }

    /* ---------------- page text ---------------- */

    function applyHeadlines() {
      $('[data-headline]').each(function () {
        var state = $(this).data('headline');
        var custom = $.trim($(this).val() || '');
        var $title = $canvas.find('.pfmp-preview-panel[data-preview-state="' + state + '"] .pfmp-title');
        var want = custom || (cfg.headlines && cfg.headlines[state]) || '';
        $title.toggleClass('pfmp-custom', custom !== '');
        // Only replace the text when the source changed, so a translated
        // built-in headline is not reset to English on every keystroke.
        if ($title.attr('data-src') !== want) {
          $title.attr('data-src', want).text(want);
        }
      });
    }

    function listDetails() {
      var details = {};
      $('[data-list-desc]').each(function () {
        var id = String($(this).data('list-desc'));
        details[id] = details[id] || {};
        details[id].desc = $.trim($(this).val() || '');
      });
      $('[data-list-when]').each(function () {
        var id = String($(this).data('list-when'));
        details[id] = details[id] || {};
        details[id].when = $.trim($(this).val() || '');
      });
      return details;
    }
    function applyLists() { if (E.lists && canvas) { E.lists(canvas, listDetails()); } }

    function applyFooter() {
      var show = $('#pfmp-foot-show').is(':checked');
      var privacy = $.trim($('#pfmp-foot-privacy').val() || '') || cfg.privacyUrl || '';
      var contact = $.trim($('#pfmp-foot-contact').val() || '');
      $canvas.find('[data-pfmp-foot="privacy"]').prop('hidden', privacy === '');
      $canvas.find('[data-pfmp-foot="contact"]').prop('hidden', contact === '');
      $canvas.find('.pfmp-foot').prop('hidden', !show || (privacy === '' && contact === ''));
    }

    function applyFeatures() {
      var showLists = $('#pfmp-show-lists').is(':checked');
      $canvas.find('[data-pfmp-mock-lists]').prop('hidden', !showLists);
      $('#pfmp-listdetails').prop('hidden', !showLists);
      // "Fewer emails" sends readers to pick lists, so it needs the list.
      $canvas.find('[data-pfmp-feature="offramp"]').toggle(showLists && $('#pfmp-f-offramp').is(':checked'));
      $canvas.find('[data-pfmp-feature="reasons"]').toggle($('#pfmp-f-reasons').is(':checked'));
      $canvas.find('[data-pfmp-feature="undo"]').toggle($('#pfmp-f-undo').is(':checked'));
      $canvas.find('[data-pfmp-feature="archive"]').toggle($.trim($('#pfmp-archive').val() || '') !== '');
    }

    /* ---------------- languages ---------------- */

    var engine = null;
    if (window.PFMPI18n && cfg.i18n && canvas) {
      engine = window.PFMPI18n.create($.extend({}, cfg.i18n, { persist: false }));
      wraps().each(function () { engine.attach(this); });
    }
    var $pvLang = $('#pfmp-pv-lang');

    function offeredCodes() {
      var codes = [];
      $form.find('.pfmp-lang-grid input[type="checkbox"]:checked').each(function () { codes.push($(this).val()); });
      if (codes.indexOf('en') === -1) { codes.unshift('en'); }
      return codes;
    }

    function langName(code) { return (cfg.languages && cfg.languages[code]) || code; }

    function buildMenu(codes) {
      var $list = $('<ul>', { 'class': 'pfmp-langlist', role: 'listbox', hidden: true });
      codes.forEach(function (c) {
        $('<li>', { role: 'presentation' }).append(
          $('<button>', { type: 'button', 'class': 'pfmp-lang', role: 'option', 'aria-selected': 'false', 'data-lang': c, lang: c }).text(langName(c))
        ).appendTo($list);
      });
      var globe = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/></svg>';
      var caret = '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>';
      var $btn = $('<button>', { type: 'button', 'class': 'pfmp-langbtn', 'aria-haspopup': 'listbox', 'aria-expanded': 'false', 'aria-label': 'Language' })
        .append(globe).append($('<span>', { 'data-pfmp-lang-current': '' }).text(langName('en'))).append(caret);
      return $('<div>', { 'class': 'pfmp-langmenu' }).append($btn).append($list);
    }

    function buildBar(codes) {
      var $bar = $('<div>', { 'class': 'pfmp-langbar', role: 'group', 'aria-label': 'Language' });
      codes.forEach(function (c) {
        $('<button>', { type: 'button', 'class': 'pfmp-lang', 'data-lang': c, lang: c, 'aria-pressed': 'false' }).text(langName(c)).appendTo($bar);
      });
      return $bar;
    }

    function applyLanguages() {
      var on = $('#pfmp-langtoggle').is(':checked');
      var style = radio(N('switcher_style')) || 'menu';
      var codes = offeredCodes();
      $canvas.find('.pfmp-langmenu, .pfmp-langbar').remove();
      if (on) {
        wraps().each(function () {
          var $w = $(this);
          if (style === 'buttons') { $w.find('.pfmp-head').after(buildBar(codes)); }
          else { $w.find('.pfmp-head').append(buildMenu(codes)); }
        });
        if (E.menus) { E.menus(canvas); }
      }

      var keep = $pvLang.val();
      $pvLang.empty();
      (on ? codes : ['en']).forEach(function (c) { $('<option>', { value: c }).text(langName(c)).appendTo($pvLang); });
      if (!engine) { return; }
      engine.setLanguages(on ? codes : ['en']);
      var next = (on && codes.indexOf(keep) !== -1) ? keep : (on && codes.indexOf(engine.current()) !== -1 ? engine.current() : 'en');
      $pvLang.val(next);
      engine.set(next);
    }
    $pvLang.on('change', function () { if (engine) { engine.set($(this).val()); } });

    /* ---------------- custom CSS ---------------- */

    function applyCustomCss() {
      var $style = $('#pfmp-preview-custom-css');
      if (!$style.length) { $style = $('<style id="pfmp-preview-custom-css"></style>').appendTo('head'); }
      $style.text($('#pfmp-css').val() || '');
    }

    /* ---------------- section summaries ---------------- */

    var darkLabels = { off: cfg.darkOff, auto: cfg.darkAuto, on: cfg.darkOn };
    function meta() {
      var set = function (id, s) { $('[data-meta="' + id + '"]').text(s || ''); };
      set('theme', (hexOf('accent') || '').toUpperCase() + ' · ' + (darkLabels[radio(N('dark_mode'))] || ''));
      set('card', (radio(N('layout')) === 'left' ? cfg.left : cfg.centered) + ' · ' + ($('#pfmp-radius').val() || 0) + 'px');
      set('brand', $.trim($('#pfmp-pub').val() || '') || cfg.none);
      var edited = $('[data-headline]').filter(function () { return $.trim(this.value) !== ''; }).length;
      set('text', edited ? fmt(cfg.nEdited, edited) : cfg.builtIn);
      var described = 0;
      $.each(listDetails(), function (id, d) { if (d.desc || d.when) { described++; } });
      set('lists', !$('#pfmp-show-lists').is(':checked') ? cfg.hidden : (described ? fmt(cfg.nLists, described) : cfg.none));
      var foot = [];
      if ($.trim($('#pfmp-foot-privacy').val() || '') || cfg.privacyUrl) { foot.push(cfg.privacy); }
      if ($.trim($('#pfmp-foot-contact').val() || '')) { foot.push(cfg.contact); }
      set('footer', $('#pfmp-foot-show').is(':checked') ? (foot.join(' · ') || cfg.none) : cfg.hidden);
      set('languages', $('#pfmp-langtoggle').is(':checked')
        ? fmt(cfg.nLanguages, offeredCodes().length) + ' · ' + (radio(N('switcher_style')) === 'buttons' ? cfg.buttons : cfg.menu)
        : cfg.off);
      var leave = [];
      if ($('#pfmp-f-offramp').is(':checked') && $('#pfmp-show-lists').is(':checked')) { leave.push(cfg.fewer); }
      if ($('#pfmp-f-reasons').is(':checked')) { leave.push(cfg.reasons); }
      if ($('#pfmp-f-undo').is(':checked')) { leave.push(cfg.undo); }
      set('leave', leave.join(' · ') || cfg.off);
      set('insights', $('#pfmp-f-insights').is(':checked') ? cfg.counting : cfg.off);
      set('pages', $('#pfmp-f-enabled').is(':checked') ? ($('#pfmp-f-takeover').is(':checked') ? cfg.fullPage : cfg.inTheme) : cfg.off);
    }

    /* ---------------- everything ---------------- */

    function applyAll() {
      applyPalette();
      applyStyle();
      applyLayout();
      applyBranding();
      applyHeadlines();
      applyLists();
      applyFooter();
      applyFeatures();
      applyLanguages();
      applyCustomCss();
      meta();
    }

    // Route each control to the parts of the preview it affects.
    $form.on('input change', 'input, select, textarea', function (e) {
      var t = e.target;
      if ($(t).closest('#pfmp-preview-canvas').length) { return; }
      var name = t.name || '';
      if (/\[palette\]|dark_mode/.test(name) || $(t).hasClass('pfmp-color__swatch')) { applyPalette(); }
      if (/\[style\]/.test(name) || t.id === 'pfmp-bg-cover' || t.id === 'pfmp-bg-repeat') { applyStyle(); }
      if (/\[layout\]/.test(name)) { applyLayout(); }
      if (t.id === 'pfmp-pub' || t.id === 'pfmp-tag') { applyBranding(); }
      if ($(t).is('[data-headline]')) { applyHeadlines(); }
      if ($(t).is('[data-list-desc], [data-list-when]')) { applyLists(); }
      if (/\[footer\]/.test(name)) { applyFooter(); }
      if (/offramp|reasons|undo|archive_url|show_lists/.test(name)) { applyFeatures(); }
      if (/lang_switcher|switcher_style|\[languages\]/.test(name)) { applyLanguages(); }
      if (t.id === 'pfmp-css') { applyCustomCss(); }
      meta();
      dirtyCheck();
    });
    $(document).on('pfmp:logo-changed', function () { applyBranding(); dirtyCheck(); });

    /* ---------------- toolbar ---------------- */

    function showState(state) {
      currentState = state;
      $('.pfmp-tab').attr('aria-selected', 'false').filter('[data-state="' + state + '"]').attr('aria-selected', 'true');
      $canvas.find('.pfmp-preview-panel').removeClass('is-active').filter('[data-preview-state="' + state + '"]').addClass('is-active');
      var $live = $('#pfmp-view-live');
      if ($live.length && cfg.liveUrls && cfg.liveUrls[state]) { $live.attr('href', cfg.liveUrls[state]); }
    }
    $('.pfmp-tab').on('click', function () { showState($(this).data('state')); });
    $('.pfmp-tabs').on('keydown', '.pfmp-tab', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
      var $tabs = $('.pfmp-tab');
      var i = $tabs.index(this) + (e.key === 'ArrowRight' ? 1 : -1);
      var $next = $tabs.eq((i + $tabs.length) % $tabs.length);
      $next.trigger('focus').trigger('click');
    });

    $('[data-device]').filter('button').on('click', function () {
      var d = $(this).data('device');
      $('button[data-device]').attr('aria-pressed', 'false');
      $(this).attr('aria-pressed', 'true');
      $frame.attr('data-device', d);
    });
    $('[data-appearance]').on('click', function () {
      appearance = $(this).data('appearance');
      $('[data-appearance]').attr('aria-pressed', 'false');
      $(this).attr('aria-pressed', 'true');
      applyPalette();
    });

    // Placeholder links in the preview never navigate.
    $canvas.on('click', 'a', function (e) { e.preventDefault(); });

    /* ---------------- phone: settings or preview ---------------- */

    $('.pfmp-mtabs [data-view]').on('click', function () {
      var v = $(this).data('view');
      $builder.attr('data-view', v);
      $('.pfmp-mtabs [data-view]').attr('aria-selected', 'false');
      $(this).attr('aria-selected', 'true');
    });

    /* ---------------- search ---------------- */

    var initiallyOpen = $('.pfmp-acc').map(function () { return this.open; }).get();
    // Search the words people read (titles, labels, help text), not field
    // values such as the export box.
    var searchText = $('.pfmp-acc').map(function () {
      return $(this).find('summary, label, legend, .pfmp-desc, .pfmp-toggle__desc, th').map(function () {
        return $(this).text();
      }).get().join(' ').toLowerCase();
    }).get();
    $('#pfmp-search').on('input', function () {
      var q = $.trim(this.value).toLowerCase();
      var any = false;
      $('.pfmp-acc').each(function (i) {
        var match = q === '' || searchText[i].indexOf(q) !== -1;
        this.hidden = !match;
        this.open = q === '' ? initiallyOpen[i] : match;
        if (match) { any = true; }
      });
      $('.pfmp-group').each(function () {
        var $sections = $(this).nextUntil('.pfmp-group', '.pfmp-acc');
        this.hidden = $sections.length > 0 && $sections.filter(function () { return !this.hidden; }).length === 0;
      });
      $('.pfmp-search-empty').prop('hidden', any);
    });

    /* ---------------- media pickers ---------------- */

    var bgFrame;
    $('#pfmp-bg-pick').on('click', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { return; }
      if (bgFrame) { bgFrame.open(); return; }
      bgFrame = wp.media({ title: cfg.chooseImage, button: { text: cfg.useImage }, library: { type: 'image' }, multiple: false });
      bgFrame.on('select', function () {
        var att = bgFrame.state().get('selection').first().toJSON();
        $('#pfmp-bg-id').val(att.id);
        $('#pfmp-bg-url').val(att.url).trigger('change');
        $('#pfmp-bg-prev').empty().append($('<img>', { src: att.url, alt: '' }));
        $('#pfmp-bg-clear').prop('hidden', false);
      });
      bgFrame.open();
    });
    $('#pfmp-bg-clear').on('click', function (e) {
      e.preventDefault();
      $('#pfmp-bg-id').val('');
      $('#pfmp-bg-url').val('').trigger('change');
      $('#pfmp-bg-prev').empty();
      $(this).prop('hidden', true);
    });

    /* ---------------- export ---------------- */

    $('#pfmp-export-copy').on('click', function () {
      var $btn = $(this);
      var value = $('#pfmp-export').val() || '';
      var done = function () {
        var label = $btn.data('label') || $btn.text();
        $btn.data('label', label).text(cfg.copied || 'Copied');
        setTimeout(function () { $btn.text(label); }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).then(done, function () { $('#pfmp-export').trigger('select'); });
      } else {
        $('#pfmp-export').trigger('select');
        try { document.execCommand('copy'); done(); } catch (err) {}
      }
    });

    /* ---------------- unsaved changes ---------------- */

    function snapshot() {
      var out = {};
      $form.serializeArray().forEach(function (f) {
        if (f.name.indexOf('pfmp_settings[') !== 0) { return; }
        out[f.name] = (out[f.name] === undefined ? '' : out[f.name] + '\u0001') + f.value;
      });
      return out;
    }
    var base = null;
    var dirty = 0;
    function dirtyCheck() {
      if (!base) { return; }
      var now = snapshot();
      var keys = {};
      Object.keys(base).forEach(function (k) { keys[k] = 1; });
      Object.keys(now).forEach(function (k) { keys[k] = 1; });
      dirty = Object.keys(keys).filter(function (k) { return base[k] !== now[k]; }).length;
      $('#pfmp-dirty-text').text(dirty === 0 ? cfg.unsavedNone : (dirty === 1 ? cfg.unsavedOne : fmt(cfg.unsavedMany, dirty)));
      $('.pfmp-savebar').toggleClass('is-dirty', dirty > 0);
      $('#pfmp-discard').prop('hidden', dirty === 0);
    }
    $('#pfmp-discard').on('click', function () {
      dirty = 0;
      window.location.reload();
    });
    $form.on('submit', function () { dirty = 0; });
    $(window).on('beforeunload', function (e) {
      if (!dirty) { return undefined; }
      e.preventDefault();
      e.returnValue = '';
      return '';
    });

    /* ---------------- start ---------------- */

    if (canvas) {
      if (E.status) { E.status(canvas); }
      if (E.reasons) { E.reasons(canvas, null); }
      if (E.unsubscribeLink) { E.unsubscribeLink(canvas, null); }
    }
    wraps().removeClass('pfmp-dark pfmp-dark-auto');
    applyAll();
    showState('manage');
    base = snapshot();
    dirtyCheck();
  });
})(jQuery);
