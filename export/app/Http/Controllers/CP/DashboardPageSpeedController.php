<?php

namespace App\Http\Controllers\CP;

use App\Dashboard\PageSpeedStore;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Gemmer én måling, som browseren lige har hentet gennem Visual Editors
 * PageSpeed-endepunkt.
 *
 * Alt der kommer ind her, har været en tur forbi browseren og er derfor ikke
 * til at stole på: scoren klippes til 0-100, lab-tallene til tre korte
 * strenge, og siden skal findes som en udgiven side på dette site. Uden det
 * ville feltet være et frit tekstfelt i en fil dashboardet viser frem.
 */
class DashboardPageSpeedController extends CpController
{
    protected const LEVELS = ['good', 'ok', 'bad'];

    public function store(Request $request, PageSpeedStore $store)
    {
        $this->authorize('access cp');

        $data = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'strategy' => ['nullable', 'in:mobile,desktop'],
            'source' => ['nullable', 'in:google,local'],
            'lab' => ['nullable', 'array', 'max:8'],
            'lab.*.label' => ['nullable', 'string', 'max:60'],
            'lab.*.value' => ['nullable', 'string', 'max:30'],
            'lab.*.level' => ['nullable', 'string', 'max:10'],
        ]);

        $entry = Entry::find($data['id']);

        abort_unless($entry && $entry->collectionHandle() === 'pages', 422, 'Ukendt side.');

        $store->put($entry->id(), [
            'score' => (int) round($data['score']),
            'strategy' => $data['strategy'] ?? 'mobile',
            'source' => $data['source'] ?? 'google',
            'lab' => collect($data['lab'] ?? [])
                ->take(PageSpeedStore::LAB_LIMIT)
                ->map(fn (array $row) => [
                    'label' => trim((string) ($row['label'] ?? '')),
                    'value' => trim((string) ($row['value'] ?? '')),
                    'level' => in_array($row['level'] ?? '', static::LEVELS, true) ? $row['level'] : 'ok',
                ])
                ->filter(fn (array $row) => $row['label'] !== '' && $row['value'] !== '')
                ->values()
                ->all(),
            'measured_at' => now()->toIso8601String(),
        ]);

        return ['ok' => true, 'average' => $store->average()];
    }
}
