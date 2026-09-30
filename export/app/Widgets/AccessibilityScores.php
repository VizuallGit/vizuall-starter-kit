<?php

namespace App\Widgets;

use App\Dashboard\AccessibilityScanner;
use Statamic\Facades\Entry;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/** Tilgængelighed pr. udgiven side, læst i indholdet. */
class AccessibilityScores extends Widget
{
    public function component()
    {
        $scanner = app(AccessibilityScanner::class);

        $pages = Entry::query()
            ->where('collection', 'pages')
            ->whereStatus('published')
            ->get()
            ->map(function ($entry) use ($scanner) {
                try {
                    return $scanner->for($entry);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();

        $scores = collect($pages)->pluck('score');

        return VueComponent::render('AccessibilityScores', [
            'handle' => static::handle(),
            'rows' => $pages,
            'average' => $scores->isEmpty() ? null : (int) round($scores->avg()),
            'perPage' => (int) $this->config('per_page', 5),
            'counts' => [
                'good' => collect($pages)->where('status', 'good')->count(),
                'ok' => collect($pages)->where('status', 'ok')->count(),
                'bad' => collect($pages)->where('status', 'bad')->count(),
            ],
        ]);
    }
}
