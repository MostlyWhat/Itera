<?php

namespace App\Policies;

use App\Models\Sprint;
use App\Models\User;

/**
 * Only members of the team that owns a Sprint can see or change it. A Sprint
 * can change only while it is open and its week has not ended.
 */
class SprintPolicy
{
    /**
     * Determine whether the user can view the Sprint.
     */
    public function view(User $user, Sprint $sprint): bool
    {
        return $user->belongsToTeam($sprint->team);
    }

    /**
     * Determine whether the user can change the goal, target, or tasks.
     */
    public function update(User $user, Sprint $sprint): bool
    {
        return $user->belongsToTeam($sprint->team) && $sprint->isEditable();
    }
}
