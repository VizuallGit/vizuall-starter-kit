<?php

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

class FontFamilySelector extends Fieldtype
{
    protected static $handle = 'font_family_selector';

    public function component(): string
    {
        return 'font-family-selector';
    }

    public function preload(): array
    {
        return ['fonts' => static::scanFamilies()];
    }

    public static function scanFamilies(): array
    {
        $dir = public_path('fonts');
        if (!is_dir($dir)) return [];

        $files = glob($dir . '/*.{woff2,woff,ttf,otf}', GLOB_BRACE) ?: [];
        $families = [];

        foreach ($files as $file) {
            $stem = pathinfo($file, PATHINFO_FILENAME);
            $family = static::extractFamily($stem);
            if ($family) {
                $families[$family] = true;
            }
        }

        ksort($families);
        return array_keys($families);
    }

    public static function extractFamily(string $stem): string
    {
        if (preg_match('/icon|symbol|awesome|material/i', $stem)) {
            return '';
        }

        $suffixes = [
            'VariableFont', 'Variable',
            'ExtraLight', 'UltraLight', 'ExtraBold', 'UltraBold', 'SemiBold', 'DemiBold',
            'Thin', 'Light', 'Regular', 'Normal', 'Medium', 'Bold', 'Black', 'Heavy',
            'Italic', 'Oblique', 'Condensed', 'Expanded', 'Narrow',
            'wght', 'ital',
        ];

        $pattern = '/[-_ ](' . implode('|', $suffixes) . ').*$/i';
        $family = preg_replace($pattern, '', $stem);
        $family = preg_replace('/[-_]?[1-9]00$/', '', $family);

        return trim($family);
    }
}
