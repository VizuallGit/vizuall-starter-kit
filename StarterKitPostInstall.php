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
    }
}
