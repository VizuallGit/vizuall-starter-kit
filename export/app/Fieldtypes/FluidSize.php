<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

class FluidSize extends Fieldtype
{
    protected static $handle = 'fluid_size';

    public function component(): string
    {
        return 'fluid-size';
    }

    public function preload(): array
    {
        $cw = '75em';
        try {
            $parent = $this->field->parent();
            if ($parent && method_exists($parent, 'get')) {
                $cw = (string) ($parent->get('container_width') ?? '75em');
            }
        } catch (\Throwable $e) {}
        return ['container_width' => $cw];
    }

    public function preProcess($value): array
    {
        if (is_array($value) && (isset($value['sizes']) || isset($value['unit']))) return $value;
        return ['max_viewport' => 1200, 'unit' => 'vw', 'sizes' => []];
    }

    public function process($value): array
    {
        if (!is_array($value) || (!isset($value['sizes']) && !isset($value['unit']))) {
            $value = ['max_viewport' => 1200, 'unit' => 'vw', 'sizes' => []];
        }
        $value['sizes_css'] = $this->computeCss($value);
        return $value;
    }

    private function computeCss(array $data): array
    {
        $minVP = 320;
        $maxVP = (float) ($data['max_viewport'] ?? 1200);
        $unit  = $data['unit'] ?? 'vw';
        $sizes = $data['sizes'] ?? [];
        $range = $maxVP - $minVP;
        $css   = [];

        foreach ($sizes as $size) {
            $handle = trim($size['handle'] ?? '');
            if (!$handle) continue;

            $minPx  = (float) ($size['min'] ?? 16);
            $maxPx  = (float) ($size['max'] ?? 16);
            $minRem = round($minPx / 16, 4);
            $maxRem = round($maxPx / 16, 4);

            if ($range > 0 && abs($maxPx - $minPx) > 0.001) {
                $slope       = ($maxPx - $minPx) / $range;
                $interceptPx = $minPx - $slope * $minVP;
                $slopeFluid  = round($slope * 100, 4);
                $intRem      = round($interceptPx / 16, 4);
                $preferred   = "{$intRem}rem + {$slopeFluid}{$unit}";
            } else {
                $preferred = "{$minRem}rem";
            }

            $css[] = [
                'handle' => $handle,
                'value'  => "clamp({$minRem}rem, {$preferred}, {$maxRem}rem)",
            ];
        }

        return $css;
    }
}
