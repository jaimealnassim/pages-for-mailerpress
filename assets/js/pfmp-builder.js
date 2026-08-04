/* Pages for MailerPress: Builder screen controls.
 * Drives the simulated, same-page preview directly (no iframe, no
 * cross-frame messaging) using the palette math in pfmp-palette.js. Every
 * change here is applied straight to the preview's DOM; nothing is saved
 * until the form is submitted. */
(function ($) {
  'use strict';

  $(function () {
    var cfg = window.PFMPBuilder || {};
    var $accent = $('#pfmp-accent');
    var $presetInput = $('#pfmp-preset-input');
    var $canvas = $('#pfmp-preview-canvas');
    var $frameWrap = $('.pfmp-builder__frame-wrap');
    var currentState = 'manage';

    function tokenInputs() {
      return $('.pfmp-builder__color--token');
    }

    function currentOverrides() {
      var overrides = {};
      tokenInputs().each(function () {
        var key = $(this).data('token-input');
        overrides[key] = $(this).val() || '';
      });
      return overrides;
    }

    function currentPalette() {
      var accent = $accent.val() || '#14392E';
      return window.PFMPPalette.resolve(accent, currentOverrides());
    }

    function currentStyle() {
      var shadowKey = $('#pfmp-shadow').val() || 'soft';
      return {
        radius: parseInt($('#pfmp-radius').val(), 10) || 0,
        shadow: (cfg.shadows && cfg.shadows[shadowKey]) || 'none',
        bgUrl: $('#pfmp-bg-url').val() || '',
        bgRepeat: $('#pfmp-bg-repeat').is(':checked'),
        bgCover: $('#pfmp-bg-cover').is(':checked')
      };
    }

    // --- apply everything straight to the preview DOM, no messaging ------
    //
    // Important: these variables must be set directly on each .pfmp-wrap
    // element, not on an ancestor like the canvas wrapper. The base
    // stylesheet declares its own default value for each of them directly
    // on .pfmp-wrap itself (see pfmp-frontend.css), and a property declared
    // on an element always wins over one merely inherited from a parent,
    // regardless of source order. Setting them higher up in the DOM has no
    // visible effect, which was the bug here.
    function wraps() {
      return $canvas.find('.pfmp-wrap');
    }

    function applyPalette() {
      if (!$canvas.length) { return; }
      var p = currentPalette();
      var map = {
        '--pfmp-ink': p.accent,
        '--pfmp-button-text': p.buttonText,
        '--pfmp-canvas': p.background,
        '--pfmp-paper': p.surface,
        '--pfmp-text': p.text,
        '--pfmp-soft': p.muted,
        '--pfmp-muted': p.muted,
        '--pfmp-line': p.border,
        '--pfmp-line-2': p.border
      };
      wraps().each(function () {
        var el = this;
        Object.keys(map).forEach(function (key) {
          el.style.setProperty(key, map[key]);
        });
      });
    }

    function applyStyle() {
      if (!$canvas.length) { return; }
      var s = currentStyle();
      wraps().each(function () {
        this.style.setProperty('--pfmp-radius', s.radius + 'px');
        this.style.setProperty('--pfmp-shadow', s.shadow);
      });
      // The background image is a full-page-mode effect (it sits behind the
      // whole page, outside the card), so preview it against the canvas
      // area around the card rather than as a custom property nothing in
      // the card itself reads.
      if (s.bgUrl && /^https?:\/\//i.test(s.bgUrl) && s.bgUrl.indexOf('"') === -1) {
        $frameWrap.css({
          'background-image': 'url("' + s.bgUrl + '")',
          'background-repeat': s.bgRepeat ? 'repeat' : 'no-repeat',
          'background-size': s.bgCover ? 'cover' : 'auto',
          'background-position': 'center'
        });
      } else {
        $frameWrap.css({ 'background-image': 'none' });
      }
    }

    function applyAll() {
      applyPalette();
      applyStyle();
      applyBranding();
      applyLanguages();
      applyLayout();
      applyOfframp();
      applyCustomCss();
    }

    // --- publication name, tagline, logo ---------------------------------
    function applyBranding() {
      if (!$canvas.length) { return; }
      var pub = $('#pfmp-pub').val() || '';
      var tag = $('#pfmp-tag').val() || '';
      var logoUrl = $('#pfmp-logo-url').val() || '';

      $canvas.find('.pfmp-pub').text(pub);
      $canvas.find('.pfmp-tag').text(tag);

      $canvas.find('.pfmp-head').each(function () {
        var $head = $(this);
        var letter = (pub || 'M').charAt(0).toUpperCase();
        if (logoUrl) {
          var $img = $head.find('.pfmp-logo');
          if ($img.length) {
            $img.attr('src', logoUrl);
          } else {
            $head.find('.pfmp-mark').replaceWith('<img class="pfmp-logo" src="' + logoUrl + '" alt="">');
          }
        } else {
          var $mark = $head.find('.pfmp-mark');
          if ($mark.length) {
            $mark.text(letter);
          } else {
            $head.find('.pfmp-logo').replaceWith('<span class="pfmp-mark" aria-hidden="true">' + letter + '</span>');
          }
        }
      });
    }

    // --- language switcher: on/off and which languages -------------------
    function applyLanguages() {
      if (!$canvas.length) { return; }
      var enabled = $('#pfmp-langtoggle').is(':checked');
      var codes = [];
      $('.pfmp-lang-grid input[type="checkbox"]:checked').each(function () {
        codes.push($(this).val());
      });

      $canvas.find('.pfmp-langbar').remove();
      if (!enabled || !codes.length) { return; }

      var buttons = codes.map(function (code) {
        var label = (cfg.languages && cfg.languages[code]) || code;
        var on = code === 'en' ? ' is-on' : '';
        return '<button type="button" class="pfmp-lang' + on + '" data-lang="' + code + '">' + label + '</button>';
      }).join('');
      var bar = '<div class="pfmp-langbar" role="group">' + buttons + '</div>';

      $canvas.find('.pfmp-head').each(function () {
        $(this).after(bar);
      });
    }

    // --- layout: centered vs left-aligned ---------------------------------
    function applyLayout() {
      if (!$canvas.length) { return; }
      var left = $('input[name="pfmp_settings[layout]"]:checked').val() === 'left';
      $canvas.find('.pfmp-wrap').toggleClass('pfmp-left', left).toggleClass('pfmp-centered', !left);
    }

    // --- unsubscribe off-ramp ---------------------------------------------
    function applyOfframp() {
      if (!$canvas.length) { return; }
      var on = $('input[name="pfmp_settings[offramp]"]').is(':checked');
      $canvas.find('.pfmp-offramp').toggle(on);
    }

    // --- custom CSS: injected live so it previews the same as it will render
    function applyCustomCss() {
      var css = $('#pfmp-css').val() || '';
      var $style = $('#pfmp-preview-custom-css');
      if (!$style.length) {
        $style = $('<style id="pfmp-preview-custom-css"></style>').appendTo('head');
      }
      $style.text(css);
    }

    function refreshTokenStates() {
      tokenInputs().each(function () {
        var $row = $(this).closest('.pfmp-builder__token');
        var isAuto = !$(this).val();
        $row.find('.pfmp-builder__token-state').text(isAuto ? (cfg.i18nAuto || 'Auto') : (cfg.i18nCustom || 'Custom'));
      });
    }

    function markCustomPreset() {
      $presetInput.val('custom');
      $('.pfmp-builder__preset').removeClass('is-on').attr('aria-pressed', 'false');
    }

    function showState(state) {
      currentState = state;
      $canvas.find('.pfmp-preview-panel').removeClass('is-active');
      $canvas.find('.pfmp-preview-panel[data-preview-state="' + state + '"]').addClass('is-active');

      // Keep the optional "View the live page" link pointed at whichever
      // state is showing, when a live URL for it happens to be known.
      var $live = $('#pfmp-view-live');
      if ($live.length && cfg.liveUrls && cfg.liveUrls[state]) {
        $live.attr('href', cfg.liveUrls[state]).show();
      } else if ($live.length) {
        $live.hide();
      }
    }

    // --- state tabs ---------------------------------------------------
    $('.pfmp-builder__state').on('click', function () {
      $('.pfmp-builder__state').removeClass('is-on').attr('aria-selected', 'false');
      $(this).addClass('is-on').attr('aria-selected', 'true');
      showState($(this).data('state'));
    });

    // --- device toggle ------------------------------------------------
    $('.pfmp-builder__device').on('click', function () {
      $('.pfmp-builder__device').removeClass('is-on').attr('aria-pressed', 'false');
      $(this).addClass('is-on').attr('aria-pressed', 'true');
      $frameWrap.attr('data-device', $(this).data('device'));
    });

    // --- color pickers --------------------------------------------------
    if ($.fn.wpColorPicker) {
      $('.pfmp-builder__color').wpColorPicker({
        change: function () {
          setTimeout(function () {
            refreshTokenStates();
            applyPalette();
          }, 0);
        },
        clear: function () {
          setTimeout(function () {
            refreshTokenStates();
            applyPalette();
          }, 0);
        }
      });
    }

    $accent.on('change input', function () {
      markCustomPreset();
      applyPalette();
    });

    tokenInputs().on('change input', function () {
      markCustomPreset();
      refreshTokenStates();
      applyPalette();
    });

    // --- clear-to-auto buttons -----------------------------------------
    $('.pfmp-builder__clear').on('click', function () {
      var key = $(this).data('clear');
      var $input = $('[data-token-input="' + key + '"]');
      $input.val('');
      if ($.fn.wpColorPicker) { $input.wpColorPicker('color', ''); }
      markCustomPreset();
      refreshTokenStates();
      applyPalette();
    });

    // --- presets ---------------------------------------------------------
    $('.pfmp-builder__preset').on('click', function () {
      var $btn = $(this);
      var accent = $btn.data('accent');
      $('.pfmp-builder__preset').removeClass('is-on').attr('aria-pressed', 'false');
      $btn.addClass('is-on').attr('aria-pressed', 'true');
      $presetInput.val($btn.data('preset'));

      $accent.val(accent);
      if ($.fn.wpColorPicker) { $accent.wpColorPicker('color', accent); }

      tokenInputs().each(function () {
        $(this).val('');
        if ($.fn.wpColorPicker) { $(this).wpColorPicker('color', ''); }
      });

      refreshTokenStates();
      applyPalette();
    });

    // --- card style: radius, shadow --------------------------------------
    $('#pfmp-radius').on('input change', function () {
      $('#pfmp-radius-value').text($(this).val() + 'px');
      applyStyle();
    });
    $('#pfmp-shadow').on('change', applyStyle);
    $('#pfmp-bg-repeat, #pfmp-bg-cover').on('change', applyStyle);

    // --- card style: background image media picker -----------------------
    var bgFrame;
    $('#pfmp-bg-pick').on('click', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { return; }
      if (bgFrame) { bgFrame.open(); return; }
      bgFrame = wp.media({
        title: cfg.chooseImage || 'Select background image',
        button: { text: cfg.useImage || 'Use this image' },
        library: { type: 'image' },
        multiple: false
      });
      bgFrame.on('select', function () {
        var att = bgFrame.state().get('selection').first().toJSON();
        $('#pfmp-bg-id').val(att.id);
        $('#pfmp-bg-url').val(att.url);
        $('#pfmp-bg-prev').html('<img src="' + att.url + '" alt="">');
        $('#pfmp-bg-clear').show();
        applyStyle();
      });
      bgFrame.open();
    });
    $('#pfmp-bg-clear').on('click', function (e) {
      e.preventDefault();
      $('#pfmp-bg-id').val('');
      $('#pfmp-bg-url').val('');
      $('#pfmp-bg-prev').empty();
      $(this).hide();
      applyStyle();
    });

    // --- publication name, tagline -----------------------------------
    $('#pfmp-pub, #pfmp-tag').on('input change', applyBranding);

    // The logo picker lives in pfmp-admin.js (shared with older screens);
    // it fires this custom event after picking or clearing a logo so the
    // preview can stay in sync without the two files depending on each
    // other's internals.
    $(document).on('pfmp:logo-changed', applyBranding);

    // --- language switcher: on/off and offered languages ------------------
    $('#pfmp-langtoggle').on('change', applyLanguages);
    $(document).on('change', '.pfmp-lang-grid input[type="checkbox"]', applyLanguages);

    // --- layout ---------------------------------------------------------
    $(document).on('change', 'input[name="pfmp_settings[layout]"]', applyLayout);

    // --- unsubscribe off-ramp ---------------------------------------------
    $(document).on('change', 'input[name="pfmp_settings[offramp]"]', applyOfframp);

    // --- custom CSS -------------------------------------------------------
    $('#pfmp-css').on('input change', applyCustomCss);

    refreshTokenStates();
    applyAll();
    showState('manage');
  });
})(jQuery);
