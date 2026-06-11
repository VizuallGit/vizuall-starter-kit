<?php
namespace App\Http\Middleware;

use App\Tags\ScriptPush;
use App\Tags\StylePush;
use App\Tags\YieldMinified;
use App\Tags\YieldScripts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectStyles
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $content = $response->getContent();

        if (!is_string($content)) {
            return $response;
        }

        if (str_contains($content, YieldMinified::PLACEHOLDER)) {
            $css = StylePush::getAll();
            $css = preg_replace('!<style[^>]*>|</style>!i', '', $css);
            $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
            $css = preg_replace('/\s+/', ' ', $css);
            $css = preg_replace('/\s*([:;{},>~])\s*/', '$1', $css);
            $css = trim($css);
            $content = str_replace(YieldMinified::PLACEHOLDER, $css ? "<style>{$css}</style>" : '', $content);
        }

        if (str_contains($content, YieldScripts::PLACEHOLDER)) {
            $js = ScriptPush::getAll();
            $js = preg_replace('!<script[^>]*>|</script>!i', '', $js);
            $js = trim($js);
            $content = str_replace(YieldScripts::PLACEHOLDER, $js ? "<script>{$js}</script>" : '', $content);
        }

        $response->setContent($content);
        return $response;
    }
}
