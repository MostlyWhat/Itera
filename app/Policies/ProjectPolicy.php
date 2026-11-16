<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * A Project gets its owner from its Area. Only members of that team
 * can see or change the Project.
 */
class ProjectPolicy
{
    /**
     * Determine whether the user can view the Project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->area->team);
    }

    /**
     * Determine whether the user can rename or archive the Project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->area->team);
    }
}
