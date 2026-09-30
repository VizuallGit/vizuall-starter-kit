<?php

namespace App\Dashboard;

use Illuminate\Support\Facades\File;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;

/**
 * Ét billede af sitet, delt af dashboardets widgets.
 *
 * Widgets stiller stort set de samme spørgsmål — hvad er rørt sidst, og hvad
 * mangler. Ligger svarene i hver sin widget, driver de fra hinanden: den ene
 * tæller kladder med, den anden ikke, og så står der to tal på samme skærm der
 * ikke kan passe begge to. Derfor spørges der her.
 *
 * Alt er memoiseret pr. request. Dashboardet bygger alle sine widgets i ét
 * svar, så samme tælling må ikke koste et Stache-opslag pr. kort.
 */
class SiteSnapshot
{
    /** Samlinger der ikke er redaktionelt indhold, men Visual Editors eget maskineri. */
    protected const LIBRARY_COLLECTIONS = ['saved_sections', 'saved_compositions', 'templates', 'sections'];

    /** Loftet på hvad der læses igennem for at finde fejl. Et dashboard må aldrig blive det tunge sted. */
    protected const SCAN_LIMIT = 500;

    protected array $memo = [];

    /**
     * Det der er rørt sidst, på tværs af samlinger.
     *
     * Sorteringen sker her og ikke i forespørgslen: `orderBy('updated_at')` på
     * en entry-query svarer ikke på det spørgsmål — den rækkefølge er ikke
     * tidsstemplet fra filen. Derfor hentes samlingerne og sorteres på
     * `lastModified()`, som er dét redaktøren mener med "sidst".
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 6): array
    {
        return $this->once('recent.'.$limit, function () use ($limit) {
            return collect($this->editableEntries())
                ->sortByDesc(fn ($entry) => $entry->lastModified()?->timestamp ?? 0)
                ->take($limit)
                ->map(fn ($entry) => $this->entryRow($entry))
                ->values()
                ->all();
        });
    }

    /**
     * Ting nogen skal tage sig af. Tom liste er et godt svar, ikke et manglende et.
     *
     * @return array<int, array{key: string, level: string, label: string, hint: string, count: int, url: ?string}>
     */
    public function attention(): array
    {
        return $this->once('attention', function () {
            $items = [];

            if ($open = $this->openComments()) {
                $items[] = [
                    'key' => 'comments',
                    'level' => 'bad',
                    'label' => $open === 1 ? 'Én åben kommentar' : $open.' åbne kommentarer',
                    'hint' => 'Fra Visual Editor',
                    'count' => $open,
                    'url' => null,
                ];
            }

            if ($drafts = $this->entryCount('pages', 'draft')) {
                $items[] = [
                    'key' => 'drafts',
                    'level' => 'ok',
                    'label' => $drafts === 1 ? 'Én side er kladde' : $drafts.' sider er kladder',
                    'hint' => 'Ikke synlige på sitet endnu',
                    'count' => $drafts,
                    'url' => cp_route('collections.show', 'pages'),
                ];
            }

            $meta = $this->pagesMissingMeta();

            if ($meta !== []) {
                $items[] = [
                    'key' => 'meta',
                    'level' => 'ok',
                    'label' => count($meta) === 1 ? 'Én side mangler meta-tekst' : count($meta).' sider mangler meta-tekst',
                    'hint' => $this->names($meta),
                    'count' => count($meta),
                    'url' => $meta[0]['edit_url'] ?? null,
                ];
            }

            if ($alt = $this->imagesMissingAlt()) {
                $items[] = [
                    'key' => 'alt',
                    'level' => 'ok',
                    'label' => $alt === 1 ? 'Ét billede mangler alt-tekst' : $alt.' billeder mangler alt-tekst',
                    'hint' => 'Skærmlæsere læser filnavnet i stedet',
                    'count' => $alt,
                    'url' => cp_route('assets.browse.index'),
                ];
            }

            return $items;
        });
    }

    /** Entries redaktøren kan åbne — indhold først, biblioteket bagefter. */
    protected function editableEntries(): array
    {
        return $this->once('editableEntries', function () {
            $handles = Collection::all()->map->handle()->all();

            return collect($handles)
                ->flatMap(fn (string $handle) => Entry::query()
                    ->where('collection', $handle)
                    ->limit(static::SCAN_LIMIT)
                    ->get()
                    ->all())
                ->all();
        });
    }

    protected function entryRow($entry): array
    {
        $collection = $entry->collection();
        $modified = $entry->lastModified();

        return [
            'id' => $entry->id(),
            'title' => (string) ($entry->get('title') ?: $entry->slug() ?: $entry->id()),
            'collection' => $collection?->handle(),
            'collection_label' => (string) ($collection?->title() ?? ''),
            'library' => in_array($collection?->handle(), static::LIBRARY_COLLECTIONS, true),
            'status' => $entry->status(),
            'edit_url' => $entry->editUrl(),
            'preview_url' => $this->previewUrl($entry),
            'modified_at' => $modified?->toIso8601String(),
        ];
    }

    /**
     * Live Preview-adressen er redigeringsadressen med en parameter på — samme
     * form som knappen på det offentlige site bruger. Kun sider med en rigtig
     * adresse får den; et gemt sektions-udklip har ingen side at vise.
     */
    protected function previewUrl($entry): ?string
    {
        if (! $entry->url()) {
            return null;
        }

        $edit = (string) $entry->editUrl();

        return $edit.(str_contains($edit, '?') ? '&' : '?').'live-preview=1';
    }

    protected function entryCount(string $collection, ?string $status = null): int
    {
        return $this->once('count.'.$collection.'.'.($status ?? 'all'), function () use ($collection, $status) {
            $query = Entry::query()->where('collection', $collection);

            if ($status) {
                $query->whereStatus($status);
            }

            return (int) $query->count();
        });
    }

    /** Udgivne sider uden meta-titel eller -beskrivelse. Felterne læses direkte — ingen sidegennemgang. */
    protected function pagesMissingMeta(): array
    {
        return $this->once('pagesMissingMeta', function () {
            return collect($this->editableEntries())
                ->filter(fn ($entry) => $entry->collectionHandle() === 'pages' && $entry->status() === 'published')
                ->filter(fn ($entry) => trim((string) $entry->get('meta_title')) === ''
                    || trim((string) $entry->get('meta_description')) === '')
                ->map(fn ($entry) => [
                    'title' => (string) ($entry->get('title') ?: $entry->slug()),
                    'edit_url' => $entry->editUrl(),
                ])
                ->values()
                ->all();
        });
    }

    protected function imagesMissingAlt(): int
    {
        return $this->once('imagesMissingAlt', function () {
            $missing = 0;
            $seen = 0;

            foreach (AssetContainer::all() as $container) {
                foreach ($container->queryAssets()->limit(static::SCAN_LIMIT)->get() as $asset) {
                    if (++$seen > static::SCAN_LIMIT) {
                        return $missing;
                    }

                    if (! $asset->isImage()) {
                        continue;
                    }

                    if (trim((string) $asset->get('alt')) === '') {
                        $missing++;
                    }
                }
            }

            return $missing;
        });
    }

    /**
     * Uafklarede kommentartråde, talt på filerne alene.
     *
     * Kommentar-widgeten læser de samme filer, men den skal bruge afsender,
     * sektionsnavn og hele tråden. Her skal der kun bruges et tal, og et tal
     * må ikke koste et opslag af hver eneste sektion det hører til.
     */
    protected function openComments(): int
    {
        return $this->once('openComments', function () {
            $dir = storage_path('statamic-visual-editor/comments');

            if (! File::isDirectory($dir)) {
                return 0;
            }

            $open = 0;

            foreach (File::files($dir) as $file) {
                if ($file->getExtension() !== 'yaml') {
                    continue;
                }

                try {
                    $parsed = YAML::file($file->getPathname())->parse() ?: [];
                } catch (\Throwable) {
                    continue;
                }

                foreach ($parsed['comments'] ?? [] as $thread) {
                    if (is_array($thread) && ! ($thread['resolved'] ?? false)) {
                        $open++;
                    }
                }
            }

            return $open;
        });
    }

    protected function names(array $rows, int $max = 3): string
    {
        $titles = collect($rows)->pluck('title')->filter()->values();

        if ($titles->count() > $max) {
            return $titles->take($max)->implode(', ').' m.fl.';
        }

        return $titles->implode(', ');
    }

    protected function once(string $key, callable $resolve)
    {
        return $this->memo[$key] ??= $resolve();
    }
}
