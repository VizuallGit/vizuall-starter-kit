<?php

namespace App\Http\Controllers;

use App\Redirects\RedirectStore;
use App\Seo\Sitemap;
use App\Seo\SitemapSettings;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * /sitemap.xml and /robots.txt, both generated.
 *
 * robots.txt is a route rather than a file in public/, so the Sitemap line
 * always carries the domain the request came in on — one file that is right
 * on the live site, the demo and locally, with nothing to keep in step. The
 * web server serves a real file in public/ before Laravel ever sees the
 * request, so there must not be one.
 */
class SitemapController extends Controller
{
    public function xml(Sitemap $sitemap): Response
    {
        $urls = $sitemap->urls();

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $body .= '  <url>'."\n";
            $body .= '    <loc>'.e($url['url']).'</loc>'."\n";

            if ($url['lastmod']) {
                $body .= '    <lastmod>'.e($url['lastmod']).'</lastmod>'."\n";
            }

            $body .= '  </url>'."\n";
        }

        $body .= '</urlset>'."\n";

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * The redirects as Cloudflare reads them.
     *
     * The static copy runs no PHP, so the middleware never fires there —
     * Cloudflare answers from a `_redirects` file instead. The site writes
     * that format because the site owns the rules; Static Publish only copies
     * the file, and neither repo has to know what the other's YAML looks like.
     *
     * Cloudflare's splat: a `/*` source captures the rest, and `:splat` in
     * the target puts it back. Our YAML writes `/*` at both ends.
     */
    public function cloudflareRedirects(RedirectStore $store): Response
    {
        $lines = [];

        foreach ($store->all() as $row) {
            if (! $row['enabled']) {
                continue;
            }

            $to = str_ends_with($row['to'], '/*')
                ? rtrim(substr($row['to'], 0, -2), '/').'/:splat'
                : $row['to'];

            $lines[] = $row['from'].'  '.$to.'  '.$row['status'];
        }

        return response(implode("\n", $lines).($lines ? "\n" : ''), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function robots(SitemapSettings $settings): Response
    {
        // Må sitet ikke findes, siger filen det hele vejen — en meta-tag på
        // siderne alene er ikke nok, for en crawler skal hente siden for at
        // se den.
        $body = $settings->siteIndexable()
            ? "User-agent: *\nDisallow: /cp/\n\n".'Sitemap: '.url('/sitemap.xml')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
