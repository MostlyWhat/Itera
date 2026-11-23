<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Queries\ProjectNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tasks inside a Project. ProjectPolicy and TaskPolicy check the owner
 * through the Project's Area and team.
 */
class TaskController extends Controller
{
    /**
     * Open the Projects page on the first active Project of the current team.
     * Without a Project, show the empty page, where the user creates the first one.
     */
    public function projects(Request $request, ProjectNavigation $navigation): RedirectResponse|Response
    {
        $list = $navigation->for($request->user()->currentTeam);
        $first = collect($list['areas'])->flatMap(fn (array $area) => $area['projects'])->first();

        if ($first === null) {
            return Inertia::render('projects/Index', ['navigation' => $list]);
        }

        return to_route('projects.show', ['project' => $first['id']]);
    }

    /**
     * Show a Project with its Tasks, next to the list of all Projects.
     */
    public function index(Project $project, ProjectNavigation $navigation): Response
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()->inListOrder()->with('subtasks')->get();

        return Inertia::render('projects/Show', [
            'navigation' => $navigation->for($project->area->team),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'areaName' => $project->area->name,
                'archived' => $project->isArchived() || $project->area->isArchived(),
            ],
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status->value,
                'statusLabel' => $task->status->label(),
                'storyPoints' => $task->story_points?->value,
                'dueDate' => $task->due_date?->toDateString(),
                'isOverdue' => $task->isOverdue(),
                'progress' => $task->subtaskProgress(),
                'subtasks' => $task->subtasks->map(fn (Subtask $subtask) => [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'completed' => $subtask->completed,
                ])->values()->all(),
            ]),
            'storyPointOptions' => StoryPoints::values(),
            'statusOptions' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /**
     * Add a Task to a Project.
     */
    public function store(SaveTaskRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->isArchived() || $project->area->isArchived()) {
            throw ValidationException::withMessages([
                'title' => __('Restore the project before you add a task to it.'),
            ]);
        }

        $project->tasks()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task added.')]);

        return back();
    }

    /**
     * Edit a Task: title, description, story points, deadline, or status.
     */
    public function update(SaveTaskRequest $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task saved.')]);

        return back();
    }

    /**
     * Delete a Task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return back();
    }
}
