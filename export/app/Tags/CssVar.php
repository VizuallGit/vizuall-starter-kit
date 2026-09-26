<?php

namespace App\Tags;

use Statamic\Tags\Tags;

/**
 * `{{ css_var name="container-width" px="true" }}` — one custom property from
 * `resources/css/var.css`.
 *
 * Glide asks for a pixel width. `px="true"` turns a rem or em length into
 * pixels at 16px per rem, the same scale the old theme setting used.
 */
class CssVar extends Tags
{
    public static $handle = 'css_var';

    public function index(): string
    {
        $name = trim((string) $this->params->get('name'), '-');
        $value = $name === '' ? '' : (static::declarations()[$name] ?? '');

        if (! $this->params->bool('px')) {
            return $value;
        }

        $px = static::pixels($value);

        return $px === null ? '' : (string) $px;
    }

    /**
     * @return array<string, string>
     */
    public static function declarations(): array
    {
        $css = @file_get_contents(resource_path('css/var.css'));

        if (! $css || ! preg_match('/:root\s*\{([^}]*)\}/s', $css, $block)) {
            return [];
        }

        $body = preg_replace('#/\*.*?\*/#s', '', $block[1]);
        preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $body, $matches, PREG_SET_ORDER);

        $out = [];

        foreach ($matches as [, $name, $value]) {
            $out[$name] = trim($value);
        }

        return $out;
    }

    /** A rem, em or px length as pixels, or null when it is not a length. */
    public static function pixels(string $value): ?float
    {
        if (! preg_match('/^(\d*\.?\d+)(rem|em|px)$/', trim($value), $match)) {
            return null;
        }

        $n = (float) $match[1];

        return $match[2] === 'px' ? $n : $n * 16;
    }
}
