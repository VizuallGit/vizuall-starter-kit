<?php

namespace App\Tags;

use Statamic\Tags\Tags;

class FileCode extends Tags
{
    public static $handle = 'file_code';

    public function index(): string
    {
        $type = (string) $this->params->get('type');
        if (!$type) return '';

        $type = str_replace(['..', '\\', "\0"], '', $type);

        $path = resource_path('views/partials/page_sections/' . $type . '.antlers.html');
        if (!file_exists($path)) return '<!-- Fil ikke fundet: ' . htmlspecialchars($type) . ' -->';

        return htmlspecialchars(file_get_contents($path), ENT_QUOTES, 'UTF-8');
    }
}
