<?php

namespace App\Models;

use App\Concerns\Archivable;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An Area groups the Projects of one part of life, for example a class,
 * a job, or home. The team owns the Area.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['name'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use Archivable, HasFactory;

    /**
     * Put a new Area at the end of the team's list.
     */
    protected static function booted(): void
    {
        static::creating(function (Area $area) {
            $area->position = (int) static::where('team_id', $area->team_id)->max('position') + 1;
        });
    }

    /**
     * Get the team that owns the Area.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the Projects of the Area, in list order.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position');
    }
}
