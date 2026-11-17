<?php

namespace App\Models;

use App\Enums\SprintStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One week of planned work for a team (seven days from `week_start`), with a goal, a
 * story point target, and the Tasks committed to it.
 *
 * @property int $id
 * @property int $team_id
 * @property CarbonImmutable $week_start
 * @property CarbonImmutable $week_end
 * @property string|null $goal
 * @property int|null $target_points
 * @property SprintStatus $status
 * @property int|null $completed_points
 * @property CarbonImmutable|null $review_completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, SprintCommitment> $commitments
 */
#[Fillable(['week_start', 'goal', 'target_points'])]
class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory;

    /**
     * The default values of new Sprints.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * The week always ends six days after `week_start`. The model sets it,
     * so a form can never save a week of another length.
     */
    protected static function booted(): void
    {
        static::saving(function (Sprint $sprint) {
            $sprint->week_end = $sprint->week_start->addDays(6);
        });
    }

    /**
     * Get the team of the Sprint.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the Tasks committed to the Sprint.
     *
     * @return HasMany<SprintCommitment, $this>
     */
    public function commitments(): HasMany
    {
        return $this->hasMany(SprintCommitment::class);
    }

    /**
     * Determine if today falls inside the Sprint week.
     */
    public function isCurrent(): bool
    {
        return today()->betweenIncluded($this->week_start, $this->week_end);
    }

    /**
     * Determine if the Sprint week starts after today.
     */
    public function isUpcoming(): bool
    {
        return $this->week_start->isAfter(today());
    }

    /**
     * Determine if the Sprint week has ended.
     */
    public function isPast(): bool
    {
        return $this->week_end->isBefore(today());
    }

    /**
     * Determine if the plan can still change: the Sprint is open and its week
     * has not ended.
     */
    public function isEditable(): bool
    {
        return $this->status === SprintStatus::Open && ! $this->isPast();
    }

    /**
     * Get the sum of the committed story points.
     */
    public function committedPoints(): int
    {
        return (int) $this->commitments->sum('committed_points');
    }

    /**
     * Scope the query to open Sprints whose week has not ended. A Task can be
     * in only one of these at a time.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCurrentOrUpcoming(Builder $query): void
    {
        $query->where('status', SprintStatus::Open->value)
            ->whereDate('week_end', '>=', today());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'immutable_date',
            'week_end' => 'immutable_date',
            'status' => SprintStatus::class,
            'review_completed_at' => 'immutable_datetime',
        ];
    }
}
