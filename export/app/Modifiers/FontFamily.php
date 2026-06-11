<?php

namespace App\Modifiers;

use App\Fieldtypes\FontFamilySelector;
use Statamic\Modifiers\Modifier;

class FontFamily extends Modifier
{
    protected static $handle = 'font_family';

    public function index($value, $params, $context): string
    {
        $stem = pathinfo((string) $value, PATHINFO_FILENAME);
        return FontFamilySelector::extractFamily($stem);
    }
}
