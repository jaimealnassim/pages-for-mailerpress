/* Pages for MailerPress admin: color pickers, language toggle, media logo
 * picker. Settings sections are native <details> accordions now, so they
 * need no JS to expand or collapse. */
(function ($) {
  'use strict';

  $(function () {
    // Color pickers now live on the Builder screen (see pfmp-builder.js).

    // Language dependency toggle
    var $langToggle = $('#pfmp-langtoggle');
    function syncLang() {
      $('.pfmp-langdep').css('opacity', $langToggle.is(':checked') ? '' : '0.5');
      $('.pfmp-langdep').find('input,select').prop('disabled', !$langToggle.is(':checked'));
    }
    if ($langToggle.length) {
      // keep English checkbox disabled regardless
      $langToggle.on('change', function () {
        syncLang();
        $('.pfmp-langdep input[value="en"]').prop('disabled', true);
      });
      syncLang();
      $('.pfmp-langdep input[value="en"]').prop('disabled', true);
    }

    // Media logo picker
    var frame;
    var PFMPAdmin = window.PFMPAdmin || {};
    $('#pfmp-logo-pick').on('click', function (e) {
      e.preventDefault();
      if (typeof wp === 'undefined' || !wp.media) { return; }
      if (frame) { frame.open(); return; }
      frame = wp.media({
        title: PFMPAdmin.choose || 'Select logo',
        button: { text: PFMPAdmin.useThis || 'Use this logo' },
        library: { type: 'image' },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $('#pfmp-logo-id').val(att.id);
        $('#pfmp-logo-url').val(att.url);
        $('.pfmp-logo-prev').html('<img src="' + att.url + '" alt="">');
        $('#pfmp-logo-clear').show();
        $(document).trigger('pfmp:logo-changed');
      });
      frame.open();
    });

    $('#pfmp-logo-clear').on('click', function (e) {
      e.preventDefault();
      $('#pfmp-logo-id').val('');
      $('#pfmp-logo-url').val('');
      $('.pfmp-logo-prev').html('<span>M</span>');
      $(this).hide();
      $(document).trigger('pfmp:logo-changed');
    });
  });
})(jQuery);
