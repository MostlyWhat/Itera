<?php

namespace App\Models;

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of work inside a Project, for example "Write the method section".
 * The parent Task holds the story points and the deadline.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property TaskStatus $status
 * @property StoryPoints|null $story_points
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['title', 'status', 'story_points', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * The default values of new Tasks.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
    ];

    /**
     * Keep `completed_at` in step with the status. It records when the work
     * was done, for the board and the sprint review.
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if (! $task->isDirty('status')) {
                return;
            }

            $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
        });
    }

    /**
     * Get the Project of the Task.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Determine if the deadline has passed and the Task is not done.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== TaskStatus::Done
            && $this->due_date->lt(today());
    }

    /**
     * Scope the query to Tasks whose deadline has passed and that are not done.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('status', '!=', TaskStatus::Done->value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'story_points' => StoryPoints::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
