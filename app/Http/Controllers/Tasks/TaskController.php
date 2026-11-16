<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
     */
    public function projects(Request $request): RedirectResponse
    {
        $first = $this->navigation($request->user()->currentTeam)
            ->flatMap(fn (array $area) => $area['projects'])
            ->first();

        if ($first === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Create an area and a project first.')]);

            return to_route('areas.index');
        }

        return to_route('projects.show', ['project' => $first['id']]);
    }

    /**
     * Show a Project with its Tasks, next to the list of all Projects.
     */
    public function index(Project $project): Response
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()->inListOrder()->get();

        return Inertia::render('projects/Show', [
            'navigation' => $this->navigation($project->area->team),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'areaName' => $project->area->name,
                'archived' => $project->isArchived() || $project->area->isArchived(),
            ],
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'statusLabel' => $task->status->label(),
                'storyPoints' => $task->story_points?->value,
                'dueDate' => $task->due_date?->toDateString(),
                'isOverdue' => $task->isOverdue(),
            ]),
            'storyPointOptions' => StoryPoints::values(),
            'statusOptions' => collect(TaskStatus::cases())->map(fn (TaskStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /**
     * The active Areas of a team with their active Projects and task counts,
     * for the project selector.
     *
     * @return Collection<int, array{id: int, name: string, projects: array<int, array{id: int, name: string, openTasks: int, overdueTasks: int}>}>
     */
    private function navigation(Team $team): Collection
    {
        return $team->areas()
            ->active()
            ->with(['projects' => fn ($query) => $query->active()->withCount([
                'tasks as open_tasks_count' => fn ($tasks) => $tasks->where('status', '!=', TaskStatus::Done->value),
                'tasks as overdue_tasks_count' => fn ($tasks) => $tasks->overdue(),
            ])])
            ->get()
            ->filter(fn (Area $area) => $area->projects->isNotEmpty())
            ->values()
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'projects' => $area->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'openTasks' => (int) $project->getAttribute('open_tasks_count'),
                    'overdueTasks' => (int) $project->getAttribute('overdue_tasks_count'),
                ])->values()->all(),
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
     * Edit a Task: title, story points, deadline, or status.
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
