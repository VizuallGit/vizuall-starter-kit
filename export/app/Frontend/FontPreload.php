<?php

namespace App\Frontend;

/**
 * Hvilke fontfiler siden skal bede om med det samme.
 *
 * Uden preload finder browseren først fonten, når den har hentet
 * public/fonts/fonts.css og set at teksten bruger den: HTML → fonts.css →
 * .woff2. Preload springer det midterste led over.
 *
 * Kun de to fonte der altid står øverst på siden: brødteksten (--font-base
 * med --body-weight) og overskrifterne (--font-heading med --heading-weight).
 * Kilden er fonts.css og temaets tokens, ikke en liste her. En font fra et
 * Adobe-kit står ikke i fonts.css og preloades derfor ikke.
 *
 * Filen vælges som browseren selv vælger den: kun den første familie i
 * stakken tæller (er den ikke en webfont, henter browseren ikke de næste), kun
 * normal stil, kun et face der dækker latinske bogstaver, og vægten efter
 * CSS' regler for nærmeste vægt. Borna med kun 500 bruges altså til en
 * overskrift på 700.
 */
class FontPreload
{
    private const NAMED_WEIGHTS = [
        'thin' => 100, 'extralight' => 200, 'light' => 300, 'normal' => 400,
        'medium' => 500, 'semibold' => 600, 'bold' => 700, 'extrabold' => 800, 'black' => 900,
    ];

    private const TYPES = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];

    /** `<link rel="preload">` til brødtekstens og overskrifternes fontfiler. */
    public static function links(string $fontsCss, array $tokens): string
    {
        $links = '';

        foreach (static::urls($fontsCss, $tokens) as $url) {
            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $type = isset(self::TYPES[$ext]) ? ' type="'.self::TYPES[$ext].'"' : '';
            $links .= '<link rel="preload" href="'.e($url).'" as="font"'.$type.' crossorigin>';
        }

        return $links;
    }

    /**
     * Fontfilerne som sti fra sitets rod, uden dubletter.
     *
     * @param  array<string, string>  $tokens  ThemeTokens::tokens()
     * @return list<string>
     */
    public static function urls(string $fontsCss, array $tokens): array
    {
        $faces = static::faces($fontsCss);
        $wanted = [
            [static::family($tokens, 'font-base'), static::weight($tokens['body-weight'] ?? null, 400)],
            [static::family($tokens, 'font-heading'), static::weight($tokens['heading-weight'] ?? null, 700)],
        ];

        $urls = [];

        foreach ($wanted as [$family, $weight]) {
            if ($family === null) {
                continue;
            }

            $face = static::match(array_filter($faces, fn ($f) => strcasecmp($f['family'], $family) === 0), $weight);

            if ($face) {
                $urls[] = $face['url'];
            }
        }

        return array_values(array_unique($urls));
    }

    /** Den første familie i `--font-base: 'Inter', 'Helvetica', …`. */
    private static function family(array $tokens, string $name, int $depth = 0): ?string
    {
        $value = trim($tokens[$name] ?? '');

        // --font-heading: var(--font-base) peger på brødtekstens font.
        if (preg_match('/^var\(\s*--([\w-]+)/', $value, $ref)) {
            return $depth < 3 ? static::family($tokens, $ref[1], $depth + 1) : null;
        }

        $first = trim(explode(',', $value)[0], " \t\"'");

        return $first === '' ? null : $first;
    }

    private static function weight(?string $value, int $default): int
    {
        $value = strtolower(trim((string) $value));

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/^var\(\s*--font-weight-(\w+)/', $value, $named)) {
            $value = $named[1];
        }

        return self::NAMED_WEIGHTS[$value] ?? $default;
    }

    /**
     * fonts.css' @font-face-regler med normal stil der dækker latinske bogstaver.
     *
     * @return list<array{family: string, url: string, min: int, max: int}>
     */
    private static function faces(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/@font-face\s*\{([^}]*)\}/i', $css, $blocks);

        $faces = [];

        foreach ($blocks[1] as $block) {
            if (! preg_match('/font-family\s*:\s*["\']?([^"\';]+?)["\']?\s*;/i', $block, $family)
                || ! preg_match('/src\s*:[^;]*?url\(\s*["\']?([^"\')]+)["\']?\s*\)/i', $block, $src)) {
                continue;
            }

            if (preg_match('/font-style\s*:\s*(\w+)/i', $block, $style) && strtolower($style[1]) !== 'normal') {
                continue;
            }

            if (preg_match('/unicode-range\s*:\s*([^;]+)/i', $block, $range) && ! static::coversLatin($range[1])) {
                continue;
            }

            $url = trim($src[1]);

            if (str_starts_with($url, 'data:')) {
                continue;
            }

            [$min, $max] = [400, 400];

            if (preg_match('/font-weight\s*:\s*([\w]+)(?:\s+(\d+))?/i', $block, $w)) {
                $min = static::weight($w[1], 400);
                $max = isset($w[2]) ? (int) $w[2] : $min;
            }

            $faces[] = [
                'family' => trim($family[1]),
                // Stierne i fonts.css er relative til filen selv.
                'url' => preg_match('#^(?:/|https?://)#i', $url) ? $url : '/fonts/'.preg_replace('#^\./#', '', $url),
                'min' => min($min, $max),
                'max' => max($min, $max),
            ];
        }

        return $faces;
    }

    /** Dækker unicode-range bogstavet "a"? */
    private static function coversLatin(string $range): bool
    {
        foreach (explode(',', $range) as $part) {
            $part = strtoupper(trim(preg_replace('/^U\+/i', '', trim($part))));
            [$low, $high] = str_contains($part, '-')
                ? explode('-', $part, 2)
                : [str_replace('?', '0', $part), str_replace('?', 'F', $part)];

            if (ctype_xdigit($low) && ctype_xdigit($high) && hexdec($low) <= 0x61 && 0x61 <= hexdec($high)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Den face browseren vælger til vægten: en der rummer den, ellers den
     * nærmeste efter CSS Fonts' regel (under 400 søges lettere først, over 500
     * tungere først, 400–500 op til 500, så lettere, så tungere).
     */
    private static function match(array $faces, int $weight): ?array
    {
        $best = null;
        $bestKey = null;

        foreach ($faces as $face) {
            if ($face['min'] <= $weight && $weight <= $face['max']) {
                return $face;
            }

            $heavier = $face['min'] > $weight;
            $distance = $heavier ? $face['min'] - $weight : $weight - $face['max'];

            $rank = match (true) {
                $weight < 400 => $heavier ? 1 : 0,
                $weight > 500 => $heavier ? 0 : 1,
                default => $heavier ? ($face['min'] <= 500 ? 0 : 2) : 1,
            };

            $key = [$rank, $distance];

            if ($bestKey === null || $key < $bestKey) {
                [$best, $bestKey] = [$face, $key];
            }
        }

        return $best;
    }
}
