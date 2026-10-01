<?php

namespace App\Http\Middleware;

use App\Redirects\MissingLog;
use App\Redirects\RedirectStore;
use Closure;
use Illuminate\Http\Request;
use Statamic\Statamic;
use Symfony\Component\HttpFoundation\Response;

/**
 * Addresses that moved, and addresses that were never there.
 *
 * One middleware with two halves of the same job: before the router, send the
 * request on if a redirect claims it; after, note the miss if nothing answered.
 * The note is what makes the screen useful — a 404 with a count beside it is a
 * redirect waiting to be written.
 *
 * Prepended to the web group (bootstrap/app.php) so the lookup happens before
 * Statamic resolves a URL and before the static cache answers from disk. Laravel
 * reorders middleware it knows about, so the position is asserted by a test
 * rather than assumed — see tests/Feature/RedirectsTest.php.
 *
 * Note for the static site: this runs in PHP. A redirect only fires on the
 * static export once the same rows are written into the Worker — until then
 * redirects work here and not on the published site.
 */
class HandleRedirects
{
    public function __construct(
        protected RedirectStore $store,
        protected MissingLog $log,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldConsider($request)) {
            return $next($request);
        }

        $path = $this->store->normalise($request->getPathInfo());

        if ($hit = $this->store->match($path)) {
            return redirect($this->withQuery($hit['to'], $request), $hit['status']);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            $this->log->record($path);
        }

        return $response;
    }

    /**
     * Only plain page requests. A POST is somebody submitting something and
     * must not be bounced; the Control Panel and action URLs (`/!/…`) are
     * machinery, and a 404 from them says nothing about a missing page.
     */
    protected function shouldConsider(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if (Statamic::isCpRoute()) {
            return false;
        }

        $path = '/'.ltrim($request->getPathInfo(), '/');

        foreach (['/!/', '/'.config('statamic.routes.action', '!').'/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Carry the query string across, unless the target brought its own —
     * tracking parameters on a moved link should survive the move.
     */
    protected function withQuery(string $target, Request $request): string
    {
        $query = $request->getQueryString();

        if ($query === null || $query === '' || str_contains($target, '?')) {
            return $target;
        }

        return $target.'?'.$query;
    }
}
