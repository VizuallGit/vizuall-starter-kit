<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Tags;
use Symfony\Component\Yaml\Yaml;

class SectionYaml extends Tags
{
    public static $handle = 'section_yaml';

    public function index(): string
    {
        $sectionId = (string) $this->context->get('id');
        if (!$sectionId) return '';

        $uri = '/' . ltrim(request()->path(), '/');
        $entry = Entry::findByUri($uri);
        if (!$entry) return '';

        $path = $entry->path();
        if (!file_exists($path)) return '';

        $contents = file_get_contents($path);
        if (!preg_match('/^---\n(.+?)\n---/s', $contents, $matches)) return '';

        $data = Yaml::parse($matches[1]);
        $sections = $data['page_sections'] ?? [];

        $section = collect($sections)->first(fn($s) => ($s['id'] ?? '') === $sectionId);
        if (!$section) return '';

        return htmlspecialchars(Yaml::dump($section, 6, 2), ENT_QUOTES, 'UTF-8');
    }
}
