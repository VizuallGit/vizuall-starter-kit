<?php

namespace App\Http\Middleware;

use App\Frontend\AlpineMarkup;
use App\Frontend\BlurLoadMarkup;
use App\Frontend\ContrastMarkup;
use Closure;
use Illuminate\Foundation\Vite as LaravelVite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alpine og auto-kontrast sættes ind på den offentlige side kun når HTML'en
 * bruger dem. Live Preview får begge, så editoren kan tilføje dem undervejs.
 * Blur-load (sløret forhåndsbillede i components/picture og image) følger HTML'en:
 * det virker alligevel kun på billeder der er der ved indlæsning.
 *
 * Skal ligge efter Statamics static-cache-middleware i statamic.web, så den
 * HTML cachen gemmer, allerede indeholder de script-tags siden skal have.
 * Preview-svar caches ikke (de er POST eller har et token).
 */
class LoadUsedFrontendScripts
{
    private const SCRIPTS = [
        'contrast' => 'resources/js/contrast.js',
        'alpine' => 'resources/js/alpine.js',
        'blur-load' => 'resources/js/blur-load.js',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response || ! $this->isHtml($response)) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html)) {
            return $response;
        }

        $tags = $this->tagsFor($html, $this->isLivePreview($request));

        if ($tags === '') {
            return $response;
        }

        $pos = strripos($html, '</body>');

        if ($pos === false) {
            return $response;
        }

        $response->setContent(substr($html, 0, $pos).$tags.substr($html, $pos));
        $response->headers->remove('Content-Length');

        return $response;
    }

    protected function isLivePreview(Request $request): bool
    {
        return $request->isLivePreview();
    }

    private function tagsFor(string $html, bool $preview): string
    {
        $needed = [
            'contrast' => $preview || ContrastMarkup::usesContrast($html),
            'alpine' => $preview || AlpineMarkup::usesAlpine($html),
            'blur-load' => BlurLoadMarkup::usesBlurLoad($html),
        ];

        $clientPresent = str_contains($html, '@vite/client');
        $tags = '';

        foreach (self::SCRIPTS as $name => $entry) {
            if (! $needed[$name] || $this->alreadyLoaded($html, $name)) {
                continue;
            }

            $tags .= $this->scriptTag($entry, $clientPresent);
            $clientPresent = true;
        }

        return $tags;
    }

    protected function scriptTag(string $entry, bool $stripClient): string
    {
        $vite = clone app(LaravelVite::class);

        $html = $vite
            ->withEntryPoints([$entry])
            ->useBuildDirectory('build')
            ->toHtml();

        // Stylesheet-tagget i layoutet har allerede hentet Vite-klienten i dev.
        if ($stripClient) {
            $html = preg_replace(
                '#<script\b[^>]*\bsrc="[^"]*@vite/client"[^>]*>\s*</script>#',
                '',
                $html,
            ) ?? $html;
        }

        return "\n".$html."\n";
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        if ($type === '') {
            return true;
        }

        return str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }

    private function alreadyLoaded(string $html, string $name): bool
    {
        return str_contains($html, "resources/js/{$name}.js")
            || preg_match('#/assets/'.$name.'-[^"]+\.js#', $html) === 1;
    }
}
