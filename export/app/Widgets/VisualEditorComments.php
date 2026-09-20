<?php

namespace App\Widgets;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\YAML;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class VisualEditorComments extends Widget
{
    public function component()
    {
        return VueComponent::render('VisualEditorComments', [
            'pages' => $this->pages(),
            'csrf' => csrf_token(),
        ]);
    }

    protected function pages(): array
    {
        $dir = storage_path('statamic-visual-editor/comments');

        if (! File::isDirectory($dir)) {
            return [];
        }

        $grouped = [];

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'yaml') {
                continue;
            }

            $entryId = $file->getFilenameWithoutExtension();
            $entry = Entry::find($entryId);
            $parsed = YAML::file($file->getPathname())->parse() ?: [];
            $threads = $parsed['comments'] ?? [];

            if (! is_array($threads) || $threads === []) {
                continue;
            }

            $comments = [];

            foreach ($threads as $thread) {
                if (! is_array($thread)) {
                    continue;
                }

                $messages = array_values($thread['messages'] ?? []);
                $first = $messages[0] ?? [];
                $created = $thread['created_at'] ?? $first['created_at'] ?? null;
                $resolved = (bool) ($thread['resolved'] ?? false);

                $comments[] = [
                    'id' => $thread['id'] ?? Str::uuid()->toString(),
                    'section' => $this->sectionLabel($entry, (string) ($thread['visual_id'] ?? '__page')),
                    'body' => trim((string) ($first['body'] ?? '')),
                    'author' => $first['author_name'] ?? '',
                    'resolved' => $resolved,
                    'created_at' => $created,
                    'messages' => collect($messages)
                        ->map(fn (array $message) => [
                            'id' => $message['id'] ?? '',
                            'author' => $message['author_name'] ?? '',
                            'body' => trim((string) ($message['body'] ?? '')),
                            'created_at' => $message['created_at'] ?? null,
                        ])
                        ->all(),
                    'sort' => $this->sortKey($resolved, $created),
                ];
            }

            if ($comments === []) {
                continue;
            }

            $grouped[] = [
                'id' => $entryId,
                'title' => $entry?->get('title') ?: $entry?->slug() ?: 'Ukendt side',
                'comments' => collect($comments)
                    ->sortBy('sort')
                    ->values()
                    ->map(fn (array $row) => collect($row)->except('sort')->all())
                    ->all(),
            ];
        }

        return collect($grouped)
            ->sortBy(fn (array $page) => mb_strtolower($page['title']))
            ->values()
            ->all();
    }

    protected function sectionLabel($entry, string $visualId): string
    {
        if ($visualId === '' || $visualId === '__page') {
            return 'Hele siden';
        }

        $node = $entry ? $this->findByVisualId($entry->data()->all(), $visualId) : null;

        if (! $node) {
            foreach (GlobalSet::all() as $set) {
                $localized = $set->inCurrentSite() ?? $set->inDefaultSite();

                if (! $localized) {
                    continue;
                }

                $node = $this->findByVisualId($localized->data()->all(), $visualId);

                if ($node) {
                    break;
                }
            }
        }

        if (! $node) {
            return 'Sektion';
        }

        $custom = trim((string) ($node['_sve_label'] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        $title = trim((string) ($node['title'] ?? ''));

        if ($title !== '') {
            return $title;
        }

        return $this->humanizeType($node['type'] ?? null);
    }

    protected function findByVisualId(mixed $data, string $visualId): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        if (($data['_visual_id'] ?? null) === $visualId || (string) ($data['id'] ?? '') === $visualId) {
            return $data;
        }

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }

            $found = $this->findByVisualId($value, $visualId);

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function humanizeType(?string $type): string
    {
        if (! $type) {
            return 'Sektion';
        }

        return collect(explode('/', $type))
            ->map(fn (string $part) => Str::headline(str_replace('_', ' ', $part)))
            ->filter()
            ->implode(' ') ?: 'Sektion';
    }

    protected function sortKey(bool $resolved, mixed $created): string
    {
        $time = '0000';

        try {
            if ($created) {
                $time = Carbon::parse($created)->format('YmdHis');
            }
        } catch (\Throwable) {
            //
        }

        return ($resolved ? '1' : '0').'-'.str_pad((string) (99999999999999 - (int) $time), 14, '0', STR_PAD_LEFT);
    }
}
