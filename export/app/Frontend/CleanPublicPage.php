<?php

namespace App\Frontend;

use Closure;
use Illuminate\Http\Request;
use Statamic\Events\ResponseCreated;
use Statamic\StaticSite\Request as StaticSiteRequest;
use Symfony\Component\HttpFoundation\Response;
use Vizuall\StylePush\Http\Middleware\InjectAssets;
use Vizuall\StylePush\Tags\YieldStyles;

/**
 * Den offentlige side gjort hurtig, før den sendes eller udgives — aldrig i
 * Live Preview eller editoren: PageCss (CSS'en inline, kun det brugte, hver
 * regel én gang) og PriorityMedia (billedet/videoen øverst hentes først).
 *
 * To indgange, samme arbejde (som LoadUsedFrontendScripts):
 *
 *  - Middleware forrest i `web`, så den ser siden EFTER Style Push har sat
 *    sektionernes CSS ind (InjectAssets ligger i `web` og kører indenfor).
 *  - Statamics ResponseCreated, for den statiske udgivelse: statamic/ssg kalder
 *    toResponse() uden middleware. Static Publish sætter Style Push-CSS'en ind
 *    på samme event, men lytter først fra eksportens start, efter os. Derfor
 *    sætter vi den ind selv, med samme InjectAssets, før vi renser; Static
 *    Publish finder så ingen pladsholder og tømmer bare stakken.
 *
 * Renset én gang er der intet sitets stylesheet tilbage at rense, og
 * prioriteten står allerede på billedet, så den anden indgang ændrer intet.
 */
final class CleanPublicPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->applies($request, $response)) {
            $this->clean($response);
        }

        return $response;
    }

    public function created(ResponseCreated $event): void
    {
        $request = request();
        $response = $event->response;

        if (! $this->applies($request, $response)) {
            return;
        }

        if (class_exists(InjectAssets::class) && str_contains((string) $response->getContent(), YieldStyles::PLACEHOLDER)) {
            app(InjectAssets::class)->handle($request, fn () => $response);
        }

        $this->clean($response);
    }

    private function applies(Request $request, mixed $response): bool
    {
        if (! $response instanceof Response || ! is_string($response->getContent()) || ! $this->isHtml($response)) {
            return false;
        }

        // Den statiske udgivelse: siden som besøgende får den. Requesten er
        // fanget fra den der startede udgivelsen (fx en POST fra CP'et), så
        // metode og token siger intet her.
        if (class_exists(StaticSiteRequest::class) && $request instanceof StaticSiteRequest) {
            return true;
        }

        return $request->isMethod('GET')
            && ! $request->isLivePreview()
            && $request->statamicToken() === null
            && ! $request->is(config('statamic.routes.action', '!').'/*')
            && ! $request->is(trim((string) config('statamic.cp.route', 'cp'), '/').'*');
    }

    private function clean(Response $response): void
    {
        $html = $response->getContent();
        $clean = PriorityMedia::apply(PageCss::clean($html, fn (string $path) => $this->read($path)));

        if ($clean !== $html) {
            $response->setContent($clean);
            $response->headers->remove('Content-Length');
        }
    }

    /** Kun sitets byggede filer og fonts.css; PageCss beder ikke om andet. */
    private function read(string $path): ?string
    {
        if (! preg_match('#^/(build/assets/[\w.-]+\.(?:css|js)|fonts/fonts\.css)$#', $path, $match)) {
            return null;
        }

        $file = public_path($match[1]);

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }
}
