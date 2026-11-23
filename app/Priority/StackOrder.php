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
        return $query
            ->select('tasks.*')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->join('areas', 'areas.id', '=', 'projects.area_id')
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
            ->sortBy([
                fn (Task $a, Task $b) => [$a->project->area->position, $a->project->area->id]
                    <=> [$b->project->area->position, $b->project->area->id],
                fn (Task $a, Task $b) => [$a->project->position, $a->project->id]
                    <=> [$b->project->position, $b->project->id],
                fn (Task $a, Task $b) => [$a->due_date === null, $a->due_date?->toDateString(), $a->id]
                    <=> [$b->due_date === null, $b->due_date?->toDateString(), $b->id],
            ])
            ->values();
    }
}
