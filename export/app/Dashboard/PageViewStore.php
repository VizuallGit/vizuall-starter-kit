<?php

namespace App\Dashboard;

use Illuminate\Support\Facades\File;
use Statamic\Contracts\Entries\Entry;

class PageViewStore
{
    public function increment(Entry $entry): void
    {
        if (! $this->isPublicPage($entry)) {
            return;
        }

        $pages = $this->pages();
        $id = $entry->id();

        $pages[$id] = [
            'views' => (int) ($pages[$id]['views'] ?? 0) + 1,
            'title' => $entry->get('title') ?: $entry->slug() ?: $id,
            'url' => $entry->url() ?: '/',
            'updated_at' => now()->toIso8601String(),
        ];

        $this->write($pages);
    }

    public function top(int $limit = 8): array
    {
        $pages = $this->pages();
        $max = collect($pages)->max('views') ?: 1;

        return collect($pages)
            ->map(function (array $row, string $id) use ($max) {
                $views = (int) ($row['views'] ?? 0);

                return [
                    'id' => $id,
                    'title' => $row['title'] ?? $id,
                    'url' => $row['url'] ?? '/',
                    'edit_url' => $this->editUrl($id),
                    'views' => $views,
                    'percent' => (int) round(($views / $max) * 100),
                ];
            })
            ->sortByDesc('views')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * Demo-seed the first time, so the widget is not empty before anyone visits.
     * Real page views increment from here.
     */
    public function pages(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            $this->seed();
        }

        if (! File::exists($path)) {
            return [];
        }

        $parsed = json_decode(File::get($path), true);

        return is_array($parsed['pages'] ?? null) ? $parsed['pages'] : [];
    }

    protected function seed(): void
    {
        $pages = [];

        try {
            $entries = \Statamic\Facades\Entry::query()
                ->whereStatus('published')
                ->get();
        } catch (\Throwable) {
            return;
        }

        foreach ($entries as $entry) {
            if (! $this->isPublicPage($entry)) {
                continue;
            }

            $pages[$entry->id()] = [
                'views' => (crc32((string) $entry->id()) % 90) + 8,
                'title' => $entry->get('title') ?: $entry->slug() ?: $entry->id(),
                'url' => $entry->url() ?: '/',
                'updated_at' => now()->toIso8601String(),
            ];
        }

        if ($pages !== []) {
            $this->write($pages);
        }
    }

    protected function write(array $pages): void
    {
        $dir = dirname($this->path());

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->path(), json_encode(['pages' => $pages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function editUrl(string $id): ?string
    {
        try {
            $entry = \Statamic\Facades\Entry::find($id);

            return $entry?->editUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isPublicPage(Entry $entry): bool
    {
        return $entry->collection()?->handle() === 'pages'
            && (bool) $entry->url();
    }

    protected function path(): string
    {
        return storage_path('app/dashboard/page-views.json');
    }
}
