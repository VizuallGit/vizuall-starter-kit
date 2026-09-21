<?php

/**
 * Runs once, after `php please starter-kit:install` has copied the kit's files
 * and installed the modules the user said yes to.
 *
 * It only writes what the chosen modules did not. A site installed without
 * the `sections` module still needs a page_sections registry — with the
 * global-section set — for the pages blueprint to load, plus a home entry
 * that is in the pages tree. Nothing here overwrites a file the kit installed:
 * with the module, page_sections.yaml is the full registry and stays.
 */
class StarterKitPostInstall
{
    public function handle($console)
    {
        $this->writeIfMissing(
            base_path('resources/fieldsets/page_sections.yaml'),
            <<<'YAML'
title: 'Page sections'
fields:
  -
    handle: page_sections
    field:
      type: replicator
      display: 'Page sections'
      collapse: accordion
      sets:
        items:
          display: Items
          sets:
            global_section:
              display: 'Global section'
              hide: true
              fields:
                -
                  handle: global_section
                  field:
                    collections:
                      - saved_sections
                    max_items: 1
                    type: entries
                    display: 'Global section'

YAML,
            $console,
            'Created page_sections.yaml with the global_section set (sections module not installed)'
        );

        $this->writeIfMissing(
            base_path('content/collections/pages/home.md'),
            <<<'MD'
---
id: home
blueprint: home
title: Home
page_sections: []
---

MD,
            $console,
            'Created content/collections/pages/home.md'
        );

        $this->writeIfMissing(
            base_path('content/trees/collections/pages.yaml'),
            "tree:\n  - entry: home\n",
            $console,
            'Created the pages tree with home in it'
        );

        $console->info('Visual Editor stores (Global sections + Compositions) are included from the starter kit.');
    }

    protected function writeIfMissing(string $path, string $contents, $console, string $message): void
    {
        if (file_exists($path)) {
            $console->line('Kept existing '.str_replace(base_path().'/', '', $path));

            return;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $contents);
        $console->info($message);
    }
}
