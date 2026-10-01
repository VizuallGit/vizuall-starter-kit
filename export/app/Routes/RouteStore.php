<?php

namespace App\Routes;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Pages that are a template and an address, and nothing else.
 *
 * A search page, a style guide, a landing page with no entry behind it: in
 * Statamic that is `Route::statamic('soeg', 'search', [...])` in routes/web.php.
 * This is the same thing written down instead of coded, so a designer can add
 * one from the Control Panel and never open a PHP file.
 *
 * A row is `uri`, `view`, `title`, `data` and `enabled`. `title` is a field of
 * its own because it is the one every page wants; `data` is anything else the
 * template reads — both arrive in Antlers as plain variables.
 *
 * Content rather than storage: a route is a decision the next deploy has to
 * carry, same as a redirect. See App\Redirects\RedirectStore.
 */
class RouteStore
{
    /** Addresses the site has already spoken for. A row claiming one is kept but never registered. */
    protected const RESERVED = ['cp', '!', '_', 'vendor', 'assets'];

    /** @var list<array{uri: string, view: string, title: string, data: array<string, string>, enabled: bool, sitemap: bool}>|null */
    protected ?array $rows = null;

    /** @return list<array{uri: string, view: string, title: string, data: array<string, string>, enabled: bool, sitemap: bool}> */
    public function all(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $path = $this->path();

        if (! File::exists($path)) {
            return $this->rows = [];
        }

        $parsed = Yaml::parseFile($path);
        $rows = is_array($parsed['routes'] ?? null) ? $parsed['routes'] : [];

        return $this->rows = array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->clean($row) : null,
            $rows
        )));
    }

    /** @param  array<mixed>  $rows */
    public function save(array $rows): void
    {
        $clean = array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->clean($row) : null,
            $rows
        )));

        $path = $this->path();
        $dir = dirname($path);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, Yaml::dump(['routes' => $clean], 5, 2));

        $this->rows = $clean;
    }

    /**
     * The rows that may actually become routes: switched on, pointing at a
     * template that exists, and not claiming an address the site already uses.
     *
     * A row that fails any of those is left in the file — deleting somebody's
     * work because a template is briefly missing would be worse than a line
     * that does nothing — and the screen says why.
     *
     * @return list<array{uri: string, view: string, title: string, data: array<string, string>, enabled: bool, sitemap: bool}>
     */
    public function registrable(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $row) => $row['enabled'] && $this->problem($row) === null
        ));
    }

    /** Why this row cannot be a route, in Danish, for the screen. Null when it is fine. */
    public function problem(array $row): ?string
    {
        if (! $this->viewExists($row['view'])) {
            return 'Skabelonen findes ikke.';
        }

        $first = explode('/', trim($row['uri'], '/'))[0] ?? '';

        if (in_array($first, static::RESERVED, true) || $first === trim((string) config('statamic.routes.action', '!'), '/')) {
            return 'Adressen er optaget af systemet.';
        }

        return null;
    }

    /** What a row hands the template: the title plus whatever else was typed. */
    public function data(array $row): array
    {
        return array_merge($row['data'], array_filter(['title' => $row['title']]));
    }

    /**
     * Every template a route may point at — the views that render a whole page.
     * Partials are not pages, and neither is the layout.
     *
     * @return list<string>
     */
    public function views(): array
    {
        $root = resource_path('views');

        if (! File::isDirectory($root)) {
            return [];
        }

        $views = [];

        foreach (File::allFiles($root) as $file) {
            if (! str_ends_with($file->getFilename(), '.antlers.html')) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $name = str_replace('/', '.', substr($relative, 0, -strlen('.antlers.html')));

            if (str_starts_with($name, 'partials.') || str_contains($name, '.partials.') || str_contains($name, 'layout')) {
                continue;
            }

            $views[] = $name;
        }

        sort($views);

        return $views;
    }

    public function viewExists(string $view): bool
    {
        return $view !== '' && view()->exists($view);
    }

    /**
     * @return array{uri: string, view: string, title: string, data: array<string, string>, enabled: bool, sitemap: bool}|null
     */
    protected function clean(array $row): ?array
    {
        $uri = $this->normalise((string) ($row['uri'] ?? ''));
        $view = trim((string) ($row['view'] ?? ''));

        if ($uri === '' || $uri === '/' || $view === '') {
            return null;
        }

        return [
            'uri' => $uri,
            'view' => $view,
            'title' => trim((string) ($row['title'] ?? '')),
            'data' => $this->parseData($row['data'] ?? []),
            'enabled' => (bool) ($row['enabled'] ?? true),
            'sitemap' => (bool) ($row['sitemap'] ?? false),
        ];
    }

    /**
     * Extra variables, typed one `nøgle: værdi` per line on the screen, or
     * already a map when they come back out of the file.
     *
     * Split on the first colon only, and never run it through a YAML parser:
     * a Danish sentence with a colon in it is a value, not a syntax error.
     *
     * @return array<string, string>
     */
    public function parseData(mixed $data): array
    {
        if (is_array($data)) {
            $out = [];

            foreach ($data as $key => $value) {
                if (is_string($key) && $key !== '' && is_scalar($value)) {
                    $out[$key] = (string) $value;
                }
            }

            return $out;
        }

        $out = [];

        foreach (preg_split('/\R/', (string) $data) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);

            if ($key !== '') {
                $out[$key] = trim($value);
            }
        }

        return $out;
    }

    /** The data block the way the screen shows it for editing. */
    public function dataAsLines(array $row): string
    {
        $lines = [];

        foreach ($row['data'] as $key => $value) {
            $lines[] = $key.': '.$value;
        }

        return implode("\n", $lines);
    }

    /** One leading slash, no trailing one, no query. `/` itself is not a route you may take. */
    public function normalise(string $value): string
    {
        $path = (string) strtok(trim($value), '?#');
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function path(): string
    {
        return base_path('content/routes.yaml');
    }
}
