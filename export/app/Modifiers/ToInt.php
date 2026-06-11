<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class ToInt extends Modifier
{
    protected static $handle = 'to_int';

    public function index($value, $params, $context): int
    {
        return (int) (string) $value;
    }
}
