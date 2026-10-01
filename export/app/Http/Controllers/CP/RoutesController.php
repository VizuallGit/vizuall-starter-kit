<?php

namespace App\Http\Controllers\CP;

use App\Routes\RouteStore;
use App\Seo\SitemapSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The routes utility's one move: save the table.
 *
 * A saved route is live on the next request — Statamic registers them from the
 * file every time routes are built, so there is nothing to clear afterwards.
 */
class RoutesController extends Controller
{
    public function save(Request $request, RouteStore $store)
    {
        $rows = $request->input('rows', []);

        $store->save(is_array($rows) ? array_values($rows) : []);

        return back()->with('success', 'Ruterne er gemt.');
    }

    /** Hvilke samlinger sitemappen holder ude. Kun fravalgene gemmes. */
    public function saveSitemap(Request $request, SitemapSettings $settings)
    {
        $excluded = $request->input('exclude', []);

        $settings->save(is_array($excluded) ? $excluded : []);
        $settings->saveSearchEngines((string) $request->input('search_engines', SitemapSettings::SEARCH_AUTO));

        return back()->with('success', 'Sitemappen er opdateret.');
    }
}
