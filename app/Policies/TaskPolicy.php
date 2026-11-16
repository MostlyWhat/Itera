<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * A Task gets its owner from its Project and Area. Only members of that team
 * can see or change the Task.
 */
class TaskPolicy
{
    /**
     * Determine whether the user can edit or delete the Task.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->belongsToTeam($task->project->area->team);
    }
}
