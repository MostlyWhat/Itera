<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

/**
 * Only members of the team that owns an Area can see or change it.
 * Each user has a personal team, so a personal Area is private.
 */
class AreaPolicy
{
    /**
     * Determine whether the user can view the Area.
     */
    public function view(User $user, Area $area): bool
    {
        return $user->belongsToTeam($area->team);
    }

    /**
     * Determine whether the user can rename, archive, or add Projects to the Area.
     */
    public function update(User $user, Area $area): bool
    {
        return $user->belongsToTeam($area->team);
    }
}
