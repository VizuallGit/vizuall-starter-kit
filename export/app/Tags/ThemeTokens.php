<?php

namespace App\Tags;

use Statamic\Tags\Tags;

/**
 * `{{ theme_tokens }}` — the theme from site.css' `@theme`, on `:root`.
 *
 * site.css is the one place the theme is written: colors
 * (`--color-primary-900: #11121b;`), the fluid size scale, fonts, text sizes
 * and the button. Vite bakes that into public/build at deploy, but Live
 * Preview's theme panel saves site.css on the server between deploys. This
 * tag reads the file on every request, so a saved value reaches the page
 * without a build. The style is unlayered, so it wins over Tailwind's
 * `@layer theme` — the built value only matters until the next request.
 *
 * Each color also gets its short name, `--primary-900: var(--color-primary-900)`,
 * so templates, CSS and saved field values that say `var(--primary-900)` keep
 * working. Only literal colors are read: a color that points at a variable
 * (`--color-contrast-light: var(--contrast-light)`) is left to site.css.
 *
 * The fonts come along: public/fonts/fonts.css (the Theme panel's Fonts tab
 * writes it) is linked with its modification time in the URL, so a font
 * added on the server reaches visitors without a build or a stale cache.
 */
class ThemeTokens extends Tags
{
    public static $handle = 'theme_tokens';

    /**
     * The tokens besides colors that must reach the page without a build: the
     * ones the theme panel edits, and Tailwind's spacing and text scales. Those
     * two are what `p-1300` or `text-650` read, and Tailwind only emits the
     * ones a build saw in use — a class written in the dock since then would
     * point at nothing until the next deploy.
     */
    private const MANAGED = '/^(?:size-[\w-]+|spacing-[\w-]+|text-[\w-]+|font-base|font-heading|font-size(?:-h[1-6])?|line-height|heading-text-transform|body-weight|heading-weight|heading-line-height|h[1-6]-(?:weight|line-height)|button-[\w-]+|btn-radius)$/';

    private const LITERAL_COLOR = '/^(?:#[0-9a-fA-F]{3,8}|(?:rgba?|hsla?|oklch|oklab|lab|lch|color)\(.*\))$/';

    public function index(): string
    {
        $lines = [];

        foreach (static::tokens() as $name => $value) {
            $lines[] = "--{$name}: {$value};";

            if (str_starts_with($name, 'color-')) {
                $lines[] = '--'.substr($name, 6).": var(--{$name});";
            }
        }

        return static::fontsLink().($lines ? '<style>:root{'.implode('', $lines).'}</style>' : '');
    }

    /** `<link>` to public/fonts/fonts.css, or nothing while there is no such file. */
    public static function fontsLink(): string
    {
        $file = public_path('fonts/fonts.css');

        if (! is_file($file)) {
            return '';
        }

        return '<link rel="stylesheet" href="/fonts/fonts.css?v='.filemtime($file).'">';
    }

    /**
     * The literal colors and the panel's other tokens in site.css' `@theme`
     * blocks, name (without `--`) => value, in file order.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        $css = @file_get_contents(resource_path('css/site.css'));

        if (! $css) {
            return [];
        }

        preg_match_all('/@theme\b[^{]*\{([^}]*)\}/', $css, $blocks);

        $tokens = [];

        foreach ($blocks[1] as $block) {
            $block = preg_replace('#/\*.*?\*/#s', '', $block);

            preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $name, $value]) {
                $value = trim($value);
                $keep = str_starts_with($name, 'color-')
                    ? preg_match(self::LITERAL_COLOR, $value)
                    : preg_match(self::MANAGED, $name) && $value !== 'initial' && ! str_contains($value, "var(--{$name})");

                if ($keep) {
                    $tokens[$name] = $value;
                }
            }
        }

        return $tokens;
    }
}
