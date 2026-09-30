<?php

namespace App\Dashboard;

use Statamic\Contracts\Entries\Entry;

/**
 * Tilgængelighed læst i sidens indhold.
 *
 * Det her er ikke det samme som Tilgængeligheds-panelet i Live Preview, og det
 * skal det heller ikke være. Panelet læser den færdige side i browseren og kan
 * derfor måle kontrast, fokusrækkefølge og hvad en skærmlæser faktisk ville
 * sige. Det kræver en browser pr. side.
 *
 * Her læses det samme indhold som SEO-widgeten allerede læser — overskrifter,
 * billeder og links fra sidens felter. Det fanger de fejl der bor i indholdet
 * og som redaktøren selv kan rette, og det koster ingenting. De fejl der bor i
 * skabelonen og i farverne, står stadig kun i panelet.
 */
class AccessibilityScanner
{
    /** Hver check giver 0, 1 eller 2. Fem checks = 10 mulige point. */
    protected const MAX_POINTS = 2;

    public function for(Entry $entry): array
    {
        $content = (new PageContentReader)->read($entry->data()->all());

        $checks = [
            $this->headingCheck($content->headings),
            $this->headingOrderCheck($content->headings),
            $this->altCheck($content->imagePaths),
            $this->altQualityCheck($content->imagePaths),
            $this->linkCheck($content->links),
        ];

        $points = collect($checks)->sum('points');
        $max = count($checks) * static::MAX_POINTS;
        $fails = collect($checks)->where('level', 'bad');
        $warns = collect($checks)->where('level', 'ok');

        $status = 'good';

        if ($fails->isNotEmpty()) {
            $status = 'bad';
        } elseif ($warns->isNotEmpty()) {
            $status = 'ok';
        }

        $issues = collect($checks)->reject(fn (array $check) => $check['level'] === 'good');

        return [
            'id' => $entry->id(),
            'title' => (string) ($entry->get('title') ?: $entry->slug() ?: $entry->id()),
            'edit_url' => $entry->editUrl(),
            'status' => $status,
            'score' => $max > 0 ? (int) round(($points / $max) * 100) : 0,
            'hint' => $issues->isEmpty()
                ? 'Ingen fejl i indholdet'
                : $issues->count().' ting at rette',
            'checks' => collect($checks)
                ->map(fn (array $check) => ['level' => $check['level'], 'hint' => $check['hint']])
                ->values()
                ->all(),
        ];
    }

    /** Præcis én H1. Ingen H1 er en side uden overskrift for en skærmlæser; flere er en side uden hierarki. */
    protected function headingCheck(array $headings): array
    {
        $h1s = collect($headings)->where('level', 1)->count();

        if ($h1s === 0) {
            return $this->check('bad', 0, 'Siden har ingen H1 — skærmlæsere mangler sidens overskrift');
        }

        if ($h1s > 1) {
            return $this->check('bad', 0, $h1s.' H1-overskrifter — der må kun være én');
        }

        return $this->check('good', 2, 'Én H1 på siden');
    }

    protected function headingOrderCheck(array $headings): array
    {
        if ($headings === []) {
            return $this->check('ok', 1, 'Ingen overskrifter at navigere efter');
        }

        $previous = 0;

        foreach ($headings as $heading) {
            $level = (int) $heading['level'];

            if ($previous && $level > $previous + 1) {
                return $this->check('ok', 1, "Springer fra H{$previous} til H{$level} — niveauer må ikke springes over");
            }

            $previous = $level;
        }

        return $this->check('good', 2, 'Overskrifterne følger hinanden');
    }

    protected function altCheck(array $imagePaths): array
    {
        if ($imagePaths === []) {
            return $this->check('good', 2, 'Ingen indholdsbilleder');
        }

        $missing = collect($imagePaths)->filter(fn (string $path) => $this->alt($path) === '')->count();

        if ($missing === 0) {
            return $this->check('good', 2, 'Alle billeder har alt-tekst');
        }

        if ($missing === count($imagePaths)) {
            return $this->check('bad', 0, $missing === 1 ? 'Billedet mangler alt-tekst' : 'Ingen af de '.$missing.' billeder har alt-tekst');
        }

        return $this->check('ok', 1, $missing.' af '.count($imagePaths).' billeder mangler alt-tekst');
    }

    /**
     * Alt-tekst der bare er filnavnet.
     *
     * Et felt der er udfyldt, tæller som udfyldt i enhver tælling — men
     * "dsc-4821-final-2.jpg" læst højt er værre end ingenting, fordi den
     * første check så siger god.
     */
    protected function altQualityCheck(array $imagePaths): array
    {
        if ($imagePaths === []) {
            return $this->check('good', 2, 'Ingen billeder at beskrive');
        }

        $filenames = collect($imagePaths)->filter(function (string $path) {
            $alt = $this->alt($path);

            if ($alt === '') {
                return false;
            }

            return $this->looksLikeFilename($alt);
        })->count();

        if ($filenames > 0) {
            return $this->check('ok', 1, $filenames === 1
                ? 'Ét billedes alt-tekst er bare filnavnet'
                : $filenames.' billeders alt-tekst er bare filnavnet');
        }

        return $this->check('good', 2, 'Alt-teksterne beskriver billedet');
    }

    protected function linkCheck(array $links): array
    {
        $empty = collect($links)->filter(fn (string $url) => $url === '' || $url === '#')->count();

        if ($empty > 0) {
            return $this->check('ok', 1, $empty === 1
                ? 'Et link går ingen steder'
                : $empty.' links går ingen steder');
        }

        return $this->check('good', 2, 'Alle links har en destination');
    }

    protected function alt(string $path): string
    {
        return AssetLookup::alt($path);
    }

    protected function looksLikeFilename(string $alt): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg|avif)$/i', trim($alt))
            || (bool) preg_match('/^(img|dsc|image|photo|screenshot)[\s_-]*\d+$/i', trim($alt));
    }

    protected function check(string $level, int $points, string $hint): array
    {
        return compact('level', 'points', 'hint');
    }
}
