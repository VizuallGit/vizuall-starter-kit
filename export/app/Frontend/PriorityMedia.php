<?php

namespace App\Frontend;

/**
 * Billeder og videoer markeret med `priority="true"` hentes først.
 *
 * Til det ene billede eller den ene video øverst på en side, som skal frem så
 * hurtigt som muligt (det PageSpeed måler som LCP). Uden hjælp kommer det
 * sent: med blur venter det rigtige billede i data-src på blurred-img.js,
 * `loading="lazy"` stiller det i kø, og en videos poster findes først, når
 * videoen er lagt ud. Uden markering sker der intet.
 *
 * Markeringen står på `<img>` eller `<video>`: skrevet i HTML'en, eller af
 * image- og picture-komponenten når de kaldes med priority="true". Den
 * fjernes fra den side der sendes.
 *
 *  - Et billede får fetchpriority="high", loading="eager" og det rigtige
 *    billede i src/srcset med det samme, uden blur — som komponenten med
 *    blur_load="false" — og et `<link rel="preload">` i `<head>` med samme
 *    srcset, så browseren henter den samme fil. I en `<picture>` med `<source media="(min-width:
 *    …)">` får hver kilde sit link med en media der kun passer, når
 *    `<picture>` selv ville vælge den, så der stadig kun hentes én fil. Kan
 *    kilderne ikke regnes ud (type, anden media), preloades de ikke;
 *    fetchpriority gælder stadig den kilde browseren vælger.
 *  - En video får sin poster preloadet. Selve videofilen aldrig.
 */
final class PriorityMedia
{
    public static function apply(string $html): string
    {
        $head = stripos($html, '</head>');

        if ($head === false) {
            return $html;
        }

        $live = Html::live($html);
        preg_match_all('#<(img|video)\b[^>]*>#i', $live, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $head);

        $edits = [];
        $preloads = [];

        foreach ($tags as $match) {
            $offset = $match[0][1];
            $tag = substr($html, $offset, strlen($match[0][0]));

            if (! self::marked($tag)) {
                continue;
            }

            $tag = Html::set($tag, 'priority', null);

            if (strtolower($match[1][0]) === 'video') {
                $poster = Html::attributes($tag)['poster'] ?? '';

                if ($poster !== '') {
                    $preloads[] = self::link($poster, null, null, null);
                }

                $edits[] = [$offset, strlen($match[0][0]), $tag];

                continue;
            }

            $tag = self::prioritise($tag);
            $edits[] = [$offset, strlen($match[0][0]), $tag];
            array_push($edits, ...self::withoutBlur($live, $offset, strlen($match[0][0])));
            $sources = [];

            foreach (self::pictureSources($live, $offset) as [$at, $length]) {
                $source = substr($html, $at, $length);
                $attrs = Html::attributes($source);

                if (isset($attrs['data-srcset'])) {
                    $source = self::unwait($source, 'srcset', $attrs);
                    $edits[] = [$at, $length, $source];
                }

                $sources[] = Html::attributes($source);
            }

            array_push($preloads, ...self::imagePreloads(Html::attributes($tag), $sources));
        }

        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($edits as [$offset, $length, $replacement]) {
            $html = substr_replace($html, $replacement, $offset, $length);
        }

        return self::preload($html, $preloads);
    }

    private static function marked(string $tag): bool
    {
        $value = Html::attributes($tag)['priority'] ?? null;

        return $value !== null && in_array(strtolower(Html::decode($value)), ['', 'true', 'high'], true);
    }

    /** Rigtigt src/srcset (fra data-src når blur venter), loading="eager", fetchpriority="high". */
    private static function prioritise(string $tag): string
    {
        $attrs = Html::attributes($tag);

        foreach (['src', 'srcset'] as $name) {
            if (isset($attrs["data-{$name}"])) {
                $tag = self::unwait($tag, $name, $attrs);
            }
        }

        return Html::set(Html::set($tag, 'loading', 'eager'), 'fetchpriority', 'high');
    }

    /**
     * `data-{$name}` bliver til `$name`: på pladsholderens plads når den står
     * der (src er den tomme SVG), ellers omdøbt hvor den står.
     *
     * @param  array<string, string>  $attrs
     */
    private static function unwait(string $tag, string $name, array $attrs): string
    {
        if (isset($attrs[$name])) {
            return Html::set(Html::set($tag, "data-{$name}", null), $name, $attrs["data-{$name}"]);
        }

        return preg_replace('/(\s)data-'.$name.'(?=\s*=)/i', '$1'.$name, $tag, 1);
    }

    /**
     * Udklip der fjerner den `<div class="blurred-img">` komponenterne lægger
     * om billedet (eller om dets `<picture>`), så det står som med
     * blur_load="false". Med blur er billedet usynligt, indtil blurred-img.js
     * har kørt — på det billede der skal frem først, er det kun ventetid. Kun
     * når indpakningen rummer billedet og intet andet; ellers bliver den.
     *
     * @return list<array{int, int, string}>
     */
    private static function withoutBlur(string $live, int $img, int $length): array
    {
        [$start, $end] = [$img, $img + $length];
        $before = substr($live, 0, $img);
        $picture = strripos($before, '<picture');

        if ($picture !== false && stripos($before, '</picture', $picture) === false) {
            $close = stripos($live, '</picture', $end);
            $closeEnd = $close === false ? false : strpos($live, '>', $close);

            if ($closeEnd === false) {
                return [];
            }

            [$start, $end] = [$picture, $closeEnd + 1];
        }

        if (! preg_match('#<div\b[^>]*>\s*$#i', substr($live, 0, $start), $open, PREG_OFFSET_CAPTURE)
            || ! preg_match('#^\s*</div\s*>#i', substr($live, $end, 64), $close)) {
            return [];
        }

        $classes = preg_split('/\s+/', Html::decode(Html::attributes($open[0][0])['class'] ?? ''));

        if (! in_array('blurred-img', $classes, true)) {
            return [];
        }

        $closeTag = ltrim($close[0]);

        return [
            [$open[0][1], strpos($open[0][0], '>') + 1, ''],
            [$end + strlen($close[0]) - strlen($closeTag), strlen($closeTag), ''],
        ];
    }

    /**
     * `<source>`-tags i den `<picture>` billedet ved `$img` står i, som
     * [offset, længde]. Tom når billedet ikke står i en `<picture>`.
     *
     * @return list<array{int, int}>
     */
    private static function pictureSources(string $live, int $img): array
    {
        $before = substr($live, 0, $img);
        $open = strripos($before, '<picture');

        if ($open === false || stripos($before, '</picture', $open) !== false) {
            return [];
        }

        preg_match_all('#<source\b[^>]*>#i', substr($before, $open), $sources, PREG_OFFSET_CAPTURE);

        return array_map(fn ($s) => [$open + $s[1], strlen($s[0])], $sources[0]);
    }

    /**
     * Preload-links for billedet. Alene: ét. I en `<picture>`: ét pr. kilde
     * <picture> kan vælge, med en media der passer præcis når den vælges, og
     * ét for `<img>` under den mindste. `<picture>` tager den første kilde
     * hvis media passer; en kilde efter en mindre min-width vælges aldrig.
     *
     * @param  array<string, string>  $img
     * @param  list<array<string, string>>  $sources
     * @return list<string>
     */
    private static function imagePreloads(array $img, array $sources): array
    {
        $links = [];
        $below = null;

        foreach ($sources as $source) {
            $media = trim(Html::decode($source['media'] ?? ''));

            if (isset($source['type']) || ($source['srcset'] ?? '') === ''
                || ! preg_match('/^\(\s*min-width\s*:\s*([\d.]+)(px|rem|em)\s*\)$/i', $media, $min)) {
                return [];
            }

            [$value, $unit] = [(float) $min[1], strtolower($min[2])];
            $width = $min[1].$unit;

            // Blandede enheder kan ikke sammenlignes her.
            if ($below !== null && $below['unit'] !== $unit) {
                return [];
            }

            if ($below !== null && $below['value'] <= $value) {
                continue;
            }

            $query = "(min-width: {$width})".($below ? " and (width < {$below['width']})" : '');
            $links[] = self::link(self::firstUrl($source['srcset']), $source['srcset'], $source['sizes'] ?? null, $query);
            $below = ['value' => $value, 'unit' => $unit, 'width' => $width];
        }

        $src = $img['src'] ?? '';

        if ($src === '' || str_starts_with($src, 'data:')) {
            return $sources ? [] : $links;
        }

        $links[] = self::link($src, $img['srcset'] ?? null, $img['sizes'] ?? null, $below ? "(width < {$below['width']})" : null);

        return $links;
    }

    private static function link(string $href, ?string $srcset, ?string $sizes, ?string $media): string
    {
        $link = '<link rel="preload" as="image" href="'.$href.'"';
        $link .= ($srcset ?? '') !== '' ? ' imagesrcset="'.$srcset.'"' : '';
        $link .= ($sizes ?? '') !== '' ? ' imagesizes="'.$sizes.'"' : '';
        $link .= $media !== null ? ' media="'.$media.'"' : '';

        return $link.' fetchpriority="high">';
    }

    /** Den første URL i en srcset, til href for browsere uden imagesrcset. */
    private static function firstUrl(string $srcset): string
    {
        return preg_split('/\s+/', trim(CssBlocks::split($srcset, ',')[0]))[0];
    }

    /** Linkene i `<head>`, før sidens første link, style eller script. */
    private static function preload(string $html, array $links): string
    {
        if (! $links) {
            return $html;
        }

        $head = stripos($html, '</head>');
        $live = Html::live(substr($html, 0, $head));
        $at = preg_match('#<(?:link|style|script)\b#i', $live, $first, PREG_OFFSET_CAPTURE) ? $first[0][1] : $head;

        return substr_replace($html, implode('', array_unique($links)), $at, 0);
    }
}
