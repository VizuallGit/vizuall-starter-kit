<?php
namespace App\Tags;
use Statamic\Facades\Antlers;
use Statamic\Tags\Tags;

class RenderAntlers extends Tags
{
    protected static $handle = 'render_antlers';

    public function index(): string
    {
        $code = $this->params->get('code', '');
        if (empty(trim($code))) return '';

        return (string) Antlers::parse($code, $this->context->all(), true);
    }
}
