<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Areas\SaveProjectRequest;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Projects inside an Area. ProjectPolicy checks the owner through the Area.
 */
class ProjectController extends Controller
{
    /**
     * Add a Project at the end of an Area's list, then open it.
     */
    public function store(SaveProjectRequest $request, Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        if ($area->isArchived()) {
            throw ValidationException::withMessages([
                'name' => __('Restore the area before you add a project to it.'),
            ]);
        }

        $project = $area->projects()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.show', ['project' => $project]);
    }

    /**
     * Rename a Project or change its description.
     */
    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project saved.')]);

        return back();
    }

    /**
     * Archive a Project.
     */
    public function archive(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project archived.')]);

        return back();
    }

    /**
     * Restore an archived Project.
     */
    public function unarchive(Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->unarchive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project restored.')]);

        return back();
    }
}
