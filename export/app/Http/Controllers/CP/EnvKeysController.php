<?php

namespace App\Http\Controllers\CP;

use App\Env\EnvKeys;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Save one key. The value is never echoed back — the screen only ever learns
 * whether a key is set.
 */
class EnvKeysController extends Controller
{
    public function save(Request $request, EnvKeys $keys)
    {
        $key = (string) $request->input('key', '');
        $value = (string) $request->input('value', '');

        if (! $keys->allowed($key)) {
            return back()->withErrors(['key' => 'Ukendt nøgle.']);
        }

        if (! $keys->writable()) {
            return back()->withErrors(['key' => '.env kan ikke skrives af webserveren. Ret rettighederne, eller sæt nøglen i Ploi.']);
        }

        $keys->set($key, $value);

        return back()->with('success', $value === '' ? 'Nøglen er fjernet.' : 'Nøglen er gemt.');
    }
}
