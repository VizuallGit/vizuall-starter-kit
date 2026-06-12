<?php

namespace App\Providers;

use App\Fieldtypes\ButtonPreview;
use App\Modifiers\ContrastColor;
use App\Modifiers\ToInt;
use App\Modifiers\WithTypeIndex;
use App\Tags\ColorSchemesJson;
use App\Tags\FileCode;
use App\Tags\RenderAntlers;
use App\Tags\ScopeCss;
use App\Tags\ScriptPush;
use App\Tags\SectionYaml;
use App\Tags\StylePush;
use App\Tags\YieldMinified;
use App\Tags\YieldScripts;
use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\CP\ComponentExporterController;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Utility;
use Statamic\Modifiers\Modifier;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->extend(
            \Statamic\Contracts\Entries\CollectionRepository::class,
            fn ($repo) => new class($repo) {
                public function __construct(private object $inner) {}

                public function all(): \Illuminate\Support\Collection
                {
                    $collections = $this->inner->all();

                    $cpRoute = config('statamic.cp.route', 'cp');
                    if (! request()->is($cpRoute) && ! request()->is($cpRoute . '/*')) {
                        return $collections;
                    }

                    try {
                        $global  = \Statamic\Facades\GlobalSet::find('theme_settings');
                        $default = \Statamic\Facades\Site::default()->handle();
                        $data    = $global?->in($default)?->data();
                        if (! $data) return $collections;

                        $map = [
                            'show_blog'              => 'blog',
                            'show_employees'         => 'employees',
                            'show_services'          => 'services',
                            'show_cases'             => 'cases',
                            'show_events'            => 'events',
                            'show_products'          => 'products',
                            'show_testimonials'      => 'testimonials',
                            'show_reusable_sections' => 'reusable_sections',
                        ];

                        $hidden = collect($map)
                            ->filter(fn ($handle, $field) => $data->get($field, true) === false)
                            ->values()
                            ->all();

                        return $collections->reject(fn ($c) => in_array($c->handle(), $hidden));
                    } catch (\Throwable) {
                        return $collections;
                    }
                }

                public function __call(string $method, array $args): mixed
                {
                    return $this->inner->$method(...$args);
                }
            }
        );

        Statamic::vite('app', [
            'input' => [
                'resources/js/cp.js',
                'resources/css/cp.css',
            ],
            'hotFile' => public_path('cp-hot'),
            'buildDirectory' => 'vendor/app',
        ]);

        Modifier::register('contrast_color', ContrastColor::class);
        Modifier::register('to_int', ToInt::class);
        Modifier::register('with_type_index', WithTypeIndex::class);
        ColorSchemesJson::register();
        FileCode::register();
        RenderAntlers::register();
        ScopeCss::register();
        SectionYaml::register();
        StylePush::register();
        YieldMinified::register();
        ScriptPush::register();
        YieldScripts::register();
        ButtonPreview::register();

        Utility::register('component-exporter')
            ->title('Komponent Eksport')
            ->description('Eksporter og importer page sections, blueprints og collections som ZIP')
            ->icon('export')
            ->view('utilities.component-exporter')
            ->routes(function ($router) {
                $router->get('items', [ComponentExporterController::class, 'items']);
                $router->post('export', [ComponentExporterController::class, 'export']);
                $router->post('import', [ComponentExporterController::class, 'import']);
            });

        Statamic::booted(function () {
            Nav::extend(function ($nav) {
                $global = GlobalSet::find('theme_settings');
                if (! $global) return;

                $default = \Statamic\Facades\Site::default()->handle();
                $data = $global->in($default)?->data();
                if (! $data) return;

                $map = [
                    'show_blog'              => 'Blog',
                    'show_employees'         => 'Employees',
                    'show_services'          => 'Services',
                    'show_cases'             => 'Cases',
                    'show_events'            => 'Events',
                    'show_products'          => 'Products',
                    'show_testimonials'      => 'Testimonials',
                    'show_reusable_sections' => 'Reusable sections',
                ];

                foreach ($map as $field => $label) {
                    if ($data->get($field, true) === false) {
                        $nav->remove('Content', 'Collections', $label);
                    }
                }
            });
        });
    }
}
