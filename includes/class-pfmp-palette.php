<?php
/**
 * Palette derivation.
 *
 * @package PagesForMailerPress
 */

defined('ABSPATH') || exit;

/**
 * Derives a full, contrast-safe page palette from a single accent color, with
 * per-token manual overrides. This is the engine behind the Builder screen's
 * "Auto" color rows.
 */
final class PFMP_Palette {

    /**
     * Named starting points. Each is just an accent color; every other token
     * is derived from it unless the admin overrides a specific token.
     */
    /**
     * @return array<string, array{label:string, accent:string}>
     */
    public static function presets(): array {
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
    /**
     * @return array<string, string>
     */
    public static function tokens(): array {
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
     * @param array<string, mixed> $overrides
     * @return array{accent:string,buttonText:string,background:string,surface:string,text:string,muted:string,border:string}
     */
    public static function resolve(mixed $accent, array $overrides = array()): array {
        $accent = self::safe_hex($accent, '#14392E');
        $hsl    = self::hex_to_hsl($accent);

        $auto = array(
            'background' => self::hsl_to_hex($hsl['h'], self::clamp($hsl['s'] * 0.35, 6, 30), 97),
            'surface'    => '#FFFFFF',
            'text'       => self::hsl_to_hex($hsl['h'], self::clamp($hsl['s'] * 0.30, 4, 22), 13),
            'muted'      => self::hsl_to_hex($hsl['h'], self::clamp($hsl['s'] * 0.22, 3, 18), 42),
            'border'     => self::hsl_to_hex($hsl['h'], self::clamp($hsl['s'] * 0.28, 4, 22), 88),
        );

        $palette = array('accent' => $accent);
        foreach (array_keys(self::tokens()) as $key) {
            $override      = is_string($overrides[$key] ?? null) ? sanitize_hex_color($overrides[$key]) : '';
            $palette[$key] = $override ?: $auto[$key];
        }

        // White or near-black text on the accent button, whichever contrasts more.
        $palette['buttonText'] = self::relative_luminance($accent) > 0.42 ? '#14181A' : '#FFFFFF';

        return $palette;
    }

    /**
     * Which tokens are currently on "Auto" (no override saved), for the
     * Builder UI to render swatches correctly.
     */
    /**
     * The dark-mode palette, derived from the accent alone: a near-black page
     * tinted with the accent's hue, and a lighter accent so buttons and links
     * stay readable on it. Same keys as resolve().
     *
     * @return array{accent:string,buttonText:string,background:string,surface:string,text:string,muted:string,border:string}
     */
    public static function resolve_dark(mixed $accent): array {
        $hsl = self::hex_to_hsl(self::safe_hex($accent, '#14392E'));
        $tint = self::clamp($hsl['s'] * 0.3, 4, 18);

        return array(
            'accent'     => self::hsl_to_hex($hsl['h'], self::clamp($hsl['s'] * 0.6, 25, 60), 68),
            'buttonText' => self::hsl_to_hex($hsl['h'], $tint, 7),
            'background' => self::hsl_to_hex($hsl['h'], $tint, 7),
            'surface'    => self::hsl_to_hex($hsl['h'], $tint, 11),
            'text'       => self::hsl_to_hex($hsl['h'], 10, 92),
            'muted'      => self::hsl_to_hex($hsl['h'], 8, 70),
            'border'     => self::hsl_to_hex($hsl['h'], $tint, 22),
        );
    }

    /**
     * WCAG contrast ratio between two colors, 1 to 21.
     */
    public static function contrast(string $a, string $b): float {
        $la = self::relative_luminance($a);
        $lb = self::relative_luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, bool>
     */
    public static function auto_flags(array $overrides): array {
        $flags = array();
        foreach (array_keys(self::tokens()) as $key) {
            $flags[$key] = empty($overrides[$key]);
        }
        return $flags;
    }

    private static function clamp(float $n, float $min, float $max): float {
        return max($min, min($max, $n));
    }

    private static function safe_hex(mixed $hex, string $fallback): string {
        return (is_string($hex) ? sanitize_hex_color($hex) : '') ?: $fallback;
    }

    /**
     * "#abc" or "#aabbcc" as six hex digits, no "#".
     */
    private static function expand(string $hex): string {
        $hex = ltrim($hex, '#');
        return strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex;
    }

    /**
     * @return array{h:float, s:float, l:float}
     */
    private static function hex_to_hsl(string $hex): array {
        $hex = self::expand($hex);
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;

        $h = 0.0;
        $s = 0.0;
        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = match ($max) {
                $r      => ($g - $b) / $d + ($g < $b ? 6 : 0),
                $g      => ($b - $r) / $d + 2,
                default => ($r - $g) / $d + 4,
            };
            $h *= 60;
        }

        return array('h' => $h, 's' => $s * 100, 'l' => $l * 100);
    }

    private static function hsl_to_hex(float $h, float $s, float $l): string {
        $h = fmod($h, 360) / 360;
        $s = self::clamp($s, 0, 100) / 100;
        $l = self::clamp($l, 0, 100) / 100;

        if ($s === 0.0) {
            $r = $l;
            $g = $l;
            $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue_to_rgb($p, $q, $h + 1 / 3);
            $g = self::hue_to_rgb($p, $q, $h);
            $b = self::hue_to_rgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02X%02X%02X', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hue_to_rgb(float $p, float $q, float $t): float {
        if ($t < 0) {
            ++$t;
        }
        if ($t > 1) {
            --$t;
        }
        if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
        if ($t < 1 / 2) { return $q; }
        if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
        return $p;
    }

    /**
     * WCAG relative luminance (0 = black, 1 = white), used to pick readable
     * button text on top of the accent color.
     */
    private static function relative_luminance(string $hex): float {
        $hex  = self::expand($hex);
        $chan = static function (float $v): float {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        $r = $chan(hexdec(substr($hex, 0, 2)));
        $g = $chan(hexdec(substr($hex, 2, 2)));
        $b = $chan(hexdec(substr($hex, 4, 2)));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
