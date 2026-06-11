<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class WithTypeIndex extends Modifier
{
    protected static $handle = 'with_type_index';

    public function index($value, $params, $context): array
    {
        $items = collect($value);

        $typeCounts = $items->countBy(fn ($item) => $item['type'] ?? '');

        $typeIndexes = [];

        return $items->map(function ($item) use ($typeCounts, &$typeIndexes) {
            $data = is_array($item) ? $item : $item->all();
            $type = $data['type'] ?? null;
            if (! $type) return $data;

            $typeIndexes[$type] = ($typeIndexes[$type] ?? 0) + 1;

            $data['type_index'] = $typeIndexes[$type];
            $data['type_count'] = $typeCounts[$type];

            return $data;
        })->all();
    }
}
