<?php

namespace App\Http\Controllers\Areas;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\Team;
use App\Priority\TaskOrder;
use App\Queries\ProjectNavigation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Priority page (ITERA-07): the user's priority stack of Areas and
 * Projects, next to the open Tasks in the order that the stack gives. The
 * user changes the stack and sees which Tasks to do first.
 */
class PriorityController extends Controller
{
    /**
     * The number of Tasks the page lists.
     */
    private const TASK_LIMIT = 50;

    public function __invoke(Request $request, ProjectNavigation $navigation, TaskOrder $order): Response
    {
        $team = $request->user()->currentTeam;

        $query = Task::query()
            ->where('tasks.status', '!=', TaskStatus::Done->value)
            ->whereHas('project', fn ($projects) => $projects->active()
                ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $team->id)));

        $total = (clone $query)->count();

        $tasks = $order->apply($query)
            ->with('project.area')
            ->limit(self::TASK_LIMIT)
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
                'projectId' => $task->project->id,
                'projectName' => $task->project->name,
                'areaName' => $task->project->area->name,
                'sprintWeek' => $planned->get($task->id)?->sprint->week_start->toDateString(),
            ])->values(),
            'taskCount' => $total,
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
