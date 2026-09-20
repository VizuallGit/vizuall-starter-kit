<?php

namespace App\Dashboard;

use Illuminate\Support\Str;
use Statamic\Facades\Preference;
use Statamic\Facades\User;
use Statamic\Statamic;

class WidgetCatalog
{
    protected array $labels = [
        'visual_editor_comments' => 'Kommentarer',
        'most_visited_pages' => 'Mest besøgte sider',
        'seo_score' => 'SEO-status',
    ];

    protected array $descriptions = [
        'visual_editor_comments' => 'Åbne tråde fra Visual Editor, gruppet per side.',
        'most_visited_pages' => 'De sider der bliver besøgt mest på det offentlige site.',
        'seo_score' => 'Tjekliste for overskrifter, meta og indhold på udgivne sider.',
    ];

    public function payload(): array
    {
        $selected = $this->selectedTypes();

        return [
            'widgets' => $this->available()->map(fn (array $item) => [
                'type' => $item['type'],
                'label' => $item['label'],
                'description' => $item['description'],
                'enabled' => in_array($item['type'], $selected, true),
            ])->values()->all(),
            'usingDefault' => ! $this->userHasOverride(),
            'canManageDefault' => $this->canManageDefault(),
            'urls' => [
                'save' => cp_route('dashboard-widgets.update'),
                'saveDefault' => cp_route('dashboard-widgets.default'),
                'reset' => cp_route('dashboard-widgets.destroy'),
            ],
        ];
    }

    public function allowedTypes(): array
    {
        return $this->available()->pluck('type')->all();
    }

    public function configsFor(array $types): array
    {
        $allowed = $this->allowedTypes();
        $defaults = $this->configByType();

        return collect($types)
            ->filter(fn ($type) => in_array($type, $allowed, true))
            ->unique()
            ->values()
            ->map(function ($type) use ($defaults) {
                $config = $defaults->get($type);

                if (is_string($config)) {
                    return ['type' => $config];
                }

                return $config ?? ['type' => $type, 'width' => 100];
            })
            ->all();
    }

    public function userHasOverride(): bool
    {
        $user = User::current();

        return $user && $user->hasPreference('widgets');
    }

    public function canManageDefault(): bool
    {
        $user = User::current();

        if (! $user) {
            return false;
        }

        return $user->isSuper()
            || (Statamic::pro() && $user->can('manage preferences'));
    }

    protected function available()
    {
        $fromConfig = $this->normalize(config('statamic.cp.widgets') ?? []);
        $fromDefault = $this->normalize(Preference::default()->get('widgets') ?? []);

        $known = collect($this->labels)->keys()->map(fn ($type) => ['type' => $type]);

        return $fromConfig
            ->concat($fromDefault)
            ->concat($known)
            ->unique('type')
            ->map(fn (array $item) => [
                'type' => $item['type'],
                'label' => $this->label($item['type']),
                'description' => $this->descriptions[$item['type']] ?? '',
            ])
            ->values();
    }

    protected function selectedTypes(): array
    {
        $widgets = Preference::get('widgets') ?? config('statamic.cp.widgets') ?? [];

        return $this->normalize($widgets)->pluck('type')->all();
    }

    protected function configByType()
    {
        return $this->normalize(config('statamic.cp.widgets') ?? [])
            ->concat($this->normalize(Preference::default()->get('widgets') ?? []))
            ->keyBy('type');
    }

    protected function normalize(array $widgets)
    {
        return collect($widgets)->map(function ($config) {
            if (is_string($config)) {
                return ['type' => $config];
            }

            return $config;
        })->filter(fn ($config) => is_array($config) && ! empty($config['type']));
    }

    protected function label(string $type): string
    {
        if (isset($this->labels[$type])) {
            return $this->labels[$type];
        }

        $widgets = app('statamic.widgets');

        if ($widgets->has($type)) {
            $class = $widgets->get($type);

            if (is_string($class) && method_exists($class, 'title')) {
                return $class::title();
            }
        }

        return Str::title(str_replace('_', ' ', $type));
    }
}
