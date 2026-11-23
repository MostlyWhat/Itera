<?php

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\CreateWeeklyTasks;
use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Project;
use App\Models\RecurrenceTemplate;
use App\Models\Subtask;
use App\Models\Task;
use App\Queries\ProjectNavigation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        $tasks = $project->tasks()->inListOrder()->with(['subtasks', 'recurrenceTemplate'])->get();

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
                'isWeekly' => $task->recurrence_template_id !== null,
                'weeklyId' => $task->recurrence_template_id,
                'repeatsOn' => $task->recurrenceTemplate?->weekdayName(),
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
    public function store(SaveTaskRequest $request, Project $project, CreateWeeklyTasks $weekly): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->isArchived() || $project->area->isArchived()) {
            throw ValidationException::withMessages([
                'title' => __('Restore the project before you add a task to it.'),
            ]);
        }

        $weekday = $request->validated('repeat_weekday');

        if ($weekday !== null) {
            // A weekly task: save the template, then make this week's and
            // next week's Tasks from it.
            $template = $project->recurrenceTemplates()->create([
                ...$request->safe()->only(['title', 'description', 'story_points']),
                'weekday' => (int) $weekday,
            ]);
            $weekly->forTemplate($template);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Weekly task added. It repeats every :day.', ['day' => $template->weekdayName()])]);

            return back();
        }

        $project->tasks()->create($request->safe()->except('repeat_weekday'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task added.')]);

        return back();
    }

    /**
     * Edit a Task: title, description, story points, deadline, or status.
     *
     * A weekly Task also passes its title, description, and story points to
     * its template and to the open Tasks of later weeks. A one-off Task with
     * a repeat day becomes weekly: it moves to the next date on that day.
     */
    public function update(SaveTaskRequest $request, Task $task, CreateWeeklyTasks $weekly): RedirectResponse
    {
        Gate::authorize('update', $task);

        $fields = $request->safe()->except('repeat_weekday');
        $template = $task->recurrenceTemplate;
        $weekday = $request->validated('repeat_weekday');

        if ($template !== null) {
            DB::transaction(function () use ($task, $template, $fields): void {
                $task->update($fields);

                $shared = Arr::only($fields, ['title', 'description', 'story_points']);
                $template->update($shared);
                $template->tasks()
                    ->where('status', TaskStatus::Todo)
                    ->whereDate('recurrence_date', '>', $task->recurrence_date?->toDateString())
                    ->get()
                    ->each(fn (Task $later) => $later->update($shared));
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Task saved. Later weeks use the same details.')]);

            return back();
        }

        if ($weekday !== null) {
            $template = DB::transaction(function () use ($task, $fields, $weekday): RecurrenceTemplate {
                $task->fill($fields);

                $template = $task->project->recurrenceTemplates()->create([
                    'title' => $task->title,
                    'description' => $task->description,
                    'story_points' => $task->story_points,
                    'weekday' => (int) $weekday,
                ]);

                $today = CarbonImmutable::today();
                $date = $today->addDays(((int) $weekday - $today->dayOfWeek + 7) % 7);
                $task->recurrence_template_id = $template->id;
                $task->recurrence_date = $date;
                $task->due_date = $date;
                $task->save();

                return $template;
            });
            $weekly->forTemplate($template);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Task saved. It repeats every :day.', ['day' => $template->weekdayName()])]);

            return back();
        }

        $task->update($fields);

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
