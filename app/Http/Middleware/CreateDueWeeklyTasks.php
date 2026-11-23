<?php

namespace App\Http\Middleware;

use App\Actions\Tasks\CreateWeeklyTasks;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Make the due Tasks of weekly tasks before a team page loads (ITERA-09).
 * The app has no scheduler on its host, so the first page a user opens in
 * a new week makes that week's Tasks. The action skips Tasks that exist,
 * so running it on each page load is safe.
 */
class CreateDueWeeklyTasks
{
    public function __construct(private readonly CreateWeeklyTasks $weekly) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->user()?->currentTeam;

        if ($team !== null && $request->isMethod('GET') && ! $request->header('X-Inertia-Partial-Data')) {
            $this->weekly->forTeam($team);
        }

        return $next($request);
    }
}
