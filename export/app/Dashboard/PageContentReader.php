<?php

namespace App\Dashboard;

class PageContentReader
{
    /** @var array<int, array{level: int, text: string}> */
    public array $headings = [];

    /** @var array<int, string> */
    public array $links = [];

    /** @var array<int, string> */
    public array $imagePaths = [];

    public int $words = 0;

    public bool $hasPlaceholder = false;

    public function read(array $data): self
    {
        $this->headings = [];
        $this->links = [];
        $this->imagePaths = [];
        $this->words = 0;
        $this->hasPlaceholder = false;

        $this->walk($data['page_sections'] ?? $data['blocks'] ?? []);
        $this->imagePaths = array_values(array_unique($this->imagePaths));

        return $this;
    }

    protected function walk(mixed $node): void
    {
        if (! is_array($node)) {
            return;
        }

        if (array_key_exists('enabled', $node) && $node['enabled'] === false) {
            return;
        }

        $type = $node['type'] ?? null;

        if ($type === 'headline' && array_key_exists('headline', $node)) {
            $tag = strtolower((string) ($node['tag'] ?? 'h2'));
            $level = (int) preg_replace('/\D+/', '', $tag) ?: 2;
            $text = $this->bardText($node['headline']);
            $this->headings[] = ['level' => $level, 'text' => $text];
            $this->walkBard($node['headline']);

            foreach ($node as $key => $value) {
                if (in_array($key, ['headline', 'type', 'tag', 'enabled', 'id', '_visual_id', '_id'], true)) {
                    continue;
                }

                $this->walk($value);
            }

            return;
        }

        if ($type === 'heading' && isset($node['attrs']['level'])) {
            $text = $this->bardText($node['content'] ?? []);
            $this->headings[] = ['level' => (int) $node['attrs']['level'], 'text' => $text];
            $this->walkBard($node['content'] ?? []);

            return;
        }

        if ($type === 'text' && isset($node['text']) && is_string($node['text'])) {
            $this->addText($node['text']);
            $this->collectMarks($node['marks'] ?? []);

            return;
        }

        if (isset($node['text']) && (
            array_key_exists('url', $node)
            || array_key_exists('link', $node)
            || array_key_exists('outline', $node)
        )) {
            $this->addLink($node['url'] ?? $node['link'] ?? '');
        } elseif (isset($node['url'])) {
            $this->addLink($node['url']);
        } elseif (isset($node['link'])) {
            $this->addLink($node['link']);
        }

        foreach ($node as $key => $value) {
            if (in_array($key, ['image', 'images', 'media', 'photo', 'src'], true)) {
                $this->collectImages($value);
            }

            $this->walk($value);
        }
    }

    protected function walkBard(mixed $nodes): void
    {
        if (! is_array($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = $node['type'] ?? null;

            if ($type === 'heading' && isset($node['attrs']['level'])) {
                $text = $this->bardText($node['content'] ?? []);
                $this->headings[] = ['level' => (int) $node['attrs']['level'], 'text' => $text];
                $this->walkBard($node['content'] ?? []);

                continue;
            }

            if ($type === 'text' && isset($node['text']) && is_string($node['text'])) {
                $this->addText($node['text']);
                $this->collectMarks($node['marks'] ?? []);

                continue;
            }

            if (isset($node['content'])) {
                $this->walkBard($node['content']);
            }

            if (($type === 'image' || $type === 'imgs') && isset($node['attrs']['src'])) {
                $this->collectImages($node['attrs']['src']);
            }
        }
    }

    protected function bardText(mixed $nodes): string
    {
        if (! is_array($nodes)) {
            return is_string($nodes) ? trim($nodes) : '';
        }

        $parts = [];

        $this->collectText($nodes, $parts);

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    protected function collectText(mixed $nodes, array &$parts): void
    {
        if (! is_array($nodes)) {
            return;
        }

        if (isset($nodes['text']) && is_string($nodes['text'])) {
            $parts[] = $nodes['text'];
        }

        foreach ($nodes as $value) {
            if (is_array($value)) {
                $this->collectText($value, $parts);
            }
        }
    }

    protected function collectMarks(array $marks): void
    {
        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                continue;
            }

            if (($mark['type'] ?? '') === 'link') {
                $this->addLink($mark['attrs']['href'] ?? $mark['attrs']['url'] ?? null);
            }
        }
    }

    protected function addLink(mixed $value): void
    {
        if (is_array($value)) {
            $value = $value['url'] ?? $value['href'] ?? null;
        }

        if (! is_string($value)) {
            return;
        }

        $this->links[] = trim($value);
    }

    protected function collectImages(mixed $value): void
    {
        if (is_string($value) && $this->looksLikeImage($value)) {
            $this->imagePaths[] = $value;

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            if (is_string($item) && $this->looksLikeImage($item)) {
                $this->imagePaths[] = $item;
            }
        }
    }

    protected function looksLikeImage(string $value): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg|avif)(\?.*)?$/i', $value);
    }

    protected function addText(string $text): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return;
        }

        if (preg_match('/^(indtast din|lorem ipsum|placeholder)/iu', $text)) {
            $this->hasPlaceholder = true;

            return;
        }

        preg_match_all('/\p{L}+/u', $text, $matches);
        $this->words += count($matches[0] ?? []);
    }
}
