<?php
namespace App\Tags;
use Statamic\Tags\Tags;

class YieldMinified extends Tags
{
    const PLACEHOLDER = '<!-- __STYLES__ -->';
    protected static $handle = 'yield_minified';

    public function index(): string
    {
        return self::PLACEHOLDER;
    }
}
