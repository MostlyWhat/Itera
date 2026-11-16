<?php

namespace App\Models;

use App\Concerns\Archivable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Project belongs to one Area and holds the Tasks for one goal,
 * for example "Lab report" in the Area "Biology 201".
 *
 * @property int $id
 * @property int $area_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Area $area
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable(['name', 'description'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use Archivable, HasFactory;

    /**
     * Put a new Project at the end of the Area's list.
     */
    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->position = (int) static::where('area_id', $project->area_id)->max('position') + 1;
        });
    }

    /**
     * Get the Area of the Project.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Get the Tasks of the Project. Use the `inListOrder` scope to sort them.
     * The relation has no order, so count queries also work on PostgreSQL.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
