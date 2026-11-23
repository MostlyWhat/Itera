<?php

namespace App\Priority;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Balanced (ITERA-08): urgent Tasks first, then the priority stack. Tasks
 * fall in three groups: overdue, due within SOON_DAYS days, and the rest.
 * Inside each group, the priority stack decides, then the deadline.
 */
class BalancedOrder implements TaskOrder
{
    /**
     * A deadline this many days away, or fewer, counts as "due soon".
     */
    public const SOON_DAYS = 3;

    public function apply(Builder $query): Builder
    {
        $today = today()->toDateString();
        $soon = today()->addDays(self::SOON_DAYS)->toDateString();

        return StackOrder::joinStack($query)
            ->orderByRaw(
                'case when tasks.due_date is null then 2 when date(tasks.due_date) < ? then 0 when date(tasks.due_date) <= ? then 1 else 2 end',
                [$today, $soon],
            )
            ->orderBy('areas.position')
            ->orderBy('areas.id')
            ->orderBy('projects.position')
            ->orderBy('projects.id')
            ->orderByRaw('case when tasks.due_date is null then 1 else 0 end')
            ->orderBy('tasks.due_date')
            ->orderBy('tasks.id');
    }

    public function sort(Collection $tasks): Collection
    {
        return $tasks
            ->sortBy(fn (Task $task) => [
                self::urgency($task),
                ...StackOrder::stackKey($task),
                $task->due_date === null,
                $task->due_date?->toDateString(),
                $task->id,
            ])
            ->values();
    }

    /**
     * The group of a Task: 0 overdue, 1 due soon, 2 the rest.
     */
    public static function urgency(Task $task): int
    {
        return match (true) {
            $task->due_date === null => 2,
            $task->due_date->lt(today()) => 0,
            $task->due_date->lte(today()->addDays(self::SOON_DAYS)) => 1,
            default => 2,
        };
    }
}
