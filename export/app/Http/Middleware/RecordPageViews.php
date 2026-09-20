<?php

namespace App\Http\Middleware;

use App\Dashboard\PageViewStore;
use Closure;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Cascade;
use Statamic\Statamic;
use Symfony\Component\HttpFoundation\Response;

class RecordPageViews
{
    public function __construct(protected PageViewStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->record();
        }

        return $response;
    }

    protected function shouldRecord(Request $request, Response $response): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (Statamic::isCpRoute()) {
            return false;
        }

        if ($request->isLivePreview()) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?: $request->header('Purpose')));

        if (str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender')) {
            return false;
        }

        $ua = (string) $request->userAgent();

        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit/i', $ua)) {
            return false;
        }

        return true;
    }

    protected function record(): void
    {
        try {
            $content = Cascade::content();
        } catch (\Throwable) {
            return;
        }

        if (! $content instanceof Entry) {
            return;
        }

        if (method_exists($content, 'status') && $content->status() !== 'published') {
            return;
        }

        $this->store->increment($content);
    }
}
