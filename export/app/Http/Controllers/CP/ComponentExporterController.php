<?php

namespace App\Http\Controllers\CP;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Statamic\Facades\YAML;
use ZipArchive;

class ComponentExporterController
{
    public function items()
    {
        return response()->json([
            'page_sections' => $this->getPageSections(),
            'blueprints'    => $this->getBlueprints(),
            'collections'   => $this->getCollections(),
        ]);
    }

    public function export(Request $request)
    {
        $selected = $request->input('selected', []);
        $files    = [];
        $visited  = [];

        foreach ($selected['page_sections'] ?? [] as $handle) {
            $fsHandle = $this->sectionToFieldset($handle);
            $fsPath   = resource_path("fieldsets/{$fsHandle}.yaml");
            if (File::exists($fsPath)) {
                $files["resources/fieldsets/{$fsHandle}.yaml"] = $fsPath;
                $this->collectDeps($fsHandle, $files, $visited);
            }
        }

        foreach ($selected['blueprints'] ?? [] as $path) {
            $abs = base_path($path);
            if (File::exists($abs)) {
                $files[$path] = $abs;
            }
        }

        foreach ($selected['collections'] ?? [] as $handle) {
            $path = "content/collections/{$handle}.yaml";
            $abs  = base_path($path);
            if (File::exists($abs)) {
                $files[$path] = $abs;
            }
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'statamic_export_') . '.zip';
        $zip     = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $zipPath => $diskPath) {
            $zip->addFile($diskPath, $zipPath);
        }

        if (!empty($selected['page_sections'])) {
            $zip->addFromString(
                'resources/fieldsets/page_sections.yaml',
                $this->buildFilteredPageSections($selected['page_sections'])
            );
        }

        $zip->close();

        return response()->download($tmpPath, 'components-export.zip')->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $request->validate(['zip' => 'required|file']);

        $zip = new ZipArchive();
        if ($zip->open($request->file('zip')->getPathname()) !== true) {
            return response()->json(['error' => 'Ugyldig ZIP-fil'], 422);
        }

        $zip->extractTo(base_path());
        $zip->close();

        return response()->json(['message' => 'Import gennemført.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data loaders
    // ─────────────────────────────────────────────────────────────────────────

    private function getPageSections(): array
    {
        $path = resource_path('fieldsets/page_sections.yaml');
        if (!File::exists($path)) return [];

        $yaml = YAML::parse(File::get($path));
        $sets = data_get($yaml, 'fields.0.field.sets.items.sets', []);

        return collect($sets)->map(function ($set, $handle) {
            $fsHandle = $this->sectionToFieldset($handle);
            return [
                'handle' => $handle,
                'title'  => $set['display'] ?? $handle,
                'deps'   => $this->resolveDepsForHandle($fsHandle),
            ];
        })->values()->all();
    }

    private function getBlueprints(): array
    {
        $blueprints = [];
        $base       = resource_path('blueprints');

        foreach (File::allFiles($base) as $file) {
            if ($file->getExtension() !== 'yaml') continue;

            $yaml     = YAML::parse(File::get($file->getPathname()));
            $relPath  = 'resources/blueprints/' . $file->getRelativePathname();
            $parts    = array_filter(explode(DIRECTORY_SEPARATOR, $file->getRelativePath()));
            $category = implode(' / ', array_map('ucfirst', $parts)) ?: 'Generelt';

            $blueprints[] = [
                'handle'   => $file->getFilenameWithoutExtension(),
                'title'    => $yaml['title'] ?? $file->getFilenameWithoutExtension(),
                'path'     => $relPath,
                'category' => $category,
            ];
        }

        return $blueprints;
    }

    private function getCollections(): array
    {
        return collect(File::glob(base_path('content/collections/*.yaml')))
            ->map(function ($path) {
                $handle = pathinfo($path, PATHINFO_FILENAME);
                $yaml   = YAML::parse(File::get($path));
                return ['handle' => $handle, 'title' => $yaml['title'] ?? ucfirst($handle)];
            })
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dependency resolution
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveDepsForHandle(string $handle): array
    {
        $handles = [];
        $visited = [];
        $this->walkDepsIntoHandles($handle, $handles, $visited);
        return $handles;
    }

    private function walkDepsIntoHandles(string $handle, array &$handles, array &$visited): void
    {
        if (isset($visited[$handle])) return;
        $visited[$handle] = true;

        $path = resource_path("fieldsets/{$handle}.yaml");
        if (!File::exists($path)) return;

        $yaml = YAML::parse(File::get($path));

        foreach ($this->extractDepHandles($yaml) as $dep) {
            if (!in_array($dep, $handles) && File::exists(resource_path("fieldsets/{$dep}.yaml"))) {
                $handles[] = $dep;
                $this->walkDepsIntoHandles($dep, $handles, $visited);
            }
        }
    }

    private function collectDeps(string $handle, array &$files, array &$visited): void
    {
        if (isset($visited[$handle])) return;
        $visited[$handle] = true;

        $path = resource_path("fieldsets/{$handle}.yaml");
        if (!File::exists($path)) return;

        $yaml = YAML::parse(File::get($path));

        foreach ($this->extractDepHandles($yaml) as $dep) {
            $depPath = resource_path("fieldsets/{$dep}.yaml");
            $key     = "resources/fieldsets/{$dep}.yaml";
            if (File::exists($depPath) && !isset($files[$key])) {
                $files[$key] = $depPath;
                $this->collectDeps($dep, $files, $visited);
            }
        }
    }

    private function extractDepHandles(array $yaml): array
    {
        $deps = [];
        array_walk_recursive($yaml, function ($value, $key) use (&$deps) {
            // - import: fieldset_handle
            if ($key === 'import' && is_string($value)) {
                $deps[] = $value;
            }
            // field: fieldset_handle.field_name  (e.g. field: common.section_spacing)
            if ($key === 'field' && is_string($value) && str_contains($value, '.')) {
                $deps[] = explode('.', $value, 2)[0];
            }
        });
        return array_unique($deps);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function sectionToFieldset(string $handle): string
    {
        return str_replace('/', '_', $handle);
    }

    private function buildFilteredPageSections(array $selectedHandles): string
    {
        $path = resource_path('fieldsets/page_sections.yaml');
        $yaml = YAML::parse(File::get($path));

        $allSets  = data_get($yaml, 'fields.0.field.sets.items.sets', []);
        $filtered = array_filter($allSets, fn($k) => in_array($k, $selectedHandles), ARRAY_FILTER_USE_KEY);

        data_set($yaml, 'fields.0.field.sets.items.sets', $filtered);

        return YAML::dump($yaml);
    }
}
