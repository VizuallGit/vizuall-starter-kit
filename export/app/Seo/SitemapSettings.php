<?php

namespace App\Seo;

use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Symfony\Component\Yaml\Yaml;

/**
 * Which collections the sitemap speaks for.
 *
 * Not every collection with a route is public in spirit. The old `sections`
 * gallery has an address so a developer can look at it, and a client site has
 * no business telling Google about it. That is a judgement nobody but the site
 * owner can make, so it is a checkbox rather than a list in the code.
 *
 * Only the exclusions are stored. A new collection is therefore in by default,
 * which is the safe way round: a page nobody told Google about is a page
 * nobody finds.
 *
 * The same file holds the whole site's answer to «må søgemaskiner være her».
 */
class SitemapSettings
{
    /** The three answers to «må søgemaskiner finde sitet». */
    public const SEARCH_AUTO = 'auto';

    public const SEARCH_ON = 'on';

    public const SEARCH_OFF = 'off';

    /** @var list<string>|null */
    protected ?array $excluded = null;

    protected ?array $file = null;

    /** @return list<string> */
    public function excluded(): array
    {
        if ($this->excluded !== null) {
            return $this->excluded;
        }

        $rows = $this->file()['exclude_collections'] ?? [];

        return $this->excluded = is_array($rows)
            ? array_values(array_filter(array_map('strval', $rows)))
            : [];
    }

    /**
     * Må søgemaskiner finde sitet? `auto`, `on` eller `off`.
     *
     * `auto` er standarden og følger miljøet: produktion ja, alt andet nej.
     * Det er den rigtige vej rundt — en kopi af sitet på et testdomæne skal
     * aldrig i Google, og ingen skal huske at slå det til.
     */
    public function searchEngines(): string
    {
        $value = (string) ($this->file()['search_engines'] ?? static::SEARCH_AUTO);

        return in_array($value, [static::SEARCH_AUTO, static::SEARCH_ON, static::SEARCH_OFF], true)
            ? $value
            : static::SEARCH_AUTO;
    }

    /**
     * Afgørelsen, efter `auto` er slået op i miljøet.
     *
     * `$environment` er cascadens miljø, når der er et. Det statiske udtræk
     * tvinger cascaden til `production` (Static Publish' Exporter), fordi
     * kopien ER produktionen uanset hvad serveren der byggede den kører som.
     * Uden den overstyring ville en eksport fra et site i `local` lægge
     * noindex på hver eneste udgivne side.
     *
     * `off` overstyres ikke: har nogen udtrykkeligt sagt «aldrig», gælder det
     * også i kopien.
     */
    public function siteIndexable(?string $environment = null): bool
    {
        return match ($this->searchEngines()) {
            static::SEARCH_ON => true,
            static::SEARCH_OFF => false,
            default => ($environment ?? app()->environment()) === 'production',
        };
    }

    public function saveSearchEngines(string $value): void
    {
        $this->write(['search_engines' => in_array($value, [static::SEARCH_AUTO, static::SEARCH_ON, static::SEARCH_OFF], true)
            ? $value
            : static::SEARCH_AUTO]);
    }

    /** @return array<string, mixed> */
    protected function file(): array
    {
        if ($this->file !== null) {
            return $this->file;
        }

        $path = $this->path();

        if (! File::exists($path)) {
            return $this->file = [];
        }

        $parsed = Yaml::parseFile($path);

        return $this->file = is_array($parsed) ? $parsed : [];
    }

    /**
     * Skriv nogle nøgler og lad resten stå. De to indstillinger deler fil, og
     * den ene må aldrig slette den anden.
     *
     * @param  array<string, mixed>  $values
     */
    protected function write(array $values): void
    {
        $data = array_merge($this->file(), $values);

        $path = $this->path();
        $dir = dirname($path);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, Yaml::dump($data, 3, 2));

        $this->file = $data;
        $this->excluded = null;
    }

    public function excludes(string $collection): bool
    {
        return in_array($collection, $this->excluded(), true);
    }

    /** @param  array<mixed>  $handles */
    public function save(array $handles): void
    {
        $clean = array_values(array_intersect(
            array_keys($this->collections()),
            array_map('strval', $handles)
        ));

        $this->write(['exclude_collections' => $clean]);

        $this->excluded = $clean;
    }

    /**
     * Every collection that could be in the sitemap — the ones with a route.
     * A collection without one has no public address to list.
     *
     * @return array<string, string> handle → title
     */
    public function collections(): array
    {
        $out = [];

        foreach (Collection::all() as $collection) {
            if ($collection->routes()->filter()->isEmpty()) {
                continue;
            }

            $out[$collection->handle()] = $collection->title() ?: $collection->handle();
        }

        ksort($out);

        return $out;
    }

    public function path(): string
    {
        return base_path('content/sitemap.yaml');
    }
}
