<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

class FluidFontSize extends Fieldtype
{
    protected static $handle = 'fluid_font_size';

    public function component(): string
    {
        return 'fluid-font-size';
    }

    public function preProcess($value): array
    {
        if (is_array($value) && isset($value['global'])) return $value;
        return ['global' => ['min' => 1, 'pref' => 5, 'unit' => 'cqi', 'max' => 3], 'overrides' => []];
    }

    public function process($value): array
    {
        if (!is_array($value) || !isset($value['global'])) {
            $value = ['global' => ['min' => 1, 'pref' => 5, 'unit' => 'cqi', 'max' => 3], 'overrides' => []];
        }
        $value['css'] = $this->computeCss($value);
        return $value;
    }

    private function computeCss(array $data): array
    {
        $g    = $data['global'];
        $mn   = min((float) $g['min'], (float) $g['max']);
        $mx   = max((float) $g['min'], (float) $g['max']);
        $pf   = (float) $g['pref'];
        $unit = $g['unit'] ?? 'cqi';
        $ovr  = $data['overrides'] ?? [];

        $levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];
        $css    = [];

        foreach ($levels as $i => $level) {
            if (!empty($ovr[$level])) {
                $o = $ovr[$level];
                $css[$level] = "clamp({$o['min']}rem, {$o['pref']}{$o['unit']}, {$o['max']}rem)";
                continue;
            }

            if ($level === 'p') {
                $pMax  = $mn;
                $pMin  = round($mn * 0.75, 2);
                $pPref = round($pf * ($mx > 0 ? $mn / $mx : 1), 1);
                $css[$level] = "clamp({$pMin}rem, {$pPref}{$unit}, {$pMax}rem)";
                continue;
            }

            $h6Max = min($mx, round($mn * 1.25, 2));
            $t     = $i / 5;
            $sMax  = round($mx - $t * ($mx - $h6Max), 2);
            $fac   = $mx > 0 ? $sMax / $mx : 1;
            $sPref = round($pf * $fac, 1);
            $css[$level] = "clamp({$mn}rem, {$sPref}{$unit}, {$sMax}rem)";
        }

        return $css;
    }
}
