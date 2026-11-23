<?php

namespace App\Http\Controllers\Areas;

use App\Actions\Areas\SavePriorityOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Areas\OrderAreasRequest;
use App\Http\Requests\Areas\OrderProjectsRequest;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

/**
 * The priority order of Areas and Projects (ITERA-07). The user drags a row
 * or uses the move buttons, and the page sends the full new order. Only the
 * positions change.
 */
class PriorityOrderController extends Controller
{
    /**
     * Save the order of the current team's Areas.
     */
    public function areas(OrderAreasRequest $request, SavePriorityOrder $save): RedirectResponse
    {
        $save->handle(
            Area::query()->where('team_id', $request->user()->currentTeam->id)->orderBy('position')->orderBy('id'),
            array_values(array_map('intval', $request->validated('area_ids'))),
        );

        return back();
    }

    /**
     * Save the order of the Projects in one Area.
     */
    public function projects(OrderProjectsRequest $request, Area $area, SavePriorityOrder $save): RedirectResponse
    {
        $save->handle(
            Project::query()->where('area_id', $area->id)->orderBy('position')->orderBy('id'),
            array_values(array_map('intval', $request->validated('project_ids'))),
        );

        return back();
    }
}
