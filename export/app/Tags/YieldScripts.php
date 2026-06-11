<?php
namespace App\Tags;
use Statamic\Tags\Tags;

class YieldScripts extends Tags
{
    const PLACEHOLDER = '<!-- __SCRIPTS__ -->';
    protected static $handle = 'yield_scripts';

    public function index(): string
    {
        return self::PLACEHOLDER;
    }
}
