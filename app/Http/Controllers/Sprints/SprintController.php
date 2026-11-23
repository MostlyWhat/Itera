<?php

namespace App\Http\Controllers\Sprints;

use App\Actions\Sprints\SyncSprintTasks;
use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\StoreSprintRequest;
use App\Http\Requests\Sprints\StoreSprintTaskRequest;
use App\Http\Requests\Sprints\UpdateSprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Priority\TaskOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Weekly Sprints of the current team: list them, create one for a Monday,
 * and plan its goal, story point target, and Tasks.
 */
class SprintController extends Controller
{
    public function __construct(private readonly TaskOrder $order) {}

    /**
     * List the Sprints of the current team, and the weeks that are free to plan.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sprints = $team->sprints()->with('commitments')->get();

        return Inertia::render('sprints/Index', [
            'sprints' => $sprints->map(fn (Sprint $sprint) => $this->summary($sprint)),
            'freeWeeks' => $this->freeWeeks($sprints, $request->user()),
        ]);
    }

    /**
     * Create a Sprint and commit the chosen Tasks to it.
     */
    public function store(StoreSprintRequest $request, SyncSprintTasks $syncTasks): RedirectResponse
    {
        $sprint = DB::transaction(function () use ($request, $syncTasks) {
            $sprint = $request->user()->currentTeam->sprints()->create($request->safe()->only([
                'week_start', 'goal', 'target_points',
            ]));

            $syncTasks->handle($sprint, $request->validated('task_ids', []));

            return $sprint;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint created.')]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    /**
     * Show the plan of one Sprint, with the Tasks it can still take.
     */
    public function show(Request $request, Sprint $sprint): Response
    {
        Gate::authorize('view', $sprint);

        $sprint->load(['commitments.task.project.area', 'commitments.task.subtasks', 'commitments.task.recurrenceTemplate']);

        return Inertia::render('sprints/Show', [
            'sprint' => [
                ...$this->summary($sprint),
                'editable' => $sprint->isEditable(),
            ],
            'commitments' => $sprint->commitments->map(fn (SprintCommitment $commitment) => [
                'id' => $commitment->id,
                'taskId' => $commitment->task_id,
                'title' => $commitment->task_title,
                'points' => $commitment->committed_points,
                'projectName' => $commitment->task?->project->name,
                'status' => $commitment->task?->status->value,
                'details' => $commitment->task === null ? null : $this->details($commitment->task),
            ]),
            'candidates' => $sprint->isEditable() ? $this->candidates($sprint) : [],
            'projects' => $sprint->isEditable() ? $this->projects($sprint) : [],
            'storyPointOptions' => StoryPoints::values(),
            'sortingLabel' => $request->user()->sorting_mode->label(),
        ]);
    }

    /**
     * Change the goal, the target, or the Tasks of a Sprint.
     */
    public function update(UpdateSprintRequest $request, Sprint $sprint, SyncSprintTasks $syncTasks): RedirectResponse
    {
        Gate::authorize('update', $sprint);

        DB::transaction(function () use ($request, $sprint, $syncTasks) {
            $sprint->update($request->safe()->only(['goal', 'target_points']));

            if ($request->has('task_ids')) {
                $syncTasks->handle($sprint, $request->validated('task_ids', []));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint saved.')]);

        return back();
    }

    /**
     * Create a new Task in one of the team's Projects and add it to the Sprint.
     */
    public function addTask(StoreSprintTaskRequest $request, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('update', $sprint);

        DB::transaction(function () use ($request, $sprint) {
            $task = Project::query()
                ->whereKey($request->validated('project_id'))
                ->firstOrFail()
                ->tasks()
                ->create($request->safe()->only(['title', 'story_points', 'due_date']));

            $sprint->commitments()->create([
                'task_id' => $task->id,
                'task_title' => $task->title,
                'committed_points' => $task->story_points?->value,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task added to the sprint.')]);

        return back();
    }

    /**
     * The details of a Task for its dialog: the description, the deadline,
     * and the Subtasks with their progress.
     *
     * @return array<string, mixed>
     */
    private function details(Task $task): array
    {
        return [
            'taskId' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'statusLabel' => $task->status->label(),
            'points' => $task->story_points?->value,
            'dueDate' => $task->due_date?->toDateString(),
            'isOverdue' => $task->isOverdue(),
            'repeatsOn' => $task->recurrenceTemplate?->weekdayName(),
            'projectId' => $task->project->id,
            'projectName' => $task->project->name,
            'areaName' => $task->project->area->name,
            'progress' => $task->subtaskProgress(),
            'subtasks' => $task->subtasks->map(fn (Subtask $subtask) => [
                'id' => $subtask->id,
                'title' => $subtask->title,
                'completed' => $subtask->completed,
            ])->values()->all(),
        ];
    }

    /**
     * The fields that the list and the plan both show.
     *
     * @return array<string, mixed>
     */
    private function summary(Sprint $sprint): array
    {
        return [
            'id' => $sprint->id,
            'weekStart' => $sprint->week_start->toDateString(),
            'weekEnd' => $sprint->week_end->toDateString(),
            'goal' => $sprint->goal,
            'targetPoints' => $sprint->target_points,
            'committedPoints' => $sprint->committedPoints(),
            'taskCount' => $sprint->commitments->count(),
            'timing' => match (true) {
                $sprint->isCurrent() => 'current',
                $sprint->isUpcoming() => 'upcoming',
                default => 'past',
            },
        ];
    }

    /**
     * The open Tasks of the team's active Projects. Each one says if another
     * current or upcoming Sprint already has it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function candidates(Sprint $sprint): array
    {
        $taken = SprintCommitment::query()
            ->whereNotNull('task_id')
            ->where('sprint_id', '!=', $sprint->id)
            ->whereHas('sprint', fn ($sprints) => $sprints->currentOrUpcoming()->where('team_id', $sprint->team_id))
            ->with('sprint')
            ->get()
            ->keyBy('task_id');

        // The backlog follows the priority stack, so the tasks to pick first
        // are at the top.
        return $this->order->apply(Task::query()
            ->where('tasks.status', '!=', TaskStatus::Done->value)
            ->whereHas('project', fn ($projects) => $projects->active()
                ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $sprint->team_id))))
            ->with(['project.area', 'subtasks', 'recurrenceTemplate'])
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'points' => $task->story_points?->value,
                'dueDate' => $task->due_date?->toDateString(),
                'isOverdue' => $task->isOverdue(),
                'projectName' => $task->project->name,
                'areaName' => $task->project->area->name,
                'takenBy' => $taken->get($task->id)?->sprint->week_start->toDateString(),
                'details' => $this->details($task),
            ])
            ->values()
            ->all();
    }

    /**
     * The active Projects of the team, for the "new task" form.
     *
     * @return array<int, array{id: int, name: string, areaName: string}>
     */
    private function projects(Sprint $sprint): array
    {
        return Project::query()
            ->active()
            ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $sprint->team_id))
            ->with('area')
            ->get()
            ->sortBy(fn (Project $project) => [$project->area->position, $project->position])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'areaName' => $project->area->name,
            ])
            ->values()
            ->all();
    }

    /**
     * The first days of the next four weeks, from this week, that no Sprint
     * covers. A week starts on the user's own first day of the week.
     *
     * @param  iterable<Sprint>  $sprints
     * @return array<int, string>
     */
    private function freeWeeks(iterable $sprints, User $user): array
    {
        $planned = collect($sprints);
        $first = $user->startOfWeek();

        return collect(range(0, 7))
            ->map(fn (int $week) => $first->addWeeks($week))
            ->reject(fn (CarbonImmutable $start) => $planned->contains(
                fn (Sprint $sprint) => $sprint->week_start->lte($start->addDays(6)) && $sprint->week_end->gte($start),
            ))
            ->take(4)
            ->map(fn (CarbonImmutable $start) => $start->toDateString())
            ->values()
            ->all();
    }
}
