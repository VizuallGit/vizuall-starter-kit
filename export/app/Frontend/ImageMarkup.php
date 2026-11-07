<?php

namespace App\Frontend;

use Statamic\Events\ResponseCreated;
use Statamic\Facades\Asset;
use Statamic\Statamic;
use Symfony\Component\HttpFoundation\Response;

/**
 * Billeder skrevet som almindelige elementer, gjort færdige på serveren.
 *
 * Build (Visual Editor) skriver et `<img>` eller en `<picture>` med korte valg
 * i stedet for at kalde components/image:
 *
 *   <img src="{{ media }}" width="1200" height="675" format="webp" quality="70" blur alt="…">
 *
 *   <picture>
 *       <source media="(width < 48em)" srcset="{{ media }}" width="768" height="768">
 *       <img src="{{ media }}" width="1200" height="675" format="webp" quality="70" blur alt="…">
 *   </picture>
 *
 * Her bliver de til det components/image skriver: Glide-adresser i 1x og 2x,
 * i formatet og kvaliteten, med de endelige width/height. Er både bredde og
 * højde sat, beskæres billedet om fokuspunktet (fit="crop_focal"); er kun den
 * ene sat, beholder det sit eget format, og den anden regnes ud. `blur` giver
 * den slørede pladsholder fra blurred-img.js/-css: en lille udgave i src, det
 * fulde billede i data-src/data-srcset, indtil det er 100 px fra skærmen.
 *
 * - Kun et `<img>` med mindst ét af valgene (format, quality, blur) røres; alle
 *   andre billeder står, som de er. Valgene fjernes fra den side der sendes.
 * - En `<source>` i en `<picture>` får sin egen bredde/højde og billedets
 *   format og kvalitet. Står der ingen adresse i den, bruges billedets.
 * - `format="original"` beholder filens format. AVIF kun når serverens
 *   billeddriver kan lave det (`formats()`), ellers WebP.
 * - I Live Preview er blur fra (som komponenterne), resten er det samme, så
 *   beskæring og størrelse ses mens der redigeres.
 * - `priority="true"` står tilbage til PriorityMedia, og blur springes over på
 *   det billede: det skal frem først, ikke vente.
 *
 * Kører på Statamics ResponseCreated, før scripts-lytteren (som henter
 * blurred-img.js når klassen bruges) og før CleanPublicPage/PriorityMedia.
 */
final class ImageMarkup
{
    /** Valgene der gør et `<img>` til et, der gøres færdigt her. */
    private const OPTIONS = ['format', 'quality', 'blur'];

    private const QUALITY = 70;

    private const BLUR_WIDTH = 40;

    private static ?array $formats = null;

    public function created(ResponseCreated $event): void
    {
        $response = $event->response;

        if (! $response instanceof Response || ! is_string($response->getContent()) || ! $this->isHtml($response)) {
            return;
        }

        $html = $response->getContent();
        $done = self::apply($html, (bool) request()->isLivePreview());

        if ($done !== $html) {
            $response->setContent($done);
            $response->headers->remove('Content-Length');
        }
    }

    /**
     * @param  callable(array<string, mixed>): ?string|null  $glide  parametre → Glide-adresse (tests)
     * @param  callable(string): ?array{int, int}|null  $size  adresse → billedets egne mål (tests)
     */
    public static function apply(string $html, bool $livePreview = false, ?callable $glide = null, ?callable $size = null): string
    {
        if (stripos($html, '<img') === false) {
            return $html;
        }

        $glide ??= self::glide(...);
        $size ??= self::assetSize(...);
        $masked = self::masked($html);
        $edits = [];
        $pictures = [];

        preg_match_all('#<picture\b[^>]*>.*?</picture\s*>#is', $masked, $blocks, PREG_OFFSET_CAPTURE);

        foreach ($blocks[0] as [$block, $at]) {
            $pictures[] = [$at, $at + strlen($block)];
            array_push($edits, ...self::picture($html, $block, $at, $livePreview, $glide, $size));
        }

        preg_match_all('#<img\b[^>]*>#i', $masked, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as [$tag, $at]) {
            foreach ($pictures as [$from, $to]) {
                if ($at > $from && $at < $to) {
                    continue 2;
                }
            }

            $done = self::image(substr($html, $at, strlen($tag)), $livePreview, $glide, $size);

            if ($done !== null) {
                $edits[] = [$at, strlen($tag), $done['tag']];
            }
        }

        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);

        foreach ($edits as [$at, $length, $replacement]) {
            $html = substr_replace($html, $replacement, $at, $length);
        }

        return $html;
    }

    /**
     * De formater serveren kan lave, til Build's Format-valg (`sveImageOptions`).
     *
     * @return list<string>
     */
    public static function formats(): array
    {
        if (self::$formats !== null) {
            return self::$formats;
        }

        $driver = config('statamic.assets.image_manipulation.driver', 'gd');
        $imagick = $driver === 'imagick' && class_exists(\Imagick::class);
        $can = fn (string $format, string $gd) => $imagick
            ? \Imagick::queryFormats(strtoupper($format)) !== []
            : $driver === 'gd' && function_exists($gd);

        return self::$formats = array_values(array_filter([
            $can('webp', 'imagewebp') ? 'webp' : null,
            $can('avif', 'imageavif') ? 'avif' : null,
            'jpg',
            'png',
        ]));
    }

    /** Hvad Build skal vide for at vise billedvalgene: formaterne og standardkvaliteten. */
    public static function forScript(): array
    {
        return ['formats' => self::formats(), 'quality' => self::QUALITY];
    }

    /**
     * Et `<img>` gjort færdigt, eller null når det ikke har valgene.
     *
     * @return array{tag: string, look: array{format: ?string, quality: int}, wait: bool, src?: string}|null
     */
    private static function image(string $tag, bool $livePreview, callable $glide, callable $size): ?array
    {
        [$name, $attrs] = self::parse($tag);

        if (! array_intersect(self::OPTIONS, array_keys($attrs))) {
            return null;
        }

        $value = fn (string $key) => Html::decode((string) ($attrs[$key]['value'] ?? ''));
        $look = self::look($value('format'), $value('quality'));
        // Som PriorityMedia læser markeringen: bar, "", "true" eller "high".
        $priority = isset($attrs['priority']) && in_array(strtolower($value('priority')), ['', 'true', 'high'], true);
        $blur = ! $livePreview && ! $priority && isset($attrs['blur']) && ! in_array(strtolower($value('blur')), ['false', '0', 'off'], true);
        $wait = $blur && strtolower($value('loading')) !== 'eager';

        foreach (self::OPTIONS as $option) {
            unset($attrs[$option]);
        }

        $src = $value('src');
        $made = $src === '' ? null : self::made($src, self::int($value('width')), self::int($value('height')), $look, $glide, $size);

        if ($made === null) {
            return ['tag' => self::build($name, $attrs), 'look' => $look, 'wait' => false];
        }

        [$w, $h, $one, $two, $crop] = $made;
        $srcset = self::esc($one).' 1x, '.self::esc($two).' 2x';

        self::put($attrs, 'width', $w ? (string) $w : null);
        self::put($attrs, 'height', $h ? (string) $h : null);

        if ($blur && $w && $h) {
            $classes = trim((string) ($attrs['class']['value'] ?? ''));
            self::put($attrs, 'class', trim($classes.' blurred-img'));
            self::put($attrs, 'style', "--blurred-img-ratio: {$w} / {$h};".(($attrs['style']['value'] ?? '') !== '' ? ' '.$attrs['style']['value'] : ''));
        } else {
            $wait = false;
        }

        if ($wait) {
            $tiny = $glide(array_filter([
                'src' => $src,
                'width' => self::BLUR_WIDTH,
                'height' => (int) max(1, round($h * self::BLUR_WIDTH / $w)),
                'fit' => 'crop_focal',
            ]));

            self::put($attrs, 'loading', 'eager');
            self::put($attrs, 'src', self::esc($tiny ?? $one));
            self::put($attrs, 'srcset', null);
            self::put($attrs, 'data-srcset', $srcset);
            self::put($attrs, 'data-src', self::esc($one));
        } else {
            self::put($attrs, 'loading', $attrs['loading']['value'] ?? 'lazy');
            self::put($attrs, 'srcset', $srcset);
            self::put($attrs, 'src', self::esc($one));
        }

        return ['tag' => self::build($name, $attrs), 'look' => $look, 'wait' => $wait, 'src' => $src];
    }

    /**
     * En `<picture>`: billedet, og hver `<source>` med billedets format og
     * kvalitet i sin egen størrelse.
     *
     * @return list<array{int, int, string}>
     */
    private static function picture(string $html, string $block, int $at, bool $livePreview, callable $glide, callable $size): array
    {
        if (! preg_match('#<img\b[^>]*>#i', $block, $img, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $imgAt = $at + $img[0][1];
        $done = self::image(substr($html, $imgAt, strlen($img[0][0])), $livePreview, $glide, $size);

        if ($done === null) {
            return [];
        }

        $edits = [[$imgAt, strlen($img[0][0]), $done['tag']]];
        preg_match_all('#<source\b[^>]*>#i', $block, $sources, PREG_OFFSET_CAPTURE);

        foreach ($sources[0] as [$source, $offset]) {
            $sourceAt = $at + $offset;
            [$name, $attrs] = self::parse(substr($html, $sourceAt, strlen($source)));
            $own = trim(Html::decode((string) ($attrs['srcset']['value'] ?? '')));

            // En færdig srcset (flere adresser eller 1x/400w) er skrevet i hånden: den står.
            if (preg_match('/[\s,]/', $own)) {
                continue;
            }

            $src = $own !== '' ? $own : ($done['src'] ?? '');
            $made = $src === '' ? null : self::made(
                $src,
                self::int(Html::decode((string) ($attrs['width']['value'] ?? ''))),
                self::int(Html::decode((string) ($attrs['height']['value'] ?? ''))),
                $done['look'],
                $glide,
                $size,
            );

            if ($made === null) {
                continue;
            }

            [$w, $h, $one, $two] = $made;
            self::put($attrs, 'width', $w ? (string) $w : null);
            self::put($attrs, 'height', $h ? (string) $h : null);
            self::put($attrs, $done['wait'] ? 'srcset' : 'data-srcset', null);
            self::put($attrs, $done['wait'] ? 'data-srcset' : 'srcset', self::esc($one).' 1x, '.self::esc($two).' 2x');
            $edits[] = [$sourceAt, strlen($source), self::build($name, $attrs)];
        }

        return $edits;
    }

    /**
     * Billedets endelige mål og dets to adresser, eller null når Glide ikke
     * kan lave det (en SVG, en adresse uden for sitet …).
     *
     * @return array{?int, ?int, string, string, bool}|null
     */
    private static function made(string $src, ?int $width, ?int $height, array $look, callable $glide, callable $size): ?array
    {
        if (preg_match('/\.svg(\?|#|$)/i', $src)) {
            return null;
        }

        $crop = $width !== null && $height !== null;
        $params = array_filter([
            'src' => $src,
            'width' => $width,
            'height' => $height,
            'fit' => $crop ? 'crop_focal' : null,
            'quality' => $look['quality'],
            'format' => $look['format'],
        ], fn ($v) => $v !== null);

        $one = $glide($params);
        $two = $glide($params + ['dpr' => 2]);

        if (! $one || ! $two) {
            return null;
        }

        if (! $crop) {
            $own = $size($src);

            if ($own && $own[0] > 0 && $own[1] > 0) {
                [$width, $height] = match (true) {
                    $width !== null => [$width, (int) round($width * $own[1] / $own[0])],
                    $height !== null => [(int) round($height * $own[0] / $own[1]), $height],
                    default => $own,
                };
            }
        }

        return [$width, $height, $one, $two, $crop];
    }

    /** @return array{format: ?string, quality: int} */
    private static function look(string $format, string $quality): array
    {
        $format = strtolower(trim($format)) ?: 'webp';
        $format = $format === 'jpeg' ? 'jpg' : $format;

        if ($format === 'avif' && ! in_array('avif', self::formats(), true)) {
            $format = 'webp';
        }

        $quality = (int) $quality;

        return [
            'format' => in_array($format, ['webp', 'avif', 'jpg', 'png'], true) ? $format : null,
            'quality' => $quality >= 1 && $quality <= 100 ? $quality : self::QUALITY,
        ];
    }

    private static function glide(array $params): ?string
    {
        $url = Statamic::tag('glide')->params($params)->fetch();

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** @return array{int, int}|null */
    private static function assetSize(string $src): ?array
    {
        $url = str_starts_with($src, (string) config('app.url')) ? substr($src, strlen((string) config('app.url'))) : $src;
        $asset = Asset::find(str_contains($url, '::') ? $url : '/'.ltrim($url, '/'));

        return $asset && $asset->width() && $asset->height() ? [(int) $asset->width(), (int) $asset->height()] : null;
    }

    private static function int(string $value): ?int
    {
        return ctype_digit(trim($value)) && (int) $value > 0 ? (int) $value : null;
    }

    private static function esc(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Et tag som navn og attributter i rækkefølge. Værdier i anførselstegn
     * læses hele, så et ord i en alt-tekst aldrig er et attribut.
     *
     * @return array{string, array<string, array{name: string, value: ?string, quote: string}>}
     */
    private static function parse(string $tag): array
    {
        preg_match('#^<([\w-]+)#', $tag, $name);
        $inside = preg_replace('#^<[\w-]+|/?>$#', '', $tag) ?? '';
        preg_match_all('/([^\s"\'<>\/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+))?/', $inside, $pairs, PREG_SET_ORDER);
        $attrs = [];

        foreach ($pairs as $pair) {
            $key = strtolower($pair[1]);

            if (isset($attrs[$key])) {
                continue;
            }

            $raw = $pair[2] ?? null;
            $quote = $raw !== null && ($raw[0] === '"' || $raw[0] === "'") ? $raw[0] : '"';
            $attrs[$key] = [
                'name' => $pair[1],
                'value' => $raw === null ? null : ($quote === $raw[0] && strlen($raw) >= 2 && $raw[-1] === $quote ? substr($raw, 1, -1) : $raw),
                'quote' => $quote,
            ];
        }

        return [$name[1] ?? 'img', $attrs];
    }

    /** Sætter (rå HTML-værdi) eller fjerner (null) et attribut; et nyt kommer sidst. */
    private static function put(array &$attrs, string $key, ?string $value): void
    {
        if ($value === null) {
            unset($attrs[$key]);

            return;
        }

        $attrs[$key] = ['name' => $attrs[$key]['name'] ?? $key, 'value' => $value, 'quote' => '"'];
    }

    private static function build(string $name, array $attrs): string
    {
        $out = '<'.$name;

        foreach ($attrs as $attr) {
            if ($attr['value'] === null) {
                $out .= ' '.$attr['name'];

                continue;
            }

            $quote = $attr['quote'] === "'" || str_contains($attr['value'], '"') ? "'" : '"';
            $out .= ' '.$attr['name'].'='.$quote.$attr['value'].$quote;
        }

        return $out.'>';
    }

    /** Kommentarer og scripts blanket ud, samme længde: et `<img>` dér er ikke et billede på siden. */
    private static function masked(string $html): string
    {
        return preg_replace_callback(
            '#<!--(?:.*?-->|.*$)|<script\b.*?</script\s*>#is',
            fn ($m) => str_repeat(' ', strlen($m[0])),
            $html,
        ) ?? $html;
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }
}
