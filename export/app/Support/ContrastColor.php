<?php

namespace App\Support;

class ContrastColor
{
    public static function pick(string $bg, string $light, string $dark): string
    {
        // CSS variable strings (e.g. var(--primary-500)) cannot be resolved server-side.
        // Palette variables are typically dark, so white text is the safe fallback.
        if (str_starts_with(ltrim($bg), 'var(') || str_starts_with(ltrim($bg), '--')) {
            return $light;
        }
        return self::luminance($bg) > 0.179 ? $dark : $light;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $hex)) {
            return 0; // unknown format → treat as dark → return light
        }
        [$r, $g, $b] = array_map('hexdec', str_split(substr($hex, 0, 6), 2));
        $lin = fn($c) => ($c /= 255) <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }
}
