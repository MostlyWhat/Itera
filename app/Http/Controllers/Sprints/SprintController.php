<?php

namespace App\Http\Controllers\Sprints;

use App\Actions\Sprints\SyncSprintTasks;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\StoreSprintRequest;
use App\Http\Requests\Sprints\UpdateSprintRequest;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
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
    /**
     * List the Sprints of the current team, and the weeks that are free to plan.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $sprints = $team->sprints()->with('commitments')->get();

        return Inertia::render('sprints/Index', [
            'sprints' => $sprints->map(fn (Sprint $sprint) => $this->summary($sprint)),
            'freeWeeks' => $this->freeWeeks($sprints),
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

            $syncTasks->handle($sprint, $request->validated('task_ids'));

            return $sprint;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint created.')]);

        return to_route('sprints.show', ['sprint' => $sprint]);
    }

    /**
     * Show the plan of one Sprint, with the Tasks it can still take.
     */
    public function show(Sprint $sprint): Response
    {
        Gate::authorize('view', $sprint);

        $sprint->load('commitments.task.project.area');

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
            ]),
            'candidates' => $sprint->isEditable() ? $this->candidates($sprint) : [],
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
            $syncTasks->handle($sprint, $request->validated('task_ids'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sprint saved.')]);

        return back();
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

        return Task::query()
            ->where('status', '!=', TaskStatus::Done->value)
            ->whereHas('project', fn ($projects) => $projects->active()
                ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $sprint->team_id)))
            ->with('project.area')
            ->inListOrder()
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
            ])
            ->values()
            ->all();
    }

    /**
     * The next four Mondays, from this week, that have no Sprint yet.
     *
     * @param  iterable<Sprint>  $sprints
     * @return array<int, string>
     */
    private function freeWeeks(iterable $sprints): array
    {
        $planned = collect($sprints)->map(fn (Sprint $sprint) => $sprint->week_start->toDateString());
        $monday = CarbonImmutable::today()->startOfWeek();

        return collect(range(0, 7))
            ->map(fn (int $week) => $monday->addWeeks($week)->toDateString())
            ->reject(fn (string $date) => $planned->contains($date))
            ->take(4)
            ->values()
            ->all();
    }
}
