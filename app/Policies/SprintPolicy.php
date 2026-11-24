<?php

namespace App\Policies;

use App\Enums\SprintStatus;
use App\Models\Sprint;
use App\Models\User;

/**
 * Only members of the team that owns a Sprint can see or change it. A Sprint
 * can change only while it is open and its week has not ended. It can be
 * reviewed once, after its week ends. Its notes stay editable after that.
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

    /**
     * Determine whether the user can review the Sprint: its week has ended
     * and no review has completed it yet.
     */
    public function review(User $user, Sprint $sprint): bool
    {
        return $user->belongsToTeam($sprint->team) && $sprint->needsReview();
    }

    /**
     * Determine whether the user can edit the review of a reviewed Sprint:
     * its notes and the target of the next Sprint, not its results.
     */
    public function editReview(User $user, Sprint $sprint): bool
    {
        return $user->belongsToTeam($sprint->team) && $sprint->status === SprintStatus::Completed;
    }
}
