<?php

namespace App\Fieldtypes;

use Statamic\Facades\GlobalSet;
use Statamic\Fields\Fieldtype;

class FluidFontPreview extends Fieldtype
{
    protected static $handle = 'fluid_font_preview';

    public function component(): string
    {
        return 'fluid-font-preview';
    }

    public function preload(): array
    {
        $global = GlobalSet::find('theme_settings');
        $data   = $global?->in('default')?->data();
        $rows   = $data?->get('custom_fonts', []) ?? [];

        $fonts = collect($rows)
            ->filter(fn ($r) => ! empty($r['file']) && ! str_starts_with((string) $r['file'], '{'))
            ->map(fn ($r) => [
                'file'     => (string) $r['file'],
                'variable' => (bool) ($r['variable'] ?? true),
                'weight'   => (string) ($r['weight'] ?? '400'),
            ])
            ->values()
            ->all();

        return ['customFonts' => $fonts];
    }

    public function preProcess($value): array
    {
        if (is_array($value)) return $value;
        return [];
    }

    public function process($value): array
    {
        if (!is_array($value)) return [];
        return $value;
    }
}
