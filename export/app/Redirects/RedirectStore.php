<?php

namespace App\Redirects;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * The site's redirects, as one YAML file under content/.
 *
 * Content rather than storage, because a redirect is a decision somebody made
 * and the next deploy has to carry it: the file is committed, and the server's
 * own edits come back through GitSync like every other content change.
 *
 * A row is `from`, `to`, `status` and `enabled`. `from` is a path on this site;
 * `to` is a path or a full URL somewhere else. Both may end in `/*`, which
 * carries the rest of the path across:
 *
 *     from: /blog/*   to: /nyheder/*   →   /blog/2024/foo  →  /nyheder/2024/foo
 *     from: /blog/*   to: /nyheder     →   /blog/2024/foo  →  /nyheder
 */
class RedirectStore
{
    /** Statuses the screen offers. Anything else in the file is left alone but never sent. */
    public const STATUSES = [301, 302, 307, 308];

    /** @var list<array{from: string, to: string, status: int, enabled: bool}>|null */
    protected ?array $rows = null;

    /**
     * Every row in the file, cleaned. Order is the file's order, and it is the
     * order they are tried in — the first match wins, so a hand-sorted file is
     * a hand-sorted priority list.
     *
     * @return list<array{from: string, to: string, status: int, enabled: bool}>
     */
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
        $rows = is_array($parsed['redirects'] ?? null) ? $parsed['redirects'] : [];

        return $this->rows = array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->clean($row) : null,
            $rows
        )));
    }

    /**
     * Replace the file with these rows. Cleaning happens here too, so a row
     * typed on the screen and a row typed into the file get the same treatment.
     *
     * @param  array<mixed>  $rows
     */
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

        File::put($path, Yaml::dump(['redirects' => $clean], 4, 2));

        $this->rows = $clean;
    }

    /**
     * Where `$path` should go, or null if nothing claims it.
     *
     * @return array{to: string, status: int}|null
     */
    public function match(string $path): ?array
    {
        $needle = $this->normalise($path);

        foreach ($this->all() as $row) {
            if (! $row['enabled']) {
                continue;
            }

            $to = $this->target($row, $needle);

            if ($to !== null && $to !== $needle) {
                return ['to' => $to, 'status' => $row['status']];
            }
        }

        return null;
    }

    /** Where this one row sends `$needle`, or null if it does not match it. */
    protected function target(array $row, string $needle): ?string
    {
        $from = $row['from'];

        if (! str_ends_with($from, '/*')) {
            return strcasecmp($from, $needle) === 0 ? $row['to'] : null;
        }

        $prefix = substr($from, 0, -2);

        // `/blog/*` claims `/blog/x` and `/blog` itself, not `/blogroll`.
        if (strcasecmp($prefix, $needle) !== 0 && stripos($needle, $prefix.'/') !== 0) {
            return null;
        }

        $rest = ltrim(substr($needle, strlen($prefix)), '/');

        if (! str_ends_with($row['to'], '/*')) {
            return $row['to'];
        }

        return rtrim(substr($row['to'], 0, -2), '/').($rest === '' ? '' : '/'.$rest);
    }

    /**
     * One row, trusted. Empty `from` or `to` makes the row null — the caller
     * drops it, so a half-typed line never becomes a live redirect.
     *
     * @return array{from: string, to: string, status: int, enabled: bool}|null
     */
    protected function clean(array $row): ?array
    {
        $from = $this->normalise((string) ($row['from'] ?? ''));
        $to = trim((string) ($row['to'] ?? ''));

        if ($from === '' || $from === '/' || $to === '') {
            return null;
        }

        $status = (int) ($row['status'] ?? 301);

        return [
            'from' => $from,
            'to' => $this->isAbsolute($to) ? $to : $this->normalise($to),
            'status' => in_array($status, static::STATUSES, true) ? $status : 301,
            'enabled' => (bool) ($row['enabled'] ?? true),
        ];
    }

    /**
     * A path the way both the file and the request are compared: one leading
     * slash, no trailing one, no query, no host. `/*` survives at the end.
     */
    public function normalise(string $value): string
    {
        $path = trim($value);

        if ($this->isAbsolute($path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = (string) strtok($path, '?#');
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    protected function isAbsolute(string $value): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $value);
    }

    public function path(): string
    {
        return base_path('content/redirects.yaml');
    }
}
