<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Visual Editor
    |--------------------------------------------------------------------------
    |
    | When set to false, the bridge script will never be injected into Live
    | Preview responses and all `visual_edit` tags/helpers become no-ops.
    |
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Previews
    |--------------------------------------------------------------------------
    |
    | Kun det der afviger fra addonets egne værdier — resten merges derfra.
    |
    | Et temaskift gør hvert preview forældet på én gang, så en kørsel er ~24
    | optagelser i træk. De to tal her er dem der afgør hvor længe det tager:
    |
    | - delay: hvor længe der ventes på siden før der klikkes. Skal dække de
    |          entré-animationer sektionerne har; alt derudover er spildtid
    |          ganget med antallet af sektioner.
    | - scale: enhedspixels pr. CSS-pixel, og kun hele tal — Browsershot tager
    |          en int, så en brøk bliver stiltiende til 1. Med 1 bliver et
    |          preview 1440 px bredt og ~45 KB; med 2 er det 2880 px og ~1,9 MB
    |          til et kort der vises ~250 px bredt.
    |
    | `width` bliver stående på 1440: den bestemmer *layoutet*. Sætter man den
    | ned, tegnes sektionerne i tablet-layout og kolonner stakker — et andet
    | billede, ikke et mindre.
    |
    */
    'previews' => [
        'delay' => 500,
        'scale' => 1,

        // ⚠️ Laravels config-merge virker kun på ØVERSTE niveau. Fordi denne
        // 'previews'-blok findes her, erstatter den addonets helt — hver nøgle
        // addonet sætter, og som ikke står her, er væk. Tilføjer du en nøgle
        // her, så tag resten med.
        //
        // Uden 'watch' dækkede design-fingerprintet ingen filer: en ændring i
        // site.css, i en delt blok-partial eller i det byggede tema gjorde
        // ingen previews forældede, så de blev stående til nogen kørte --force.
        'field' => 'page_sections',
        'collection' => 'pages',
        'scan' => null,
        'template' => null,
        'auto' => env('SVE_PREVIEWS_AUTO', true),
        'exclude' => ['columns', 'reusable_sections'],
        'selector' => 'main > *',
        'overrides' => [],
        'width' => 1440,
        'watch' => [
            'public/build/manifest.json',
            'resources/css',
            'resources/views',
        ],
        // Sektionernes egne partials tælles pr. sektion, ikke i design-hashen —
        // ellers ville en rettelse i én sektion gøre alle previews forældede.
        'watch_exclude' => [
            'resources/views/partials/page_sections',
        ],
        'section_partials' => 'resources/views/partials/page_sections',

        // Sættet hvis værdier ALLE previews afhænger af — farver, fonte, spacing.
        // Skrevet her selvom det er addonets default: header og footer bor ikke
        // længere i samme sæt, og forveksles de to, bliver previews stående
        // uændrede efter et farveskift.
        'theme_global' => 'theme_settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Header / footer
    |--------------------------------------------------------------------------
    |
    | Header og footer har hvert sit globale sæt — `site_head` ("Header") og
    | `site_foot` ("Footer") — ikke Theme settings: de hører til sitets
    | opbygning, ikke til temaets farver og typografi.
    |
    | Addonet kan kun pege på ét sæt her. Det peger på `site_head`, så Header
    | kan redigeres fra Live Preview; Footer redigeres i globals-oversigten
    | indtil addonet får en `footer.global`-nøgle.
    |
    | Hele blokken står her, ikke kun `global`: addonets config merges fladt, så
    | en delvis `chrome` ville fjerne `header.styles` og `footer.styles`, og
    | layout-kortene i Designs-panelet ville forsvinde.
    |
    | `hidden_tabs` er tom med vilje. Den fandtes for at skjule Header/Footer
    | inde i Theme settings' dockede panel — den dør er lukket nu, fordi fanerne
    | er flyttet ud. Sættes de to faner på listen igen, ville "Header & footer"
    | i globals-vælgeren åbne uden faner overhovedet.
    |
    | Fanernes `display` SKAL blive ved med at starte med ordene "Header" og
    | "Footer": Live Preview finder den rigtige fane på knappens tekst.
    |
    */
    'chrome' => [
        'global' => 'site_head',
        'hidden_tabs' => [],
        'header' => [
            'global' => 'site_head',
            'styles' => [
                ['handle' => 'style_1', 'label' => 'Classic — logo · nav · CTA'],
                ['handle' => 'style_2', 'label' => 'Centered — logo over nav'],
            ],
        ],
        'footer' => [
            'global' => 'site_foot',
            // Footeren har ét layout og ingen vælger — derfor ingen layout-kort.
            'styles' => [],
        ],
    ],

];
