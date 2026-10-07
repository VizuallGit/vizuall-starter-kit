<?php

namespace App\Frontend;

/**
 * Små HTML-greb til frontend-gennemløbet (PageCss, PriorityMedia): nok til at
 * finde og rette tags i den færdige side, ikke en HTML-parser.
 */
final class Html
{
    /**
     * HTML'en med det browseren ikke viser eller bruger blanket ud:
     * kommentarer, `<noscript>`, `<template>` og scripts. Samme længde, så en
     * position i resultatet er den samme i den rigtige HTML.
     */
    public static function live(string $html): string
    {
        return preg_replace_callback(
            '#<!--(?:.*?-->|.*$)|<noscript\b.*?</noscript\s*>|<template\b.*?</template\s*>|<script\b.*?</script\s*>#is',
            fn ($m) => str_repeat(' ', strlen($m[0])),
            $html,
        ) ?? $html;
    }

    /**
     * Et tags attributter, navn (små bogstaver) → værdien som den står i
     * HTML'en (stadig med entities). Et attribut uden værdi er ''.
     *
     * @return array<string, string>
     */
    public static function attributes(string $tag): array
    {
        $tag = preg_replace('#^<[\w-]+|/?>$#', '', $tag) ?? '';
        preg_match_all('/([^\s"\'<>\/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?/', $tag, $pairs, PREG_SET_ORDER);
        $attrs = [];

        foreach ($pairs as $pair) {
            $name = strtolower($pair[1]);
            $attrs[$name] ??= isset($pair[2]) ? self::unquote($pair[2]) : '';
        }

        return $attrs;
    }

    /** Værdien med entities oversat, til at sammenligne med. */
    public static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Tagget med `$name` sat til `$value` (rå HTML-værdi), eller fjernet når
     * `$value` er null. Står attributtet der, rettes det på sin plads.
     */
    public static function set(string $tag, string $name, ?string $value): string
    {
        $pattern = '/\s'.preg_quote($name, '/').'(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?(?=[\s\/>])/i';
        $attr = $value === null ? '' : ' '.$name.'="'.$value.'"';

        if (preg_match($pattern, $tag)) {
            return preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $attr), $tag, 1);
        }

        return $value === null ? $tag : preg_replace('#\s*(/?>)$#', $attr.'$1', $tag, 1);
    }

    private static function unquote(string $value): string
    {
        return strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]
            ? substr($value, 1, -1)
            : $value;
    }
}
