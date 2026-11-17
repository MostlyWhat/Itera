<?php

namespace App\Actions\Sprints;

use App\Enums\TaskStatus;
use App\Models\Sprint;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Make the Tasks of a Sprint match a list of task ids.
 *
 * New Tasks get a commitment with a copy of their title and story points.
 * Tasks that are not in the list lose their commitment, except done Tasks:
 * they stay as the record of the finished work. The request
 * validation (SprintTaskRules) has already checked that each Task belongs to
 * the team and is not in another current or upcoming Sprint.
 */
class SyncSprintTasks
{
    /**
     * @param  list<int>  $taskIds
     */
    public function handle(Sprint $sprint, array $taskIds): void
    {
        DB::transaction(function () use ($sprint, $taskIds) {
            $sprint->commitments()
                ->whereNotIn('task_id', $taskIds)
                ->whereDoesntHave('task', fn ($tasks) => $tasks->where('status', TaskStatus::Done->value))
                ->delete();

            $existing = $sprint->commitments()->pluck('task_id')->all();

            Task::query()
                ->whereKey(array_diff($taskIds, $existing))
                ->get()
                ->each(fn (Task $task) => $sprint->commitments()->create([
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'committed_points' => $task->story_points?->value,
                ]));
        });
    }
}
