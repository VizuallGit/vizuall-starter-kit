<?php

namespace App\Frontend;

/**
 * Den offentlige sides CSS, renset før siden sendes eller udgives.
 *
 *  1. Sitets byggede stylesheet (/build/assets/*.css) og fonts.css lægges ind
 *     i siden som `<style>`. Intet skal hentes, før siden kan tegnes.
 *  2. Af utility-klasserne kommer kun dem med, siden bruger.
 *  3. En regel der står flere gange — dockens bagte Tailwind (sve_tw) gentager
 *     `flex`, `grid` og `w-full` for hver sektion — står én gang.
 *  4. Af fonts.css kommer kun de fonte, sidens CSS eller HTML nævner.
 *
 * "Bruger" læses af den færdige HTML og de scripts siden henter, som Tailwind
 * læser kildefiler: et ord der ligner klassen, tæller. Det tager hellere en
 * regel for meget med end én for lidt; en ekstra regel koster bytes, en
 * manglende ødelægger siden. Regler i base/components, regler uden klasse og
 * temaets værdier filtreres ikke.
 *
 * Af identiske kopier beholdes den SIDSTE. En regel vinder over en anden i
 * samme lag ved at stå senere, så den sidste kopi er den, der afgør hvem der
 * vinder; de tidligere ændrer intet. Siden ser derfor ud præcis som før, også
 * når to klasser på samme element vil det modsatte. Det samme gælder custom
 * properties i temaet (`:root{--x}`) og `@property`. En `@layer a,b;` der
 * gentages, er derimod den FØRSTE der tæller; de senere fjernes.
 *
 * Kun `<style>` i `<head>` efter sitets stylesheet, uden media-attribut og
 * uden en anden type end text/css, renses; alt andet står som det blev
 * skrevet. Udkommenteret, i `<noscript>` eller `<template>` tæller ikke.
 *
 * Det renseren ikke kan se: klasser der først kommer med HTML hentet senere
 * (fetch/AJAX), og klasser i scripts der ikke ligger i /build/assets/. Brug
 * dem i sidens HTML, eller lad scriptet ligge i sitets build.
 */
final class PageCss
{
    private const SITE_CSS = '#^/build/assets/[\w.-]+\.css$#';

    private const SITE_JS = '#^/build/assets/[\w.-]+\.js$#';

    private const FONTS_CSS = '#^/fonts/fonts\.css$#';

    /** @var array<string, true> */
    private array $used = [];

    private string $usedText = '';

    private int $seq = 0;

    /** @var array<string, int> nøgle → nummeret på dens sidste forekomst */
    private array $last = [];

    /** @var array<string, true> */
    private array $statements = [];

    /** @var array<string, true> lag der allerede er nævnt */
    private array $layers = [];

    /** @var array<string, true>|null custom properties siden refererer; null under første gennemløb */
    private ?array $refs = null;

    /**
     * @param  callable(string): ?string  $read  indholdet af en fil siden linker, ud fra stien
     *                                           (/build/assets/…, /fonts/fonts.css); null hvis den ikke findes
     */
    public static function clean(string $html, callable $read): string
    {
        return (new self)->run($html, $read);
    }

    private function run(string $html, callable $read): string
    {
        $head = stripos($html, '</head>');

        if ($head === false) {
            return $html;
        }

        $live = Html::live(substr($html, 0, $head));
        $links = self::links($live);
        $site = array_values(array_filter($links, fn ($l) => $l['kind'] === 'site'));

        if (! $site) {
            return $html;
        }

        $text = preg_replace('#<style\b[^>]*>.*?</style\s*>#is', ' ', $html) ?? $html;
        $this->usedText = html_entity_decode($text, ENT_QUOTES | ENT_HTML5).' '.self::scripts($html, $read);
        $this->used = self::tokens($this->usedText);

        $sheets = [];

        foreach ($site as $link) {
            $css = $read($link['path']);
            $items = $css === null || stripos($css, '</style') !== false ? null : CssBlocks::parse($css);

            if ($items !== null) {
                $sheets[] = ['offset' => $link['offset'], 'length' => $link['length'], 'open' => '<style>', 'items' => $items, 'path' => $link['path']];
            }
        }

        if (! $sheets) {
            return $html;
        }

        $first = $sheets[0]['offset'];
        preg_match_all('#<style\b([^>]*)>(.*?)</style\s*>#is', $live, $styles, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($styles as $style) {
            $type = preg_match('/\btype\s*=\s*["\']?([^"\'\s>]*)/i', $style[1][0], $t) ? strtolower($t[1]) : '';

            if ($style[0][1] < $first || preg_match('/\bmedia\s*=/i', $style[1][0]) || ($type !== '' && $type !== 'text/css')) {
                continue;
            }

            if (($items = CssBlocks::parse($style[2][0])) !== null) {
                $sheets[] = ['offset' => $style[0][1], 'length' => strlen($style[0][0]), 'open' => '<style'.$style[1][0].'>', 'items' => $items];
            }
        }

        usort($sheets, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        foreach ($sheets as $n => $sheet) {
            $sheets[$n]['tree'] = $this->walk($sheet['items']);
        }

        // Hvilke custom properties bruges, når de ubrugte regler er væk? Svaret
        // afgør hvilke @property-regler og Tailwinds fallback-værdier der skal med.
        $outside = self::cut($html, $sheets);
        $this->refs = self::properties(implode('', array_map(fn ($s) => $this->render($s['tree']), $sheets)).$outside);

        $edits = [];
        $css = '';

        foreach ($sheets as $sheet) {
            $out = $this->render($sheet['tree']);
            $css .= $out;
            $edits[] = [$sheet['offset'], $sheet['length'], $out === '' ? '' : $sheet['open'].$out.'</style>'];
        }

        $inlined = array_column($sheets, 'path');

        foreach ($links as $link) {
            if ($link['kind'] === 'fonts' && ($fonts = self::fonts($read($link['path']), $link['path'], $css.$outside)) !== null) {
                $edits[] = [$link['offset'], $link['length'], $fonts === '' ? '' : '<style>'.$fonts.'</style>'];
                $inlined[] = $link['path'];
            }
        }

        foreach ($links as $link) {
            if ($link['kind'] === 'preload' && in_array($link['path'], $inlined, true)) {
                $edits[] = [$link['offset'], $link['length'], ''];
            }
        }

        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($edits as [$offset, $length, $replacement]) {
            $html = substr_replace($html, $replacement, $offset, $length);
        }

        return $html;
    }

    /**
     * Træet for én stylesheet: reglerne i utilities-laget filtreret efter brug,
     * og hver regel, custom property og @property nummereret, så render() kan
     * se hvilken forekomst der er den sidste.
     */
    private function walk(array $items, string $context = '', ?string $layer = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item[0] === 'statement' && $context === '' && preg_match('/^@layer\s+(.+)$/is', $item[1], $names)) {
                $key = CssBlocks::preludeKey($item[1]);

                if (! isset($this->statements[$key])) {
                    $this->statements[$key] = true;
                    $this->declare($names[1]);
                    $tree[] = $item;
                }

                continue;
            }

            if ($item[0] !== 'rule') {
                $tree[] = $item;

                continue;
            }

            [, $prelude, $body] = $item;

            if ($context === '' && preg_match('/^@property\s+(--[\w-]+)/i', $prelude, $property)) {
                $tree[] = $this->occurrence('property', $prelude, $body, 'p|'.CssBlocks::key($prelude, $body), $property[1]);

                continue;
            }

            if ($context === '' && preg_match('/^@layer\s+([\w-]+)$/i', $prelude, $name) && ($children = CssBlocks::parse($body)) !== null) {
                $tree[] = ['layer', $prelude, $this->walk($children, '@layer '.$name[1], $name[1]), isset($this->layers[$name[1]])];
                $this->declare($name[1]);

                continue;
            }

            if ($layer === null) {
                $tree[] = $item;
            } elseif (str_starts_with($prelude, '@')) {
                $children = preg_match('/^@(?:media|supports|container|scope|starting-style)\b/i', $prelude) ? CssBlocks::parse($body) : null;
                $tree[] = $children === null ? $item : ['block', $prelude, $this->walk($children, $context.'>'.CssBlocks::preludeKey($prelude), $layer)];
            } elseif ($layer === 'utilities') {
                $selectors = CssBlocks::split($prelude, ',');
                $kept = array_values(array_filter($selectors, fn ($s) => $this->isUsed($s)));

                if ($kept) {
                    $prelude = count($kept) === count($selectors) ? $prelude : implode(',', array_map('trim', $kept));
                    $tree[] = $this->occurrence('utility', $prelude, $body, 'u|'.$context.'|'.CssBlocks::key($prelude, $body));
                }
            } elseif (($layer === 'theme' || $layer === 'properties') && ! str_contains($body, '{')) {
                $tree[] = ['decls', $prelude, $this->declarations($prelude, $body, $context)];
            } else {
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Erklæringerne i en temaregel; custom properties nummereres pr. selector,
     * resten (og alt med !important) står som skrevet.
     */
    private function declarations(string $prelude, string $body, string $context): array
    {
        $decls = [];
        $selector = CssBlocks::preludeKey($prelude);

        foreach (CssBlocks::split($body, ';') as $decl) {
            if (preg_match('/^\s*(--[\w-]+)\s*:/', $decl, $name) && stripos($decl, '!important') === false) {
                $decls[] = $this->occurrence('decl', $decl, '', "d|{$context}|{$selector}|{$name[1]}", $name[1]);
            } elseif (trim($decl) !== '') {
                $decls[] = ['text', $decl];
            }
        }

        return $decls;
    }

    private function occurrence(string $type, string $prelude, string $body, string $key, ?string $property = null): array
    {
        $this->last[$key] = ++$this->seq;

        return [$type, $prelude, $body, $key, $this->seq, $property];
    }

    private function declare(string $names): void
    {
        foreach (explode(',', $names) as $name) {
            $this->layers[trim($name)] = true;
        }
    }

    /**
     * Træet som CSS: kun den sidste forekomst af hver nøgle. I andet gennemløb
     * (når refs er kendt) også kun @property og fallback-værdier for de custom
     * properties siden bruger. I første gennemløb udelades de helt, så de ikke
     * tæller som brug af sig selv.
     */
    private function render(array $tree, ?string $layer = null): string
    {
        $css = '';

        foreach ($tree as $node) {
            $css .= match ($node[0]) {
                'utility' => $this->isLast($node) ? $node[1].'{'.$node[2].'}' : '',
                'property' => $this->refs !== null && $this->isLast($node) && isset($this->refs[$node[5]]) ? $node[1].'{'.$node[2].'}' : '',
                'layer' => $this->renderLayer($node),
                'block' => ($inner = $this->render($node[2], $layer)) === '' ? '' : $node[1].'{'.$inner.'}',
                'decls' => $this->renderDecls($node, $layer),
                default => CssBlocks::serialize([$node]),
            };
        }

        return $css;
    }

    private function renderLayer(array $node): string
    {
        preg_match('/^@layer\s+([\w-]+)$/i', $node[1], $name);
        $inner = $this->render($node[2], $name[1]);

        // En tom blok der nævner laget første gang, fastlægger lagenes rækkefølge.
        if ($inner === '' && $node[3]) {
            return '';
        }

        return $node[1].'{'.$inner.'}';
    }

    private function renderDecls(array $node, ?string $layer): string
    {
        $decls = [];

        foreach ($node[2] as $decl) {
            if ($decl[0] === 'text') {
                $decls[] = $decl[1];
            } elseif ($this->isLast($decl) && ($layer !== 'properties' || ($this->refs !== null && isset($this->refs[$decl[5]])))) {
                $decls[] = $decl[1];
            }
        }

        return $decls ? $node[1].'{'.implode(';', $decls).'}' : '';
    }

    private function isLast(array $node): bool
    {
        return $this->last[$node[3]] === $node[4];
    }

    /** Bruger siden mindst én af selectorens klasser? Uden klasser: altid med. */
    private function isUsed(string $selector): bool
    {
        $classes = CssBlocks::classes($selector);

        if (! $classes) {
            return true;
        }

        foreach ($classes as $class) {
            if (isset($this->used[$class])) {
                return true;
            }

            // Vilkårlige værdier, `bg-[url('/a.jpg')]` og `grid-cols-[1fr,2fr]`,
            // deles af tokens(); dem leder vi efter som de står.
            if (strpbrk($class, "[]()'\",") !== false && str_contains($this->usedText, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ordene i teksten, som klassenavne kan stå: `class="md:flex"`,
     * `:class="{ 'hidden': !open }"`, `{hidden: !open}`, `classList.add('flex')`.
     * Et ord med kolon giver også delen før kolonnet, så `{hidden:!open}` tæller
     * hidden; `md:grid` tæller md, ikke grid.
     *
     * @return array<string, true>
     */
    private static function tokens(string $text): array
    {
        $tokens = [];

        foreach (preg_split('/[\s"\'`<>={};,()]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $tokens[$token] = true;
            $tokens[trim($token, '!')] = true;

            if (str_contains($token, ':')) {
                $tokens[strstr($token, ':', true)] = true;
            }
        }

        return $tokens;
    }

    /** Indholdet af sitets byggede scripts, som siden henter eller preloader. */
    private static function scripts(string $html, callable $read): string
    {
        preg_match_all('#<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*["\']([^"\']+)["\'][^>]*>#i', $html, $refs);
        $js = '';

        foreach (array_unique($refs[1]) as $url) {
            $path = self::path($url);

            if ($path !== null && preg_match(self::SITE_JS, $path)) {
                $js .= ' '.($read($path) ?? '');
            }
        }

        return $js;
    }

    /**
     * Stylesheet- og preload-links i `<head>` til sitets byggede CSS og fonts.css.
     *
     * @return list<array{kind: string, path: string, offset: int, length: int}>
     */
    private static function links(string $head): array
    {
        preg_match_all('#<link\b[^>]*>#i', $head, $tags, PREG_OFFSET_CAPTURE);
        $links = [];

        foreach ($tags[0] as [$tag, $offset]) {
            $attrs = array_map(Html::decode(...), Html::attributes($tag));

            $rel = preg_split('/\s+/', strtolower($attrs['rel'] ?? ''));
            $path = self::path($attrs['href'] ?? '');

            if ($path === null || isset($attrs['disabled']) || (isset($attrs['media']) && strtolower($attrs['media']) !== 'all')) {
                continue;
            }

            $kind = match (true) {
                in_array('stylesheet', $rel, true) && preg_match(self::SITE_CSS, $path) === 1 => 'site',
                in_array('stylesheet', $rel, true) && preg_match(self::FONTS_CSS, $path) === 1 => 'fonts',
                in_array('preload', $rel, true) && strtolower($attrs['as'] ?? '') === 'style' => 'preload',
                default => null,
            };

            if ($kind !== null) {
                $links[] = ['kind' => $kind, 'path' => $path, 'offset' => $offset, 'length' => strlen($tag)];
            }
        }

        return $links;
    }

    /** Stien i en URL fra siden, eller null hvis det ikke er en sti. */
    private static function path(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/') && ! str_contains($path, '..') ? $path : null;
    }

    /** Siden uden de stylesheets der renses. */
    private static function cut(string $html, array $sheets): string
    {
        foreach (array_reverse($sheets) as $sheet) {
            $html = substr_replace($html, '', $sheet['offset'], $sheet['length']);
        }

        return $html;
    }

    /** @return array<string, true> */
    private static function properties(string $text): array
    {
        preg_match_all('/--[\w-]+/', $text, $names);

        return array_fill_keys($names[0], true);
    }

    /**
     * fonts.css med kun de @font-face hvis familie står i `$text`, og stierne
     * gjort absolutte. Null hvis filen ikke kan læses eller renses.
     */
    private static function fonts(?string $css, string $path, string $text): ?string
    {
        $items = $css === null || stripos($css, '</style') !== false ? null : CssBlocks::parse($css);

        if ($items === null) {
            return null;
        }

        $base = rtrim(dirname($path), '/').'/';
        $out = '';

        foreach ($items as $item) {
            if ($item[0] === 'raw' && str_starts_with($item[1], '/*')) {
                continue;
            }

            if ($item[0] === 'rule' && preg_match('/^@font-face$/i', $item[1])) {
                if (preg_match('/font-family\s*:\s*["\']?([^"\';]+?)["\']?\s*(?:;|$)/i', $item[2], $family) && stripos($text, trim($family[1])) === false) {
                    continue;
                }

                $item[2] = preg_replace_callback(
                    '/url\(\s*(["\']?)(?![\w+.-]+:|\/|#)([^"\')]+)\1\s*\)/i',
                    fn ($m) => 'url('.$m[1].$base.preg_replace('#^\./#', '', $m[2]).$m[1].')',
                    $item[2],
                );
            }

            $out .= CssBlocks::serialize([$item]);
        }

        return $out;
    }
}
