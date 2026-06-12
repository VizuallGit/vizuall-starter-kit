<?php

class StarterKitPostInstall
{
    public function handle($console)
    {
        $yaml = <<<YAML
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
          sets: {}
YAML;

        file_put_contents(
            base_path('resources/fieldsets/page_sections.yaml'),
            $yaml
        );

        $console->info('Created empty page_sections.yaml');

        $homeMd = <<<MD
---
id: home
blueprint: home
title: Home
page_sections: []
---
MD;

        $homePath = base_path('content/collections/pages/home.md');
        if (!file_exists($homePath)) {
            @mkdir(dirname($homePath), 0755, true);
            file_put_contents($homePath, $homeMd);
            $console->info('Created content/collections/pages/home.md');
        }
    }
}
