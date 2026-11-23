<?php

namespace App\Models;

use App\Enums\StoryPoints;
use Carbon\CarbonImmutable;
use Database\Factories\RecurrenceTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly task (ITERA-09), for example "Do the weekly quiz" every Monday.
 * Itera copies it into a new Task for each week. Each copy has its own
 * status, so finishing one week does not finish the next.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property int $weekday
 * @property StoryPoints|null $story_points
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Collection<int, Task> $tasks
 */
#[Fillable(['title', 'description', 'weekday', 'story_points'])]
class RecurrenceTemplate extends Model
{
    /** @use HasFactory<RecurrenceTemplateFactory> */
    use HasFactory;

    /**
     * The weekday names, from 0 (Sunday) to 6 (Saturday).
     */
    public const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Get the Project of the weekly task.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the Tasks made from this weekly task.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the name of the weekday, for example "Monday".
     */
    public function weekdayName(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? 'Monday';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'story_points' => StoryPoints::class,
        ];
    }
}
