<?php

namespace App\Http\Controllers\CP;

use App\Dashboard\WidgetCatalog;
use Illuminate\Http\Request;
use Statamic\Facades\Preference;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

class DashboardWidgetsController extends CpController
{
    public function show(WidgetCatalog $catalog)
    {
        $this->authorize('access cp');

        return $catalog->payload();
    }

    public function update(Request $request, WidgetCatalog $catalog)
    {
        $this->authorize('access cp');

        User::current()
            ->setPreference('widgets', $catalog->configsFor($this->types($request, $catalog)))
            ->save();

        return ['ok' => true];
    }

    public function updateDefault(Request $request, WidgetCatalog $catalog)
    {
        $this->authorize('access cp');
        abort_unless($catalog->canManageDefault(), 403);

        Preference::default()
            ->set('widgets', $catalog->configsFor($this->types($request, $catalog)))
            ->save();

        return ['ok' => true];
    }

    public function destroy()
    {
        $this->authorize('access cp');

        User::current()
            ->removePreference('widgets')
            ->save();

        return ['ok' => true];
    }

    protected function types(Request $request, WidgetCatalog $catalog): array
    {
        $validated = $request->validate([
            'types' => 'present|array',
            'types.*' => 'string',
        ]);

        $allowed = $catalog->allowedTypes();

        return collect($validated['types'])
            ->filter(fn ($type) => in_array($type, $allowed, true))
            ->values()
            ->all();
    }
}
