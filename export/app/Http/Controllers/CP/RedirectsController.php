<?php

namespace App\Http\Controllers\CP;

use App\Redirects\MissingLog;
use App\Redirects\RedirectStore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The redirects utility's three moves: save the table, promote a 404 into a
 * row, forget a 404.
 *
 * Plain browser form posts, not Inertia calls — the screen is a Blade fragment
 * and every action ends in `back()`, so the Control Panel reloads the page the
 * way it would after any other navigation. Nothing here needs to be live.
 */
class RedirectsController extends Controller
{
    public function save(Request $request, RedirectStore $store)
    {
        $rows = $request->input('rows', []);

        $store->save(is_array($rows) ? array_values($rows) : []);

        return back()->with('success', 'Omdirigeringerne er gemt.');
    }

    /**
     * Turn a logged 404 into a row, switched off and pointing at the front
     * page. Off, because the editor still has to say where it should go —
     * a redirect nobody aimed is worse than the 404 it replaces.
     */
    public function promote(Request $request, RedirectStore $store, MissingLog $log)
    {
        $path = $store->normalise((string) $request->input('path', ''));

        if ($path === '' || $path === '/') {
            return back();
        }

        $rows = $store->all();
        $rows[] = ['from' => $path, 'to' => '/', 'status' => 301, 'enabled' => false];

        $store->save($rows);
        $log->forget($path);

        return back()->with('success', 'Linjen er lagt ind. Sæt målet, og slå den til.');
    }

    public function forget(Request $request, MissingLog $log)
    {
        if ($request->boolean('all')) {
            $log->clear();
        } else {
            $log->forget((string) $request->input('path', ''));
        }

        return back();
    }
}
