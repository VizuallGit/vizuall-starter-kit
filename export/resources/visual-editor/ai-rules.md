# AI-regler for Visual Editor

Redigér denne liste — den bliver sendt med hver AI-besked.
Sig “husk det her” eller “sådan gør vi” i panelet, så bliver en ny regel føjet til.

## Arbejdsmåde

1. Slet aldrig en sektion og byg den forfra. Behold det der allerede er der.
2. Tilføj eller justér kun det, brugeren beder om.
3. Fjern kun noget, hvis brugeren siger fjern, slet eller tag ud.
4. Behold alle eksisterende `{{ visual_edit }}`-tags, field handles, loops og partials.
5. Skriv kun i den valgte sektions filer. Rør ikke andre sektioner.
6. Opret ikke en ny sektionstype, et nyt fieldset eller en ny fil, medmindre brugeren udtrykkeligt beder om det.
7. Genbrug felter der allerede findes (fx heading, title, text, blocks). Hardcod ikke tekst, hvis der er et felt til den.
8. Behold `{{ style_push }}` og `{{ script_push }}`. Skriv CSS/JS dér, ikke som en ny fil.
9. Læs den nuværende fil først. Skriv den tilbage med det gamle indhold plus ændringen.
10. Når nogen siger hvordan vi bygger (“husk det”, “sådan gør vi”), tilføj en ny nummereret regel her. Slet ikke de gamle.

## Ny sektion — start altid her

Hver ny sektionstype starter med præcis denne ramme. Byg indhold indeni. Slet ikke rammen. Sæt ikke `_class = …` i filen — det kommer fra page_sections-loopet.

```
<section id="id-{{ id }}" class="[ {{ _class }} ] wrapper relative " {{ visual_edit outline_inside="true" section_orderable="true" }}>

</section>

{{ style_push }}
<style>
  #id-{{ id }} {
    --color-bg: {{ bg_color ?? 'var(--gray-600)' }};

    {{ partial:spacing_sides_css :padding="padding.laptop.padding" }}
  }

  @media (width < 64em) {
    #id-{{ id }} {
      {{ partial:spacing_sides_css :padding="padding.tablet.padding" }}
    }
  }

  @media (width < 48em) {
    #id-{{ id }} {
      {{ partial:spacing_sides_css :padding="padding.mobile.padding" }}
    }
  }

  @scope(.{{ _class }}) {
    :scope {
      background-color: var(--color-bg);
    }
  }
</style>
{{ /style_push }}

{{ script_push }}
<script>

</script>
{{ /script_push }}
```

- `#id-{{ id }}` = custom properties (farve, gap, kolonne-antal…) plus `{{ partial:spacing_sides_css }}`. Aldrig `.id-{{ id }}`. Skriv ikke `background-color` her.
- `@scope(.{{ _class }})` = selve CSS’en. Brug `var(--…)` fra id’et. Layout, hover, grid, typografi.
- CSS kun i `style_push`. JS kun i `script_push`.
- Brug ikke `{{ responsive_css }}`. Skriv det direkte i `#id-{{ id }}`, som Hero style 2.

## HTML (sådan bygger vi)

11. Roden er et `<section id="id-{{ id }}" class="[ {{ _class }} ] …" {{ visual_edit outline_inside="true" section_orderable="true" }}>`.
12. Indhold ligger i `.wrapper`. Indre blokke får et navn i klassen, fx `[ content ]`, `[ media ]`, `[ list ]`.
13. Tekst og billeder kommer fra felter: `{{ title }}`, `{{ blocks }}`, `{{ partial src="blocks/{type}" }}` — ikke hardcoded copy, medmindre brugeren gav ordene.
14. Klikbare felter får `{{ visual_edit field="…" inline_edit="true" }}`. Lister får `insertable="true"` på listen og `orderable="true"` på rækken.
15. HTML-klasser er token-utilities (`wrapper`, `py-900`, `bg-primary`, `text-800`, `gap-gutter`). Ikke arbitrary values som `bg-[#333]` eller `p-[17px]`.
16. Layout, hover, media queries og undtagelser hører hjemme i CSS-ruden, ikke som lange utility-stakke.

## CSS (sådan skriver vi det)

17. Alt der kan skifte pr. sektion (farve, padding, gap, bredde, antal kolonner) bliver en custom property under `#id-{{ id }}`. Den rigtige CSS (de CSS-egenskaber browseren tegner) ligger kun i `@scope(.{{ _class }})` og bruger `var(--…)`.
18. Eksempel — farve og padding fra felter:

```
#id-{{ id }} {
  --color-bg: {{ bg_color ?? 'var(--gray-600)' }};
  --color-text: {{ text_color ?? 'inherit' }};
  --pad-block: var(--size-600);
}

@scope(.{{ _class }}) {
  :scope {
    background-color: var(--color-bg);
    color: var(--color-text);
    padding-block: var(--pad-block);
  }

  .list {
    gap: var(--gutter);
  }
}
```

19. Skriv aldrig `background-color: {{ bg_color }}` eller `padding: 2rem` direkte i `@scope`, hvis værdien kommer fra et felt eller kan være forskellig. Sæt den på id’et først.
20. Brug tokens som fallback: `var(--size-100)` … `var(--size-900)`, `var(--gutter)`, `var(--primary)`, `var(--gray-*)`.
21. Brug ikke `{{ responsive_css }}`. Padding skrives med `{{ partial:spacing_sides_css :padding="padding.laptop.padding" }}` inde i `#id-{{ id }}`, og de to media queries til tablet (`width < 64em`) og mobil (`width < 48em`) — som Hero style 2.
22. `data-auto-contrast` på sektionen når teksten skal vende sig efter baggrunden.
23. Læs et felt én gang pr. partial. Hver læsning af `{{ felt }}` er et opslag gennem hele sidens data; læg feltet i en variabel øverst i partialen og brug variablen til laptop/tablet/mobile. Løb `{{ blocks }}` igennem én gang pr. sektion, ikke tre.
24. Færrest mulige partial-kald pr. sektion. Et partial-kald koster i sig selv (ca. 0,7 ms) før det gør noget; tolv små partials pr. sektion er dyrere end én partial der skriver al sektionens responsive CSS. Tjek en ny sektion i performance-panelets Server-fane: koster den over 40 ms egen tid, viser listen hvilken partial.

## Fieldset (sådan bygger vi det)

`resources/fieldsets/ai_demo/section.yaml` er en **guideline**, ikke en skabelon du kopierer 1:1.

Når brugeren beder om en sektion (fx “en hero med overskrift, billede og to knapper”):
- Læs demoen først, så du kender formen (Content-tab, Style-tab, Farver-accordion, Spacing-accordion).
- Byg et **nyt** fieldset med de felter opgaven kræver. Udvid: flere farver, media, knapper, lister — det demoen ikke har.
- Kopiér ikke demoens handles eller felter, medmindre de faktisk hører til opgaven.
- Registrér **aldrig** `ai_demo` i page_sections.

Rækkefølge i YAML — et `type: tab` åbner et lag, felterne under det hører til det lag:

1. **Content** — `type: tab` (ikke accordion). Det brugeren redigerer: blocks, tekst, billeder, knapper. Ikke farver her.
2. **Style** — `type: tab`. Layout-valg (bredde, position, vis/skjul). Kun det sektionen bruger.
3. **Farver** — `type: tab` med `style: accordion`. Alle farver, `type: theme_color_picker`. Hvert farvefelt → custom property under `#id-{{ id }}`.
4. **Spacing** — `type: tab` med `style: accordion`. Padding/gap via `common.section_spacing` og `sve_responsive: true`.

Genbrug `common.*` og `basic_blocks.blocks` når det passer. Nye handles i snake_case.

## Nyt fieldset / ny sektionstype

Når en ny sektion er færdig, er den **ikke** færdig før den ligger i `resources/fieldsets/page_sections.yaml` under en eksisterende fane. Læs filen først — fanerne er replicator-grupperne (`display:` på hver gruppe). Tilføj sættet i den gruppe der passer, skriv den fulde YAML tilbage (ingen eksisterende sæt må forsvinde). Opret en ny fane hvis typen ikke passer; Patterns-chipsene følger fanerne automatisk.

26. Tre filer, samme handle: `resources/fieldsets/foo/style_1.yaml`, `resources/views/partials/page_sections/foo/style_1.antlers.html`, og registrering i `resources/fieldsets/page_sections.yaml`:
            foo/style_1:
              display: 'Foo style 1'
              fields:
                - import: foo.style_1
27. Antlers-filen starter med rammen under “Ny sektion”. Fieldset følger demo-formen og udvider efter opgaven.
28. `title:` og `display:` på dansk eller som de andre sæt. Handle i snake_case.
29. Efter registrering i page_sections: sig at Control Panel skal genindlæses.

30. Svar kort, på brugerens sprog.
31. CSS fremover som Hero style 2: custom properties og spacing-partial direkte i `#id-{{ id }}`. Ikke `{{ responsive_css }}`. Headline-blokke kaldes med `{{ partial src="blocks/{type}" headline_tag="…" headline_size="…" }}` — tag og størrelse sættes pr. sektion.
