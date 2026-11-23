<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One small step of a parent Task, for example "Find three sources". A
 * Subtask has no story points. Done Subtasks only show how much of the
 * parent Task is finished.
 *
 * @property int $id
 * @property int $task_id
 * @property string $title
 * @property bool $completed
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 */
#[Fillable(['title', 'completed', 'position'])]
class Subtask extends Model
{
    /** @use HasFactory<SubtaskFactory> */
    use HasFactory;

    /**
     * The default values of new Subtasks.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'completed' => false,
        'position' => 0,
    ];

    /**
     * Get the parent Task of the Subtask.
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
            'completed' => 'boolean',
            'position' => 'integer',
        ];
    }
}
