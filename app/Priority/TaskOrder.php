<?php

namespace App\Priority;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One way to put Tasks in the order of what to do first (FR-5). Each
 * method uses this interface (Strategy pattern), so the backlog, the
 * board, and the Priority page do not change when the user picks another
 * method. ITERA-07 adds Stack Order. ITERA-08 adds Deadline and Balanced.
 */
interface TaskOrder
{
    /**
     * Sort a Task query in this order.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function apply(Builder $query): Builder;

    /**
     * Sort Tasks that are already loaded. Each Task needs its Project and
     * Area.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    public function sort(Collection $tasks): Collection;
}
