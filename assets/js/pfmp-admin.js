/* Pages for MailerPress admin: language section state and the logo picker.
 * Settings sections are native <details> accordions, so they need no JS. */
(function ($) {
  'use strict';

  $(function () {
    var strings = window.PFMPAdmin || {};

    // Language options depend on the switcher being on. They are dimmed and
    // made inert when it's off, never disabled: disabled fields are left out
    // of the form, which would wipe the chosen languages on Save.
    var $langToggle = $('#pfmp-langtoggle');
    function syncLang() {
      var on = $langToggle.is(':checked');
      $('.pfmp-langdep').toggleClass('is-off', !on).prop('inert', !on);
    }
    if ($langToggle.length) {
      $langToggle.on('change', syncLang);
      syncLang();
    }

    function letter() {
      var name = $.trim($('#pfmp-pub').val() || strings.pubName || '');
      return name ? Array.from(name)[0].toUpperCase() : 'M';
    }

    // Logo picker. Elements are built with jQuery, never by concatenating
    // a URL into HTML.
    var frame;
    $('#pfmp-logo-pick').on('click', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { return; }
      if (frame) { frame.open(); return; }
      frame = wp.media({
        title: strings.choose || 'Select logo',
        button: { text: strings.useThis || 'Use this logo' },
        library: { type: 'image' },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $('#pfmp-logo-id').val(att.id);
        $('#pfmp-logo-url').val(att.url);
        $('.pfmp-logo-prev').empty().append($('<img>', { src: att.url, alt: '' }));
        $('#pfmp-logo-clear').show();
        $(document).trigger('pfmp:logo-changed');
      });
      frame.open();
    });

    $('#pfmp-logo-clear').on('click', function (e) {
      e.preventDefault();
      $('#pfmp-logo-id').val('');
      $('#pfmp-logo-url').val('');
      $('.pfmp-logo-prev').empty().append($('<span>').text(letter()));
      $(this).hide();
      $(document).trigger('pfmp:logo-changed');
    });
  });
})(jQuery);
