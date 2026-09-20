<?php

namespace App\Dashboard;

use Statamic\Contracts\Entries\Entry;

class SeoScorer
{
    public function for(Entry $entry): array
    {
        $content = (new PageContentReader)->read($entry->data()->all());

        $title = trim((string) $entry->get('meta_title'));
        $description = trim((string) $entry->get('meta_description'));
        $image = $entry->get('meta_image');
        $hasImage = is_array($image) ? $image !== [] : filled($image);

        $checks = [
            $this->h1Check($content->headings),
            $this->headingOrderCheck($content->headings),
            $this->duplicateHeadingCheck($content->headings),
            $this->wordCountCheck($content->words),
            $this->placeholderCheck($content->hasPlaceholder),
            $this->linkCheck($content->links),
            $this->altCheck($content->imagePaths),
            $this->titleCheck($title),
            $this->descriptionCheck($description),
            $this->imageCheck($hasImage),
        ];

        $points = collect($checks)->sum('points');
        $max = count($checks) * 2;
        $fails = collect($checks)->where('level', 'bad');
        $warns = collect($checks)->where('level', 'ok');

        $status = 'good';

        if ($fails->isNotEmpty()) {
            $status = 'bad';
        } elseif ($warns->isNotEmpty()) {
            $status = 'ok';
        }

        $issues = collect($checks)
            ->reject(fn (array $check) => $check['level'] === 'good')
            ->pluck('hint')
            ->values()
            ->all();

        return [
            'id' => $entry->id(),
            'title' => $entry->get('title') ?: $entry->slug() ?: $entry->id(),
            'edit_url' => $entry->editUrl(),
            'status' => $status,
            'score' => $max > 0 ? (int) round(($points / $max) * 100) : 0,
            'hint' => $issues === [] ? 'Klar' : count($issues).' ting at se på',
            'issues' => $issues,
            'checks' => collect($checks)
                ->map(fn (array $check) => [
                    'level' => $check['level'],
                    'hint' => $check['hint'],
                ])
                ->values()
                ->all(),
            'words' => $content->words,
        ];
    }

    protected function h1Check(array $headings): array
    {
        $h1s = collect($headings)->where('level', 1)->count();

        if ($h1s === 0) {
            return $this->check('bad', 0, 'Ingen H1');
        }

        if ($h1s > 1) {
            return $this->check('bad', 0, $h1s.' H1-overskrifter — der bør kun være én');
        }

        return $this->check('good', 2, 'Én H1');
    }

    protected function headingOrderCheck(array $headings): array
    {
        if ($headings === []) {
            return $this->check('ok', 1, 'Ingen overskrifter på siden');
        }

        $firstH1 = collect($headings)->search(fn (array $heading) => $heading['level'] === 1);

        if ($firstH1 === false) {
            return $this->check('good', 2, 'Overskriftsrækkefølge');
        }

        if ($firstH1 > 0) {
            return $this->check('ok', 1, 'Overskrift står før sidens H1');
        }

        $previous = 0;

        foreach ($headings as $heading) {
            $level = (int) $heading['level'];

            if ($previous && $level > $previous + 1) {
                return $this->check('ok', 1, "Springer fra H{$previous} til H{$level}");
            }

            $previous = $level;
        }

        return $this->check('good', 2, 'Overskriftsrækkefølge');
    }

    protected function duplicateHeadingCheck(array $headings): array
    {
        $texts = collect($headings)
            ->map(fn (array $heading) => mb_strtolower(trim($heading['text'])))
            ->filter(fn (string $text) => mb_strlen($text) > 3);

        $duplicate = $texts->countBy()->filter(fn (int $count) => $count > 1)->keys()->first();

        if ($duplicate) {
            return $this->check('ok', 1, 'Samme overskrift gentages');
        }

        return $this->check('good', 2, 'Unikke overskrifter');
    }

    protected function wordCountCheck(int $words): array
    {
        if ($words < 150) {
            return $this->check('bad', 0, 'Kun '.$words.' ord på siden');
        }

        if ($words < 300) {
            return $this->check('ok', 1, $words.' ord — lidt tyndt');
        }

        return $this->check('good', 2, $words.' ord');
    }

    protected function placeholderCheck(bool $hasPlaceholder): array
    {
        if ($hasPlaceholder) {
            return $this->check('ok', 1, 'Placeholder-tekst på siden');
        }

        return $this->check('good', 2, 'Ingen placeholder-tekst');
    }

    protected function linkCheck(array $links): array
    {
        $empty = collect($links)->filter(fn (string $url) => $url === '' || $url === '#')->count();

        if ($empty > 0) {
            return $this->check('ok', 1, 'Link uden destination');
        }

        $duplicate = collect($links)
            ->filter()
            ->countBy()
            ->filter(fn (int $count) => $count >= 3)
            ->keys()
            ->first();

        if ($duplicate) {
            return $this->check('ok', 1, 'Samme link gentages');
        }

        return $this->check('good', 2, 'Links ser fine ud');
    }

    protected function altCheck(array $imagePaths): array
    {
        if ($imagePaths === []) {
            return $this->check('good', 2, 'Ingen indholdsbilleder');
        }

        $missing = 0;

        foreach ($imagePaths as $path) {
            $asset = $this->findAsset($path);
            $alt = trim((string) ($asset?->get('alt') ?? ''));

            if ($alt === '') {
                $missing++;
            }
        }

        if ($missing === count($imagePaths)) {
            return $this->check('bad', 0, $missing === 1 ? 'Billede uden alt-tekst' : $missing.' billeder uden alt-tekst');
        }

        if ($missing > 0) {
            return $this->check('ok', 1, $missing.' billeder uden alt-tekst');
        }

        return $this->check('good', 2, 'Alle billeder har alt-tekst');
    }

    protected function findAsset(string $path): mixed
    {
        $path = ltrim($path, '/');

        foreach ([$path, 'assets::'.$path] as $id) {
            try {
                $asset = \Statamic\Facades\Asset::find($id);

                if ($asset) {
                    return $asset;
                }
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    protected function titleCheck(string $title): array
    {
        $len = mb_strlen($title);

        if ($len === 0) {
            return $this->check('bad', 0, 'Mangler meta-titel');
        }

        if ($len < 20) {
            return $this->check('ok', 1, 'Meta-titel er kort');
        }

        return $this->check('good', 2, 'Meta-titel er udfyldt');
    }

    protected function descriptionCheck(string $description): array
    {
        $len = mb_strlen($description);

        if ($len === 0) {
            return $this->check('bad', 0, 'Mangler metabeskrivelse');
        }

        if ($len < 70) {
            return $this->check('ok', 1, 'Metabeskrivelse er kort');
        }

        return $this->check('good', 2, 'Metabeskrivelse er udfyldt');
    }

    protected function imageCheck(bool $hasImage): array
    {
        if (! $hasImage) {
            return $this->check('ok', 1, 'Mangler SEO-billede');
        }

        return $this->check('good', 2, 'SEO-billede er sat');
    }

    protected function check(string $level, int $points, string $hint): array
    {
        return compact('level', 'points', 'hint');
    }
}
