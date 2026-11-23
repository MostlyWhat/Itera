<?php

namespace App\Http\Controllers\Areas;

use App\Enums\SortingMode;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\Team;
use App\Priority\BalancedOrder;
use App\Priority\TaskOrder;
use App\Queries\ProjectNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Priority page (ITERA-07): the user's priority stack of Areas and
 * Projects, next to the open Tasks in the order of the user's sorting mode
 * (ITERA-08). The user changes the stack or the mode and sees which Tasks
 * to do first.
 */
class PriorityController extends Controller
{
    public function __invoke(Request $request, ProjectNavigation $navigation, TaskOrder $order): Response
    {
        $team = $request->user()->currentTeam;

        $query = Task::query()
            ->where('tasks.status', '!=', TaskStatus::Done->value)
            ->whereHas('project', fn ($projects) => $projects->active()
                ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $team->id)));

        // All open Tasks, so overdue ones show in every mode.
        $tasks = $order->apply($query)
            ->with('project.area')
            ->get();

        // The Tasks that a current or upcoming Sprint already has.
        $planned = SprintCommitment::query()
            ->whereIn('task_id', $tasks->pluck('id'))
            ->whereHas('sprint', fn ($sprints) => $sprints->currentOrUpcoming())
            ->with('sprint')
            ->get()
            ->keyBy('task_id');

        return Inertia::render('priority/Index', [
            'areas' => $navigation->for($team)['areas'],
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status->value,
                'points' => $task->story_points?->value,
                'dueDate' => $task->due_date?->toDateString(),
                'isOverdue' => $task->isOverdue(),
                'isDueSoon' => ! $task->isOverdue() && BalancedOrder::urgency($task) === 1,
                'projectId' => $task->project->id,
                'projectName' => $task->project->name,
                'areaName' => $task->project->area->name,
                'sprintWeek' => $planned->get($task->id)?->sprint->week_start->toDateString(),
            ])->values(),
            'sortingMode' => $request->user()->sorting_mode->value,
            'sortingModes' => array_map(fn (SortingMode $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
            ], SortingMode::cases()),
            'nextSprint' => $this->nextSprint($team),
        ]);
    }

    /**
     * The Sprint to add Tasks to next: this week's Sprint, or else the
     * nearest upcoming one. Null when the team has neither.
     *
     * @return array{id: int, weekStart: string, current: bool}|null
     */
    private function nextSprint(Team $team): ?array
    {
        $sprint = $team->sprints()
            ->currentOrUpcoming()
            ->reorder('week_start')
            ->first();

        return $sprint === null ? null : [
            'id' => $sprint->id,
            'weekStart' => $sprint->week_start->toDateString(),
            'current' => $sprint->isCurrent(),
        ];
    }
}
