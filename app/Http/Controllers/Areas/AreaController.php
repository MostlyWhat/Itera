<?php

namespace App\Http\Controllers\Areas;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Areas\SaveAreaRequest;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Areas of the current team. The team comes from the URL. The
 * EnsureTeamMembership middleware checks the membership and makes it the
 * current team. AreaPolicy checks each Area that the URL names.
 */
class AreaController extends Controller
{
    /**
     * Show the Areas and Projects of the current team.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $areas = $team->areas()
            ->with(['projects' => fn ($query) => $query->withCount([
                'tasks as open_tasks_count' => fn ($tasks) => $tasks->where('status', '!=', TaskStatus::Done->value),
                'tasks as overdue_tasks_count' => fn ($tasks) => $tasks->overdue(),
            ])])
            ->get();

        $active = $areas->filter(fn (Area $area) => ! $area->isArchived());

        return Inertia::render('areas/Index', [
            'areas' => $active->values()->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'projects' => $area->projects
                    ->reject(fn (Project $project) => $project->isArchived())
                    ->values()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'openTasks' => (int) $project->getAttribute('open_tasks_count'),
                        'overdueTasks' => (int) $project->getAttribute('overdue_tasks_count'),
                    ]),
            ]),
            'archivedAreas' => $areas
                ->filter(fn (Area $area) => $area->isArchived())
                ->values()
                ->map(fn (Area $area) => [
                    'id' => $area->id,
                    'name' => $area->name,
                ]),
            'archivedProjects' => $active
                ->flatMap(fn (Area $area) => $area->projects
                    ->filter(fn (Project $project) => $project->isArchived())
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'areaName' => $area->name,
                    ]))
                ->values(),
        ]);
    }

    /**
     * Create an Area at the end of the current team's list.
     */
    public function store(SaveAreaRequest $request): RedirectResponse
    {
        $request->user()->currentTeam->areas()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area created.')]);

        return back();
    }

    /**
     * Rename an Area.
     */
    public function update(SaveAreaRequest $request, Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area renamed.')]);

        return back();
    }

    /**
     * Archive an Area. Its Projects stay with it and come back on restore.
     */
    public function archive(Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area archived.')]);

        return back();
    }

    /**
     * Restore an archived Area.
     */
    public function unarchive(Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $area->unarchive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Area restored.')]);

        return back();
    }
}
