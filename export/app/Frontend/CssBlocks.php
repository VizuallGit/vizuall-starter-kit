<?php

namespace App\Frontend;

/**
 * Lige nok CSS-læsning til PageCss: reglerne i en bygget stylesheet eller et
 * `<style>`, uden at forstå hvad de gør. Strenge, kommentarer og escapes
 * respekteres, så `content: "}"` eller klassen `.w-\[50\%\]` ikke afslutter
 * noget for tidligt.
 *
 * Et punkt er en af:
 *   ['rule', prelude, body]  — `prelude{body}`, også @media/@layer med indhold
 *   ['statement', text]      — `text;` på øverste niveau, fx `@layer a,b;`
 *   ['raw', text]            — en kommentar eller en uafsluttet rest, uændret
 */
final class CssBlocks
{
    /**
     * Punkterne på øverste niveau, eller null hvis klammerne ikke går op. Den
     * CSS kan PageCss ikke rense uden at ændre hvad browseren gør med den.
     *
     * @return list<array>|null
     */
    public static function parse(string $css): ?array
    {
        $items = [];
        $len = strlen($css);
        $depth = 0;
        $start = 0;
        $open = 0;

        for ($i = 0; $i < $len; $i++) {
            // Spring til næste tegn der betyder noget her.
            $i += strcspn($css, "\\\"'/{};", $i);

            if ($i >= $len) {
                break;
            }

            $c = $css[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                $i = self::stringEnd($css, $i);
            } elseif ($c === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $end = $end === false ? $len - 1 : $end + 1;

                if ($depth === 0 && trim(substr($css, $start, $i - $start)) === '') {
                    $items[] = ['raw', substr($css, $i, $end - $i + 1)];
                    $start = $end + 1;
                }

                $i = $end;
            } elseif ($c === '{') {
                if ($depth === 0) {
                    $open = $i;
                }
                $depth++;
            } elseif ($c === '}') {
                if (--$depth < 0) {
                    return null;
                }
                if ($depth === 0) {
                    $items[] = ['rule', trim(substr($css, $start, $open - $start)), substr($css, $open + 1, $i - $open - 1)];
                    $start = $i + 1;
                }
            } elseif ($c === ';' && $depth === 0) {
                $items[] = ['statement', trim(substr($css, $start, $i - $start))];
                $start = $i + 1;
            }
        }

        // En blok der aldrig lukkes (eller en streng der sluger `}`): browseren
        // lukker selv ved filens slutning, og det gætter vi ikke på.
        if ($depth > 0) {
            return null;
        }

        $rest = trim(substr($css, $start));

        if ($rest !== '') {
            $items[] = ['raw', $rest];
        }

        return $items;
    }

    /** @param  list<array>  $items */
    public static function serialize(array $items): string
    {
        $css = '';

        foreach ($items as $item) {
            $css .= match ($item[0]) {
                'rule' => $item[1].'{'.$item[2].'}',
                'statement' => $item[1].';',
                default => $item[1],
            };
        }

        return $css;
    }

    /**
     * Del ved `$sep` på øverste niveau: ikke inde i (), [] eller strenge.
     *
     * @return list<string>
     */
    public static function split(string $text, string $sep): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $len = strlen($text);

        $stops = "\\\"'()[]".$sep;

        for ($i = 0; $i < $len; $i++) {
            $i += strcspn($text, $stops, $i);

            if ($i >= $len) {
                break;
            }

            $c = $text[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                $i = self::stringEnd($text, $i);
            } elseif ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            } elseif ($c === $sep && $depth === 0) {
                $parts[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($text, $start);

        return $parts;
    }

    /**
     * Klasserne en selector kræver, uden escapes: `.md\:flex:hover` → md:flex.
     * Klasser i `:not(…)` kræves netop IKKE, og `[href$=".pdf"]` er ingen
     * klasse; begge springes over.
     *
     * @return list<string>
     */
    public static function classes(string $selector): array
    {
        $classes = [];
        $len = strlen($selector);

        for ($i = 0; $i < $len; $i++) {
            $c = $selector[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                $i = self::stringEnd($selector, $i);
            } elseif ($c === '[') {
                $i = self::closing($selector, $i, '[', ']');
            } elseif ($c === ':' && strncasecmp(substr($selector, $i, 5), ':not(', 5) === 0) {
                $i = self::closing($selector, $i + 4, '(', ')');
            } elseif ($c === '.') {
                [$name, $i] = self::ident($selector, $i + 1);

                if ($name !== '') {
                    $classes[] = $name;
                }
            }
        }

        return $classes;
    }

    /**
     * Sammenligningsnøgle for en regel: samme nøgle betyder samme regel, uanset
     * om den er minificeret af Vite eller af Style Push. Kun mellemrum der intet
     * betyder fjernes; `& :is(p)` og `&:is(p)` forbliver forskellige.
     */
    public static function key(string $prelude, string $body): string
    {
        return self::preludeKey($prelude).'{'.self::bodyKey($body).'}';
    }

    public static function preludeKey(string $prelude): string
    {
        if (str_starts_with($prelude, '@')) {
            return preg_replace('/\s+/', '', $prelude);
        }

        $prelude = preg_replace('/\s+/', ' ', trim($prelude));

        return preg_replace('/\s*([>+~,])\s*/', '$1', $prelude);
    }

    private static function bodyKey(string $body): string
    {
        $body = preg_replace('/\s+/', ' ', trim($body));
        $body = preg_replace('/\s*([{};])\s*/', '$1', $body);

        // Uden indlejrede regler er `prop: value` og `prop:value` det samme.
        if (! str_contains($body, '{')) {
            $body = preg_replace('/(^|;)(--?[\w-]+):\s*/', '$1$2:', $body);
        }

        return preg_replace('/;+(?=}|$)/', '', $body);
    }

    /** Indekset for strengens afsluttende anførselstegn. */
    private static function stringEnd(string $text, int $i): int
    {
        $quote = $text[$i];
        $len = strlen($text);

        for ($i++; $i < $len; $i++) {
            $i += strcspn($text, '\\'.$quote, $i);

            if ($i >= $len) {
                break;
            }

            if ($text[$i] === '\\') {
                $i++;
            } elseif ($text[$i] === $quote) {
                return $i;
            }
        }

        return $len - 1;
    }

    /** Indekset for den `$close` der lukker `$open` ved `$i`. */
    private static function closing(string $text, int $i, string $open, string $close): int
    {
        $depth = 0;
        $len = strlen($text);

        for (; $i < $len; $i++) {
            $c = $text[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '"' || $c === "'") {
                $i = self::stringEnd($text, $i);
            } elseif ($c === $open) {
                $depth++;
            } elseif ($c === $close && --$depth === 0) {
                return $i;
            }
        }

        return $len - 1;
    }

    /**
     * Et CSS-navn fra `$i`, uden escapes, og indekset for dets sidste tegn.
     *
     * @return array{string, int}
     */
    private static function ident(string $text, int $i): array
    {
        $name = '';
        $len = strlen($text);

        while ($i < $len) {
            $c = $text[$i];

            if ($c === '\\') {
                if (preg_match('/\G\\\\([0-9a-fA-F]{1,6})\s?/', $text, $hex, 0, $i)) {
                    $name .= mb_chr(hexdec($hex[1]));
                    $i += strlen($hex[0]);
                } else {
                    $name .= $text[$i + 1] ?? '';
                    $i += 2;
                }
            } elseif (ctype_alnum($c) || $c === '-' || $c === '_' || ord($c) >= 0x80) {
                $name .= $c;
                $i++;
            } else {
                break;
            }
        }

        return [$name, $i - 1];
    }
}
