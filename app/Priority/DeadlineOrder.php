<?php

namespace App\Priority;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Deadline (ITERA-08): the nearest deadline comes first, so overdue Tasks
 * lead. Tasks without a deadline come last. The priority stack breaks
 * ties between Tasks with the same deadline.
 */
class DeadlineOrder implements TaskOrder
{
    public function apply(Builder $query): Builder
    {
        return StackOrder::joinStack($query)
            ->orderByRaw('case when tasks.due_date is null then 1 else 0 end')
            ->orderBy('tasks.due_date')
            ->orderBy('areas.position')
            ->orderBy('areas.id')
            ->orderBy('projects.position')
            ->orderBy('projects.id')
            ->orderBy('tasks.id');
    }

    public function sort(Collection $tasks): Collection
    {
        return $tasks
            ->sortBy(fn (Task $task) => [
                $task->due_date === null,
                $task->due_date?->toDateString(),
                ...StackOrder::stackKey($task),
            ])
            ->values();
    }
}
