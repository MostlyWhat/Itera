<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make `back()` return to the page the user saw last.
 *
 * Every action ends with `back()`. It uses the Referer header and, without
 * it, the session's previous URL. Two problems made actions go to the
 * homepage:
 *
 * 1. Laravel saves the previous URL only on full page loads, because
 *    Inertia visits are XHR requests.
 * 2. Some browsers, for example Safari with its privacy protections, send
 *    no Referer or only the origin ("https://itera.wasmer.app/"). The
 *    origin alone sends `back()` to the homepage.
 *
 * So this middleware saves the URL of each Inertia page visit, and for an
 * action with no Referer or an origin-only Referer, it uses that URL.
 */
class RememberInertiaPage
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && $request->hasSession()) {
            $this->repairReferer($request);
        }

        $response = $next($request);

        if (
            $request->isMethod('GET')
            && $request->header('X-Inertia')
            && ! $request->header('X-Inertia-Partial-Data')
            && $response->isSuccessful()
            && $request->hasSession()
        ) {
            $request->session()->setPreviousUrl($request->fullUrl());
        }

        return $response;
    }

    /**
     * Replace a missing or origin-only Referer with the last page the user
     * saw. The homepage has no actions, so a Referer of "/" is never the
     * real page.
     */
    private function repairReferer(Request $request): void
    {
        $previous = $request->session()->previousUrl();

        if ($previous === null) {
            return;
        }

        $referer = (string) $request->headers->get('referer', '');
        $path = parse_url($referer, PHP_URL_PATH);

        if ($referer === '' || $path === null || $path === '' || $path === '/') {
            $request->headers->set('referer', $previous);
        }
    }
}
