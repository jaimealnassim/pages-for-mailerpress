/* Pages for MailerPress: color derivation engine, ported from
 * includes/class-pfmp-palette.php so the Builder can preview instantly.
 * Keep this in sync with the PHP version if the formula ever changes. */
(function (global) {
  'use strict';

  function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }

  function hexToHsl(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) { hex = hex.split('').map(function (c) { return c + c; }).join(''); }
    var r = parseInt(hex.substr(0, 2), 16) / 255;
    var g = parseInt(hex.substr(2, 2), 16) / 255;
    var b = parseInt(hex.substr(4, 2), 16) / 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b);
    var h = 0, s = 0, l = (max + min) / 2;
    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      switch (max) {
        case r: h = (g - b) / d + (g < b ? 6 : 0); break;
        case g: h = (b - r) / d + 2; break;
        default: h = (r - g) / d + 4;
      }
      h *= 60;
    }
    return { h: h, s: s * 100, l: l * 100 };
  }

  function hueToRgb(p, q, t) {
    if (t < 0) { t += 1; }
    if (t > 1) { t -= 1; }
    if (t < 1 / 6) { return p + (q - p) * 6 * t; }
    if (t < 1 / 2) { return q; }
    if (t < 2 / 3) { return p + (q - p) * (2 / 3 - t) * 6; }
    return p;
  }

  function hslToHex(h, s, l) {
    h = ((h % 360) + 360) % 360 / 360;
    s = clamp(s, 0, 100) / 100;
    l = clamp(l, 0, 100) / 100;
    var r, g, b;
    if (s === 0) {
      r = g = b = l;
    } else {
      var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
      var p = 2 * l - q;
      r = hueToRgb(p, q, h + 1 / 3);
      g = hueToRgb(p, q, h);
      b = hueToRgb(p, q, h - 1 / 3);
    }
    function hx(v) { return Math.round(v * 255).toString(16).padStart(2, '0').toUpperCase(); }
    return '#' + hx(r) + hx(g) + hx(b);
  }

  function relativeLuminance(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) { hex = hex.split('').map(function (c) { return c + c; }).join(''); }
    function chan(v) {
      v = v / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    }
    var r = chan(parseInt(hex.substr(0, 2), 16));
    var g = chan(parseInt(hex.substr(2, 2), 16));
    var b = chan(parseInt(hex.substr(4, 2), 16));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  }

  // Same rule as WordPress's sanitize_hex_color(): #abc or #aabbcc.
  var HEX = /^#([0-9a-f]{3}){1,2}$/i;

  /**
   * @param {string} accent hex color
   * @param {Object} overrides {background,surface,text,muted,border} hex or ''
   * @returns {Object} resolved palette
   */
  function resolve(accent, overrides) {
    overrides = overrides || {};
    if (!HEX.test(accent)) { accent = '#14392E'; }
    var hsl = hexToHsl(accent);

    var auto = {
      background: hslToHex(hsl.h, clamp(hsl.s * 0.35, 6, 30), 97),
      surface: '#FFFFFF',
      text: hslToHex(hsl.h, clamp(hsl.s * 0.30, 4, 22), 13),
      muted: hslToHex(hsl.h, clamp(hsl.s * 0.22, 3, 18), 42),
      border: hslToHex(hsl.h, clamp(hsl.s * 0.28, 4, 22), 88)
    };

    var palette = { accent: accent };
    ['background', 'surface', 'text', 'muted', 'border'].forEach(function (key) {
      var o = overrides[key];
      palette[key] = (o && HEX.test(o)) ? o : auto[key];
    });
    palette.buttonText = relativeLuminance(accent) > 0.42 ? '#14181A' : '#FFFFFF';
    return palette;
  }

  // Mirrors PFMP_Palette::resolve_dark().
  function resolveDark(accent) {
    if (!HEX.test(accent)) { accent = '#14392E'; }
    var hsl = hexToHsl(accent);
    var tint = clamp(hsl.s * 0.3, 4, 18);
    return {
      accent: hslToHex(hsl.h, clamp(hsl.s * 0.6, 25, 60), 68),
      buttonText: hslToHex(hsl.h, tint, 7),
      background: hslToHex(hsl.h, tint, 7),
      surface: hslToHex(hsl.h, tint, 11),
      text: hslToHex(hsl.h, 10, 92),
      muted: hslToHex(hsl.h, 8, 70),
      border: hslToHex(hsl.h, tint, 22)
    };
  }

  function contrast(a, b) {
    var la = relativeLuminance(a), lb = relativeLuminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  }

  global.PFMPPalette = { resolve: resolve, resolveDark: resolveDark, contrast: contrast, isHex: function (v) { return HEX.test(v); }, hexToHsl: hexToHsl, hslToHex: hslToHex };
})(window);
