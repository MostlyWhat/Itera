<?php

namespace App\Models;

use App\Enums\ClosingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SprintCommitmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Task committed to one Sprint. The title and points are copies taken
 * when the Task is committed, so the sprint history does not change later.
 *
 * @property int $id
 * @property int $sprint_id
 * @property int|null $task_id
 * @property string $task_title
 * @property int|null $committed_points
 * @property ClosingStatus|null $closing_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Sprint $sprint
 * @property-read Task|null $task
 */
#[Fillable(['task_id', 'task_title', 'committed_points'])]
class SprintCommitment extends Model
{
    /** @use HasFactory<SprintCommitmentFactory> */
    use HasFactory;

    /**
     * Get the Sprint of the commitment.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the Task, if it still exists.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closing_status' => ClosingStatus::class,
        ];
    }
}
