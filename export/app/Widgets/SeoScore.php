<?php

namespace App\Widgets;

use App\Dashboard\SeoScorer;
use Statamic\Facades\Entry;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class SeoScore extends Widget
{
    public function component()
    {
        $scorer = app(SeoScorer::class);

        $pages = Entry::query()
            ->where('collection', 'pages')
            ->whereStatus('published')
            ->get()
            ->map(function ($entry) use ($scorer) {
                try {
                    return $scorer->for($entry);
                } catch (\Throwable) {
                    return [
                        'id' => $entry->id(),
                        'title' => $entry->get('title') ?: $entry->slug() ?: $entry->id(),
                        'edit_url' => $entry->editUrl(),
                        'status' => 'ok',
                        'score' => 0,
                        'hint' => 'Kunne ikke tjekkes',
                        'issues' => [],
                        'checks' => [],
                        'words' => 0,
                    ];
                }
            })
            ->sortBy(fn (array $row) => ['bad' => 0, 'ok' => 1, 'good' => 2][$row['status']] ?? 9)
            ->values()
            ->all();

        $counts = [
            'good' => collect($pages)->where('status', 'good')->count(),
            'ok' => collect($pages)->where('status', 'ok')->count(),
            'bad' => collect($pages)->where('status', 'bad')->count(),
        ];

        return VueComponent::render('SeoScore', [
            'pages' => $pages,
            'counts' => $counts,
        ]);
    }
}
