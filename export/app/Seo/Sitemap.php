<?php

namespace App\Seo;

use App\Redirects\RedirectStore;
use App\Routes\RouteStore;
use Illuminate\Support\Carbon;
use Statamic\Facades\Entry;
use Illuminate\Support\Str;
use Statamic\Facades\Site;

/**
 * Every address this site wants found, built fresh on each request.
 *
 * Never a file somebody has to remember to rebuild: a sitemap that is a
 * snapshot is wrong the moment a page is published or deleted, and a wrong
 * sitemap is worse than none — search engines keep fetching addresses that
 * are gone and never hear about the ones that arrived.
 *
 * Two sources: Statamic's own entries, and the custom routes from
 * App\Routes\RouteStore that asked to be listed.
 *
 * Both lists come back from `rows()`: the ones that are in, and the ones that
 * are out with the reason why. The utility screen draws both, so "why is this
 * page not on Google" has an answer that is not guesswork.
 */
class Sitemap
{
    public function __construct(
        protected RouteStore $routes,
        protected RedirectStore $redirects,
        protected SitemapSettings $settings,
    ) {}

    /**
     * @return array{included: list<array{url: string, lastmod: ?string, label: string, source: string}>, excluded: list<array{url: string, label: string, source: string, reason: string}>}
     */
    public function rows(): array
    {
        $included = [];
        $excluded = [];

        foreach ($this->entryRows() as $row) {
            $row['reason'] === null
                ? $included[] = ['url' => $row['url'], 'lastmod' => $row['lastmod'], 'label' => $row['label'], 'source' => $row['source']]
                : $excluded[] = ['url' => $row['url'], 'label' => $row['label'], 'source' => $row['source'], 'reason' => $row['reason']];
        }

        foreach ($this->routeRows() as $row) {
            $row['reason'] === null
                ? $included[] = ['url' => $row['url'], 'lastmod' => $row['lastmod'], 'label' => $row['label'], 'source' => $row['source']]
                : $excluded[] = ['url' => $row['url'], 'label' => $row['label'], 'source' => $row['source'], 'reason' => $row['reason']];
        }

        usort($included, fn ($a, $b) => strcmp($a['url'], $b['url']));
        usort($excluded, fn ($a, $b) => strcmp($a['url'], $b['url']));

        return ['included' => $included, 'excluded' => $excluded];
    }

    /** Just the addresses, for the XML. */
    public function urls(): array
    {
        return $this->rows()['included'];
    }

    /**
     * Statamic's entries, each with the reason it is out — or null if it is in.
     *
     * An entry with no URL is not skipped with a reason; it is not a page at
     * all. Saved sections and compositions live in collections without a route
     * precisely so they never become public, and listing them as "excluded"
     * would make a deliberate choice look like a mistake. Neither are the
     * editor's own pages — the section gallery and anything else drawn with a
     * `skabelon_*` view: one list says what those are, and the static export
     * already leaves them out of the copy.
     */
    protected function entryRows(): array
    {
        $rows = [];
        $site = Site::current()->handle();

        foreach (Entry::query()->where('site', $site)->get() as $entry) {
            $url = $entry->absoluteUrl();

            if (! $url || $this->isEditorPage($entry)) {
                continue;
            }

            $rows[] = [
                'url' => $url,
                'lastmod' => ($date = $entry->lastModified()) ? Carbon::parse($date)->toAtomString() : null,
                'label' => (string) ($entry->get('title') ?: $entry->slug()),
                'source' => $entry->collectionHandle(),
                'reason' => $this->entryReason($entry),
            ];
        }

        return $rows;
    }

    /**
     * A page of the editor rather than of the site.
     *
     * The same `editor_views` list Static Publish excludes from the copy, so
     * the sitemap and the published site never disagree about what a page is.
     * No list configured means nothing is an editor page.
     */
    protected function isEditorPage($entry): bool
    {
        $patterns = (array) config('static-publish.editor_views', []);

        if ($patterns === []) {
            return false;
        }

        return Str::is($patterns, (string) $entry->template())
            || Str::is($patterns, (string) $entry->layout());
    }

    /** Why this entry stays out of the sitemap, in Danish, or null. */
    protected function entryReason($entry): ?string
    {
        if (! $entry->published()) {
            return 'Ikke udgivet';
        }

        if ($entry->get('meta_noindex')) {
            return 'Skjult for søgemaskiner';
        }

        if ($this->settings->excludes($entry->collectionHandle())) {
            return 'Hele samlingen er holdt ude';
        }

        if ($this->redirects->match((string) parse_url($entry->url() ?: '', PHP_URL_PATH))) {
            return 'En omdirigering sender adressen videre';
        }

        return null;
    }

    /** The custom routes, same shape. A route has no content, so it has no lastmod. */
    protected function routeRows(): array
    {
        $rows = [];

        foreach ($this->routes->all() as $row) {
            $rows[] = [
                'url' => url($row['uri']),
                'lastmod' => null,
                'label' => $row['title'] ?: $row['uri'],
                'source' => 'rute',
                'reason' => $this->routeReason($row),
            ];
        }

        return $rows;
    }

    protected function routeReason(array $row): ?string
    {
        if (! $row['enabled']) {
            return 'Ruten er slået fra';
        }

        if ($problem = $this->routes->problem($row)) {
            return $problem;
        }

        if (! ($row['sitemap'] ?? false)) {
            return 'Ruten er ikke sat til at komme med';
        }

        return null;
    }
}
