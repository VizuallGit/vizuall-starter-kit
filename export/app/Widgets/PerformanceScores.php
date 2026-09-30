<?php

namespace App\Widgets;

use App\Dashboard\PageSpeedStore;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Entry;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * Ydelse pr. side, som Google sidst målte den.
 *
 * Widgeten måler ikke selv. Den viser hvad der er gemt, og lader redaktøren
 * sætte en måling i gang — den går gennem Visual Editors eget endepunkt, så
 * API-nøglen bliver på serveren og der er ét sted der taler med Google.
 */
class PerformanceScores extends Widget
{
    public function component()
    {
        $store = app(PageSpeedStore::class);
        $stored = $store->all();

        $rows = Entry::query()
            ->where('collection', 'pages')
            ->whereStatus('published')
            ->get()
            ->filter(fn ($entry) => (bool) $entry->absoluteUrl())
            ->map(function ($entry) use ($stored) {
                $row = $stored[$entry->id()] ?? null;

                return [
                    'id' => $entry->id(),
                    'title' => (string) ($entry->get('title') ?: $entry->slug() ?: $entry->id()),
                    'url' => $entry->absoluteUrl(),
                    'edit_url' => $entry->editUrl(),
                    'score' => is_numeric($row['score'] ?? null) ? (int) $row['score'] : null,
                    'lab' => is_array($row['lab'] ?? null) ? $row['lab'] : [],
                    'strategy' => $row['strategy'] ?? 'mobile',
                    'source' => $row['source'] ?? null,
                    'measured_at' => $row['measured_at'] ?? null,
                ];
            })
            ->values()
            ->all();

        return VueComponent::render('PerformanceScores', [
            'handle' => static::handle(),
            'rows' => $rows,
            'average' => $store->average(),
            'perPage' => (int) $this->config('per_page', 5),
            // Visual Editor ejer målingen. Findes ruten ikke, er addonet ikke
            // installeret — så siger widgeten det i stedet for at fejle stille.
            'endpoint' => Route::has('sve.pagespeed') ? route('sve.pagespeed') : null,
            'saveUrl' => cp_route('dashboard-pagespeed.store'),
            'strategy' => in_array($this->config('strategy'), ['mobile', 'desktop'], true)
                ? $this->config('strategy')
                : 'mobile',
        ]);
    }
}
