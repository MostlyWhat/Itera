<?php

namespace App\Priority;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Stack Order (ITERA-07): Tasks follow the user's priority stack. Tasks of
 * the top Area come first, and inside an Area the top Project comes first.
 * Inside one Project, the nearest deadline comes first, and Tasks without
 * a deadline come last.
 */
class StackOrder implements TaskOrder
{
    public function apply(Builder $query): Builder
    {
        return self::joinStack($query)
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
                ...self::stackKey($task),
                $task->due_date === null,
                $task->due_date?->toDateString(),
                $task->id,
            ])
            ->values();
    }

    /**
     * Join the Project and the Area of each Task, so every strategy can sort
     * by the priority stack.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public static function joinStack(Builder $query): Builder
    {
        return $query
            ->select('tasks.*')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->join('areas', 'areas.id', '=', 'projects.area_id');
    }

    /**
     * The place of a Task in the priority stack, as values to compare. The
     * Task needs its Project and Area.
     *
     * @return list<int>
     */
    public static function stackKey(Task $task): array
    {
        return [
            $task->project->area->position,
            $task->project->area->id,
            $task->project->position,
            $task->project->id,
        ];
    }
}
