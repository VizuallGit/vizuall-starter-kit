<?php
namespace App\Tags;
use Statamic\Tags\Tags;

class ScriptPush extends Tags
{
    protected static $handle = 'script_push';
    protected static array $stack = [];
    protected static array $seen = [];

    public function index(): string
    {
        $js = $this->parse();
        $hash = md5($js);
        if (!in_array($hash, static::$seen)) {
            static::$seen[] = $hash;
            static::$stack[] = $js;
        }
        return '';
    }

    public static function getAll(): string
    {
        return implode('', static::$stack);
    }
}
