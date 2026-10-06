<?php

namespace App\Providers;

use App\Dashboard\ResilientWidgetLoader;
use App\Dashboard\WidgetCatalog;
use App\Env\EnvKeys;
use App\Http\Controllers\CP\DashboardPageSpeedController;
use App\Http\Controllers\CP\DashboardWidgetsController;
use App\Http\Controllers\CP\EnvKeysController;
use App\Http\Controllers\CP\RedirectsController;
use App\Http\Controllers\CP\RoutesController;
use App\Redirects\MissingLog;
use App\Redirects\RedirectStore;
use App\Http\Controllers\SitemapController;
use App\Routes\RouteStore;
use App\Seo\Sitemap;
use App\Seo\SitemapSettings;
use App\Tags\FileCode;
use App\Tags\SectionYaml;
use App\Tags\SeoRobots;
use App\Tags\ThemeTokens;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Icon;
use Statamic\Facades\User;
use Statamic\Facades\Utility;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // En widget der er fjernet, må ikke kunne vælte hele dashboardet for de
        // brugere der havde den i deres gemte liste. Se ResilientWidgetLoader.
        $this->app->bind(\Statamic\Widgets\Loader::class, ResilientWidgetLoader::class);

        // Responsive felter på globale sæt pakkes ind af addonet selv siden
        // visual-editor v1.1.180 (`WrapResponsiveGlobalFields` bor dér, ved
        // siden af `ResponsiveFields`). Intet at gøre her.
    }

    public function boot(): void
    {
        // Alpine og auto-kontrast skal med i den HTML den statiske cache gemmer.
        // Statamic lægger sin Cache-middleware på statamic.web i en booted-callback,
        // og den her provider booter bagefter, så push lander indenfor cachen.
        $this->app->booted(function () {
            $this->app->make(\Illuminate\Routing\Router::class)
                ->pushMiddlewareToGroup('statamic.web', \App\Http\Middleware\LoadUsedFrontendScripts::class);
        });

        // Den statiske udgivelse (Static Publish → statamic/ssg) og Visual
        // Editors preview-billeder (/!/sve/…-preview, uden for statamic.web)
        // kalder toResponse() uden middlewaren. Samme arbejde på Statamics
        // ResponseCreated — ellers mangler auto-kontrast og Alpine i den
        // udgivne kopi og i Patterns-billederne. På statamic.web-sider er
        // tagget allerede der, når middlewaren kommer, og den springer over.
        \Illuminate\Support\Facades\Event::listen(\Statamic\Events\ResponseCreated::class, function ($event) {
            $this->app->make(\App\Http\Middleware\LoadUsedFrontendScripts::class)
                ->handle(request(), fn () => $event->response);
        });

        // Sidens CSS renses i den udgivne kopi også (se CleanPageCss). Efter
        // scripts-lytteren, så de scripts siden henter er med, når den ser
        // hvilke klasser der bruges.
        \Illuminate\Support\Facades\Event::listen(\Statamic\Events\ResponseCreated::class, [\App\Frontend\CleanPageCss::class, 'created']);

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
        SeoRobots::register();
        ThemeTokens::register();

        // Dashboardets standardwidgets. `config/statamic/cp.php` følger ikke med
        // starter kittet, så et nyt site startede med Statamics tomme liste og
        // dermed et dashboard uden et eneste kort — selv om widget-koden var
        // installeret. En tom liste er ikke et valg nogen har truffet, så her
        // lægges vores egen ind. Har nogen sat noget, står det urørt.
        if (empty(config('statamic.cp.widgets'))) {
            config(['statamic.cp.widgets' => WidgetCatalog::defaults()]);
        }

        Statamic::pushCpRoutes(function () {
            Route::get('dashboard-widgets', [DashboardWidgetsController::class, 'show'])->name('dashboard-widgets.show');
            Route::put('dashboard-widgets', [DashboardWidgetsController::class, 'update'])->name('dashboard-widgets.update');
            Route::put('dashboard-widgets/default', [DashboardWidgetsController::class, 'updateDefault'])->name('dashboard-widgets.default');
            Route::delete('dashboard-widgets', [DashboardWidgetsController::class, 'destroy'])->name('dashboard-widgets.destroy');
            Route::post('dashboard-pagespeed', [DashboardPageSpeedController::class, 'store'])->name('dashboard-pagespeed.store');
        });

        Statamic::provideToScript([
            'dashboardWidgets' => function () {
                return User::current() ? app(WidgetCatalog::class)->payload() : null;
            },
        ]);

        $this->bootRedirectsUtility();
        $this->bootRoutesUtility();
        $this->bootCustomRoutes();
        $this->bootSitemap();
        $this->bootEnvKeys();
    }

    /**
     * Nøgler under Værktøjer.
     *
     * Skærmen skriver i `.env`, ikke i en indstillingsfil: alt under
     * resources/ ligger i git, og en API-nøgle dér ville følge med til
     * GitHub og til hvert site bygget på kittet. Se App\Env\EnvKeys for
     * hvad der med vilje IKKE kan sættes herfra.
     */
    protected function bootEnvKeys(): void
    {
        Utility::extend(function () {
            Utility::register('keys')
                ->title('Nøgler')
                ->navTitle('Nøgler')
                ->icon('key')
                ->description('API-nøgler sitet bruger. Gemmes i .env, så de aldrig havner i git.')
                ->view('utilities.env_keys', fn () => [
                    'rows' => app(EnvKeys::class)->rows(),
                    'writable' => app(EnvKeys::class)->writable(),
                ])
                ->routes(function ($router) {
                    $router->post('/', [EnvKeysController::class, 'save'])->name('save');
                });
        });
    }

    /**
     * /sitemap.xml og /robots.txt, plus skærmen der viser hvad der er med.
     *
     * Begge er ruter, ikke filer: sitemappen skal være rigtig i samme sekund
     * en side bliver udgivet, og robots.txt skal kunne sige det domæne
     * requesten kom ind på. Der må derfor ikke ligge en robots.txt i public/
     * — webserveren ville svare med den, længe før Laravel ser requesten.
     */
    protected function bootSitemap(): void
    {
        Statamic::pushWebRoutes(function () {
            Route::get('/sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap');
            Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
            // Læses kun af det statiske udtræk, men skal være en rigtig rute:
            // eksporten henter den som en side og skriver svaret som fil.
            Route::get('/_redirects', [SitemapController::class, 'cloudflareRedirects'])->name('cloudflare-redirects');
        });

        Utility::extend(function () {
            Utility::register('sitemap')
                ->title('Sitemap')
                ->navTitle('Sitemap')
                ->icon('hierarchy')
                ->description('Hvilke adresser søgemaskinerne får at vide om — og hvilke der holdes ude, og hvorfor.')
                ->view('utilities.sitemap', fn () => array_merge(
                    app(Sitemap::class)->rows(),
                    [
                        'xml' => url('/sitemap.xml'),
                        'robots' => url('/robots.txt'),
                        'collections' => app(SitemapSettings::class)->collections(),
                        'excluded_collections' => app(SitemapSettings::class)->excluded(),
                        'search_engines' => app(SitemapSettings::class)->searchEngines(),
                        'indexable' => app(SitemapSettings::class)->siteIndexable(),
                        'environment' => app()->environment(),
                    ]
                ))
                ->routes(function ($router) {
                    $router->post('/', [RoutesController::class, 'saveSitemap'])->name('save');
                });
        });
    }

    /**
     * Omdirigeringer under Værktøjer.
     *
     * Registreres site-bredt, ikke pr. bruger: `Utility::routes()` bygger ruten
     * mens ruter registreres, længe før der er en bruger at spørge om noget.
     * Adgangen afgøres pr. request af Statamics egen `can:access … utility`,
     * som repositoriet selv lægger på både siden og dens ruter.
     *
     * Ingen `__()` her — oversættelser hentet under boot forgifter cachen, og
     * teksterne står alligevel på dansk i både skærmen og dens view.
     */
    /**
     * Ruter under Værktøjer.
     */
    protected function bootRoutesUtility(): void
    {
        Utility::extend(function () {
            Utility::register('routes')
                ->title('Ruter')
                ->navTitle('Ruter')
                ->icon('arrow-right-box')
                ->description('Sider der kun er en adresse og en skabelon — en søgeside, en stilguide.')
                ->view('utilities.routes', fn () => [
                    'rows' => app(RouteStore::class)->all(),
                    'views' => app(RouteStore::class)->views(),
                    'store' => app(RouteStore::class),
                ])
                ->routes(function ($router) {
                    $router->post('/', [RoutesController::class, 'save'])->name('save');
                });
        });
    }

    /**
     * Ruterne fra `content/routes.yaml`, som rigtige Laravel-ruter.
     *
     * `pushWebRoutes` lægger dem præcis dér hvor en håndskrevet
     * `Route::statamic()` i routes/web.php ville have ligget: efter CP'et og
     * action-ruterne, men FØR Statamics egen opsamlingsrute — så en rute
     * vinder over en side med samme adresse, og rækkefølgen er den samme som
     * hvis linjen var skrevet i hånden.
     *
     * Kun rækker der kan lade sig gøre registreres. En skabelon der ikke
     * findes ville give en 500 på forsiden af sitet; i stedet bliver linjen
     * stående i filen, og skærmen siger hvorfor den ikke svarer.
     */
    protected function bootCustomRoutes(): void
    {
        Statamic::pushWebRoutes(function () {
            $store = app(RouteStore::class);

            foreach ($store->registrable() as $row) {
                Route::statamic($row['uri'], $row['view'], $store->data($row));
            }
        });
    }

    protected function bootRedirectsUtility(): void
    {
        Utility::extend(function () {
            Utility::register('redirects')
                ->title('Omdirigeringer')
                ->navTitle('Omdirigeringer')
                ->icon('arrow-roadmap-path-flow')
                ->description('Adresser der er flyttet, og de 404\'ere der endnu ikke er svaret på.')
                ->view('utilities.redirects', fn () => [
                    'rows' => app(RedirectStore::class)->all(),
                    'statuses' => RedirectStore::STATUSES,
                    'missing' => app(MissingLog::class)->top(),
                ])
                ->routes(function ($router) {
                    $router->post('/', [RedirectsController::class, 'save'])->name('save');
                    $router->post('promote', [RedirectsController::class, 'promote'])->name('promote');
                    $router->post('forget', [RedirectsController::class, 'forget'])->name('forget');
                });
        });
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
