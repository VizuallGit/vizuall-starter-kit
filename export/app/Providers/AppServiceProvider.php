<?php

namespace App\Providers;

use App\Dashboard\WidgetCatalog;
use App\Http\Controllers\CP\DashboardWidgetsController;
use App\Tags\FileCode;
use App\Tags\SectionYaml;
use App\Tags\ThemeTokens;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Icon;
use Statamic\Facades\User;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Responsive felter på globale sæt pakkes ind af addonet selv siden
        // visual-editor v1.1.180 (`WrapResponsiveGlobalFields` bor dér, ved
        // siden af `ResponsiveFields`). Intet at gøre her.
    }

    public function boot(): void
    {
        // Custom SVGs for Replicator/Bard set icons (Edit Set → Custom icon field,
        // or filename in YAML). Does not replace Statamic's default picker list —
        // call Sets::useIcons('vizuall', …) if you want these in the picker too.
        Icon::register('vizuall', resource_path('svg/set-icons'));

        // ── Flyttet til visual editor-addonet ────────────────────────────────
        //
        // "Lås rækker" og "Kun én af hver" registreres nu af addonets
        // `ReplicatorSettings`, kaldt fra dets RegisterPanelVisibility-middleware.
        // Begge lægges stadig på Statamics EGNE Replicator- og Grid-klasser,
        // præcis som her — det er dér `App\Fieldtypes\Replicator` læste dem fra,
        // og dér addonets egen Replicator læser dem fra nu.


        // Responsive-feltet bor i visual editor-addonet
        // (`ResponsiveFieldtype`, `ResponsiveFields`, `WrapResponsiveFields`).
        // Ikke i app/.

        // CP-assets. Bemærk hvor de to ender i dokumentet — det er hele pointen:
        //
        // Statamic rendrer registrerede Vite-entries fra
        // `statamic::partials.scripts`, altså i BUNDEN af <body>. For JS er det
        // rigtigt. For CSS er det en fælde: browseren har allerede tegnet hele
        // siden med Statamics egen CSS fra <head>, og finder først vores bagefter
        // — så siden hopper fra CP'ets standardudseende over i vores. Det er dét
        // man ser som "gammel CSS, så ny CSS".
        //
        // Derfor står CSS'en ikke her, men registreres som en <link> i <head> via
        // `Statamic::externalStyle()` nedenfor. Samme byggede fil, samme manifest
        // — kun placeringen i dokumentet er en anden, og en <link> i <head>
        // blokerer for tegning, hvilket er præcis dét vi vil have.
        //
        // Under `npm run cp:dev` findes cp-hot: der serverer Vite CSS'en selv og
        // injicerer den via JS, og så skal den blive stående blandt entries.
        $cpHot = file_exists(public_path('cp-hot'));

        Statamic::vite('app', [
            'input' => array_values(array_filter([
                'resources/js/cp.js',
                $cpHot ? 'resources/css/cp.css' : null,
            ])),
            'hotFile' => public_path('cp-hot'),
            'buildDirectory' => 'vendor/app',
        ]);

        if (! $cpHot && $cpCss = $this->builtCpStylesheet()) {
            Statamic::externalStyle($cpCss);
        }

        // contrast_color, highlight_color, to_int, with_type_index,
        // render_antlers, scope_css, style_push,
        // script_push, yield_minified, yield_scripts og button_preview
        // registreres nu af hvert sit addon. Kun dem der er bundet til
        // dette projekts egne stier bliver her.
        FileCode::register();
        SectionYaml::register();
        ThemeTokens::register();

        Statamic::pushCpRoutes(function () {
            Route::get('dashboard-widgets', [DashboardWidgetsController::class, 'show'])->name('dashboard-widgets.show');
            Route::put('dashboard-widgets', [DashboardWidgetsController::class, 'update'])->name('dashboard-widgets.update');
            Route::put('dashboard-widgets/default', [DashboardWidgetsController::class, 'updateDefault'])->name('dashboard-widgets.default');
            Route::delete('dashboard-widgets', [DashboardWidgetsController::class, 'destroy'])->name('dashboard-widgets.destroy');
        });

        Statamic::provideToScript([
            'dashboardWidgets' => function () {
                return User::current() ? app(WidgetCatalog::class)->payload() : null;
            },
        ]);
    }

    /**
     * URL'en til den byggede cp.css, læst ud af CP-buildets eget manifest.
     *
     * Filnavnet er hashet af Vite, så det kan ikke skrives ind i hånden — og
     * hashen er netop dét der gør en <link> i <head> forsvarlig at cache hårdt.
     * Er der ikke bygget endnu, returneres null: en manglende stylesheet må
     * hverken kaste eller lægge en død <link> i hovedet.
     */
    protected function builtCpStylesheet(): ?string
    {
        $manifest = public_path('vendor/app/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        $entries = json_decode(file_get_contents($manifest), true);
        $file = $entries['resources/css/cp.css']['file'] ?? null;

        return $file ? asset('vendor/app/'.$file) : null;
    }
}
