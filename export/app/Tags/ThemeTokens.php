<?php

namespace App\Tags;

use App\Frontend\FontPreload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
 * working. Literal colors are read, and a highlight — a color that points at
 * one of them (`--color-highlight-1: var(--color-primary-500)`), the Theme
 * panel's named palette colors. A color that points at anything else
 * (`--color-contrast-light: var(--contrast-light)`) is left to site.css.
 *
 * The fonts come along: public/fonts/fonts.css (the Theme panel's Fonts tab
 * writes it) is linked with its modification time in the URL, so a font
 * added on the server reaches visitors without a build or a stale cache.
 * The body and heading font files are preloaded (FontPreload), read from
 * the same two files, so a font changed in the Theme panel is the one
 * preloaded on the next request — an Adobe kit's fonts too.
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
    private const MANAGED = '/^(?:size-[\w-]+|spacing-[\w-]+|text-[\w-]+|container-width|gutter|font-base|font-heading|font-size(?:-h[1-6])?|line-height|heading-text-transform|body-color|body-bg|body-weight|heading-weight|heading-line-height|h[1-6]-(?:weight|line-height)|title-(?:font|size|weight|line-height|text-transform|letter-spacing)|button-[\w-]+|btn-radius|input-[\w-]+)$/';

    private const LITERAL_COLOR = '/^(?:#[0-9a-fA-F]{3,8}|(?:rgba?|hsla?|oklch|oklab|lab|lch|color)\(.*\))$/';

    /** A highlight's value: `var(--color-primary-500)`, captured as `color-primary-500`. */
    private const COLOR_POINTER = '/^var\(\s*--(color-[\w-]+)\s*\)$/';

    public function index(): string
    {
        $lines = [];
        $tokens = static::tokens();

        foreach ($tokens as $name => $value) {
            $lines[] = "--{$name}: {$value};";

            if (str_starts_with($name, 'color-')) {
                $lines[] = '--'.substr($name, 6).": var(--{$name});";
            }
        }

        return static::fontPreloads($tokens).static::fontsLink().($lines ? '<style>:root{'.implode('', $lines).'}</style>' : '');
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
     * `<link rel="preload">` for the font files fonts.css gives the body and
     * headings — also when they come from an Adobe Fonts kit it imports. A
     * kit's CSS chains to p.typekit.net before its fonts apply, so that host
     * is preconnected.
     */
    public static function fontPreloads(array $tokens): string
    {
        $file = public_path('fonts/fonts.css');

        if (! is_file($file)) {
            return '';
        }

        $css = (string) file_get_contents($file);
        $kits = FontPreload::kits($css);
        $preconnect = $kits ? '<link rel="preconnect" href="https://p.typekit.net">' : '';

        return $preconnect.FontPreload::links($css, $tokens, array_map(static::kitCss(...), $kits));
    }

    /**
     * An Adobe kit's CSS, kept for a day. A kit that does not answer is asked
     * again in ten minutes; until then its fonts are just not preloaded.
     */
    private static function kitCss(string $url): string
    {
        $key = 'theme-tokens-kit:'.md5($url);
        $css = Cache::get($key);

        if (is_string($css)) {
            return $css;
        }

        try {
            $response = Http::timeout(3)->get($url);
            $css = $response->successful() ? $response->body() : '';
        } catch (\Throwable) {
            $css = '';
        }

        Cache::put($key, $css, $css === '' ? now()->addMinutes(10) : now()->addDay());

        return $css;
    }

    /**
     * The literal colors, the highlights that point at one of them, and the
     * panel's other tokens in site.css' `@theme` blocks, name (without `--`)
     * => value, in file order.
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
        $pointers = [];

        foreach ($blocks[1] as $block) {
            $block = preg_replace('#/\*.*?\*/#s', '', $block);

            preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $name, $value]) {
                $value = trim($value);

                if (str_starts_with($name, 'color-')) {
                    if (preg_match(self::LITERAL_COLOR, $value)) {
                        $tokens[$name] = $value;
                    } elseif (preg_match(self::COLOR_POINTER, $value, $m) && $m[1] !== $name) {
                        $pointers[$name] = [$value, $m[1]];
                    }
                } elseif (preg_match(self::MANAGED, $name) && $value !== 'initial' && ! str_contains($value, "var(--{$name})")) {
                    $tokens[$name] = $value;
                }
            }
        }

        // A highlight comes along only when the color it points at is one we serve.
        foreach ($pointers as $name => [$value, $target]) {
            if (isset($tokens[$target])) {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }
}
