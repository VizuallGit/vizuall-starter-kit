<?php

namespace App\Tags;

use App\Seo\SitemapSettings;
use Statamic\Tags\Tags;

/**
 * `{{ seo_robots }}` — hvad `<meta name="robots">` skal sige, eller ingenting.
 *
 * To spørgsmål slås sammen til ét svar, så skabelonen kun har én linje at
 * forholde sig til:
 *
 *   1. Må hele sitet findes? Styres på Værktøjer → Sitemap. Standarden følger
 *      miljøet: produktion ja, alt andet nej — så en kopi på et testdomæne
 *      aldrig havner i Google, uden at nogen skal huske det.
 *   2. Må denne ene side findes? Fluebenet «Skjul for søgemaskiner» på sidens
 *      SEO-fane.
 *
 * Brugt som par rendrer det kun sit indhold når siden skal holdes ude:
 *
 *     {{ seo_robots }}<meta name="robots" content="{{ content }}" />{{ /seo_robots }}
 *
 * Et par, ikke `{{ if seo_robots }}` — Antlers slår variabler op i en
 * betingelse og kalder ikke et tag, så den betingelse ville altid være falsk.
 * Markuppen bliver i skabelonen, beslutningen i PHP.
 *
 * Det bevidste fravalg: dette er IKKE `APP_ENV`. At skrive APP_ENV fra
 * kontrolpanelet ville slå fejlvisning, cache-drivere og debug-tilstand om på
 * én gang — og på et produktionssite er config'en cachet, så .env-ændringen
 * enten ikke virker eller virker halvt. Indeksering får sin egen kontakt.
 */
class SeoRobots extends Tags
{
    public static $handle = 'seo_robots';

    public function index(): string
    {
        $content = $this->content();

        if (! $this->isPair) {
            return $content ?? '';
        }

        return $content === null ? '' : $this->parse(['content' => $content]);
    }

    /** Hvad robots-taggen skal sige, eller null når siden gerne må findes. */
    protected function content(): ?string
    {
        if (! app(SitemapSettings::class)->siteIndexable($this->environment())) {
            return 'noindex, nofollow';
        }

        if ($this->context->value('meta_noindex')) {
            return 'noindex, nofollow';
        }

        return null;
    }

    /**
     * Cascadens miljø, ikke appens.
     *
     * Det statiske udtræk sætter cascadens `environment` til `production`,
     * fordi kopien er produktionen. Læste vi `app()->environment()` her,
     * ville en eksport bygget på en server i `local` lægge noindex på hver
     * side — tavst, og først opdaget når Google holdt op med at komme.
     */
    protected function environment(): ?string
    {
        $value = $this->context->value('environment');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
