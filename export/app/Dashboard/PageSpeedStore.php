<?php

namespace App\Dashboard;

use Illuminate\Support\Facades\File;

/**
 * Sidst kendte PageSpeed-tal pr. side.
 *
 * Målingen selv hører til Visual Editors ydelses-panel, som spørger Google.
 * Den tager op til to minutter for én side og kan ikke laves om til noget et
 * dashboard venter på — så dashboardet måler ikke, det husker. Redaktøren
 * trykker "Mål", svaret lander her, og alle senere besøg læser det herfra,
 * med datoen synlig så ingen tror et tal fra i sidste uge er fra i dag.
 */
class PageSpeedStore
{
    /** Hvor mange lab-tal der gemmes pr. side. Resten er panelets arbejde, ikke dashboardets. */
    public const LAB_LIMIT = 3;

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return [];
        }

        $parsed = json_decode(File::get($path), true);

        return is_array($parsed['pages'] ?? null) ? $parsed['pages'] : [];
    }

    public function for(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    public function put(string $id, array $row): void
    {
        $pages = $this->all();
        $pages[$id] = $row;

        $this->write($pages);
    }

    /**
     * Sitets tal: gennemsnittet af de sider der er målt.
     *
     * Umålte sider tæller ikke med som nul — et site hvor halvdelen ikke er
     * målt, er ikke et site der er halvt så hurtigt.
     */
    public function average(): ?int
    {
        $scores = collect($this->all())
            ->pluck('score')
            ->filter(fn ($score) => is_numeric($score));

        return $scores->isEmpty() ? null : (int) round($scores->avg());
    }

    /** Ryd tal for sider der ikke findes længere. */
    public function keepOnly(array $ids): void
    {
        $pages = collect($this->all())->only($ids)->all();

        $this->write($pages);
    }

    protected function write(array $pages): void
    {
        $dir = dirname($this->path());

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->path(), json_encode(['pages' => $pages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function path(): string
    {
        return storage_path('app/dashboard/pagespeed.json');
    }
}
