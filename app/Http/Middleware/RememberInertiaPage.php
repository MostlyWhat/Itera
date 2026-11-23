<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Save the URL of each page that Inertia shows, as the session's previous
 * URL. Laravel saves it only for full page loads, because Inertia visits
 * are XHR requests. Every action ends with `back()`, which uses the
 * Referer header and, without it, the previous URL. Some browsers and
 * privacy tools remove the Referer header, so without this middleware
 * `back()` can send the user to the first page they opened, often the
 * homepage.
 */
class RememberInertiaPage
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
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
}
