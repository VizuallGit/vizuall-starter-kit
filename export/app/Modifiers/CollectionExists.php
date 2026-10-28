<?php

namespace App\Modifiers;

use Statamic\Facades\Collection;
use Statamic\Modifiers\Modifier;

/**
 * `{{ handle | collection_exists }}` — true when a collection with that handle
 * still exists.
 *
 * Page sections (collection_list, event_list, employees_list_2) store a
 * collection handle that can later be deleted or renamed. Statamic's
 * `collection` tag throws a 500 on an unknown handle, so sections check this
 * first and fall back to a message instead of crashing the page.
 */
class CollectionExists extends Modifier
{
    public function index($value, $params, $context)
    {
        $handle = is_array($value) ? ($value[0] ?? null) : $value;

        if (! $handle) {
            return false;
        }

        return Collection::find($handle) !== null;
    }
}
