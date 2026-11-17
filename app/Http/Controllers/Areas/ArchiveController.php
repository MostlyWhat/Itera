<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Queries\ProjectNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Archive page: the archived Areas and Projects of the current team,
 * each with a way to restore it.
 */
class ArchiveController extends Controller
{
    public function __invoke(Request $request, ProjectNavigation $navigation): Response
    {
        $list = $navigation->for($request->user()->currentTeam);

        return Inertia::render('archive/Index', [
            'archivedAreas' => $list['archivedAreas'],
            'archivedProjects' => $list['archivedProjects'],
        ]);
    }
}
