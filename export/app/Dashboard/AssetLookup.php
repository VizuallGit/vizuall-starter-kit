<?php

namespace App\Dashboard;

use Statamic\Facades\Asset;

/**
 * Stien i et felt slået op som et asset.
 *
 * To widgets spørger om det samme: har billedet en alt-tekst. Fandt de hver
 * sin vej til assetet, ville de kunne svare forskelligt på samme billede — og
 * det er præcis den slags to tal på én skærm, der koster tillid.
 */
class AssetLookup
{
    public static function find(string $path): mixed
    {
        $path = ltrim($path, '/');

        foreach ([$path, 'assets::'.$path] as $id) {
            try {
                if ($asset = Asset::find($id)) {
                    return $asset;
                }
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    public static function alt(string $path): string
    {
        return trim((string) (static::find($path)?->get('alt') ?? ''));
    }
}
