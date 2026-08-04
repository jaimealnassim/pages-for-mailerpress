<?php
defined('ABSPATH') || exit;

/**
 * Derives a full, contrast-safe page palette from a single accent color, with
 * per-token manual overrides. This is the engine behind the Builder screen's
 * "Auto" color rows.
 */
class PFMP_Palette {

    /**
     * Named starting points. Each is just an accent color; every other token
     * is derived from it unless the admin overrides a specific token.
     */
    public static function presets() {
        return array(
            'meadow' => array('label' => __('Meadow', 'pages-for-mailerpress'), 'accent' => '#14392E'),
            'slate'  => array('label' => __('Slate', 'pages-for-mailerpress'),  'accent' => '#334155'),
            'ember'  => array('label' => __('Ember', 'pages-for-mailerpress'),  'accent' => '#B0533D'),
            'orchid' => array('label' => __('Orchid', 'pages-for-mailerpress'), 'accent' => '#6B4E9E'),
            'ink'    => array('label' => __('Ink', 'pages-for-mailerpress'),    'accent' => '#111827'),
            'sand'   => array('label' => __('Sand', 'pages-for-mailerpress'),   'accent' => '#8A6A2E'),
        );
    }

    /**
     * The five derivable tokens, in a stable order for rendering rows.
     */
    public static function tokens() {
        return array(
            'background' => __('Page background', 'pages-for-mailerpress'),
            'surface'    => __('Card background', 'pages-for-mailerpress'),
            'text'       => __('Text', 'pages-for-mailerpress'),
            'muted'      => __('Muted text', 'pages-for-mailerpress'),
            'border'     => __('Borders', 'pages-for-mailerpress'),
        );
    }

    /**
     * Resolve the full palette for a settings array: {accent, overrides{}}.
     * Any token left blank in overrides is computed from the accent.
     *
     * @return array{accent:string,buttonText:string,background:string,surface:string,text:string,muted:string,border:string}
     */
    public static function resolve($accent, $overrides = array()) {
        $accent = self::safeHex($accent, '#14392E');
        $hsl    = self::hexToHsl($accent);

        $auto = array(
            'background' => self::hslToHex($hsl['h'], self::clamp($hsl['s'] * 0.35, 6, 30), 97),
            'surface'    => '#FFFFFF',
            'text'       => self::hslToHex($hsl['h'], self::clamp($hsl['s'] * 0.30, 4, 22), 13),
            'muted'      => self::hslToHex($hsl['h'], self::clamp($hsl['s'] * 0.22, 3, 18), 42),
            'border'     => self::hslToHex($hsl['h'], self::clamp($hsl['s'] * 0.28, 4, 22), 88),
        );

        $palette = array('accent' => $accent);
        foreach (self::tokens() as $key => $label) {
            $override = isset($overrides[$key]) ? sanitize_hex_color((string) $overrides[$key]) : '';
            $palette[$key] = $override ? $override : $auto[$key];
        }

        // White or near-black text on the accent button, whichever contrasts more.
        $palette['buttonText'] = self::relativeLuminance($accent) > 0.42 ? '#14181A' : '#FFFFFF';

        return $palette;
    }

    /**
     * Which tokens are currently on "Auto" (no override saved), for the
     * Builder UI to render swatches correctly.
     */
    public static function autoFlags($overrides) {
        $flags = array();
        foreach (array_keys(self::tokens()) as $key) {
            $flags[$key] = empty($overrides[$key]);
        }
        return $flags;
    }

    private static function clamp($n, $min, $max) {
        return max($min, min($max, $n));
    }

    private static function safeHex($hex, $fallback) {
        $clean = sanitize_hex_color((string) $hex);
        return $clean ? $clean : $fallback;
    }

    private static function hexToHsl($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;

        if ($max === $min) {
            $h = 0;
            $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r:
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $d + 2;
                    break;
                default:
                    $h = ($r - $g) / $d + 4;
            }
            $h *= 60;
        }

        return array('h' => $h, 's' => $s * 100, 'l' => $l * 100);
    }

    private static function hslToHex($h, $s, $l) {
        $h = fmod($h, 360) / 360;
        $s = self::clamp($s, 0, 100) / 100;
        $l = self::clamp($l, 0, 100) / 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hueToRgb($p, $q, $t) {
        if ($t < 0) { $t += 1; }
        if ($t > 1) { $t -= 1; }
        if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
        if ($t < 1 / 2) { return $q; }
        if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
        return $p;
    }

    /**
     * WCAG relative luminance (0 = black, 1 = white), used to pick readable
     * button text on top of the accent color.
     */
    private static function relativeLuminance($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $chan = function ($v) {
            $v = $v / 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };
        $r = $chan(hexdec(substr($hex, 0, 2)));
        $g = $chan(hexdec(substr($hex, 2, 2)));
        $b = $chan(hexdec(substr($hex, 4, 2)));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
