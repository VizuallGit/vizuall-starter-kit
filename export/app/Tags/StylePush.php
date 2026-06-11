<?php
namespace App\Tags;
use Statamic\Tags\Tags;

class StylePush extends Tags
{
    protected static $handle = 'style_push';
    protected static array $stack = [];
    protected static array $seen = [];

    public function index()
    {
        $css = $this->parse();
        $hash = md5($css);
        if (!in_array($hash, static::$seen)) {
            static::$seen[] = $hash;
            static::$stack[] = $css;
        }
        return '';
    }

    public static function getAll(): string
    {
        return implode('', static::$stack);
    }
}
