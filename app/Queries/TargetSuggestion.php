<?php

namespace App\Queries;

use App\Enums\SprintStatus;
use App\Models\Sprint;

/**
 * A story point target for the next Sprint, from the points that the team
 * really completed (ITERA-10). It is the average of the completed points of
 * the last SPRINTS reviewed Sprints, up to and including the one under review.
 */
class TargetSuggestion
{
    /**
     * The number of past Sprints in the average.
     */
    public const SPRINTS = 3;

    /**
     * @param  int|null  $completed  The completed points of `$sprint` before its review saves them.
     * @return array{points: int, sprints: int, average: float}|null
     */
    public function for(Sprint $sprint, ?int $completed = null): ?array
    {
        $earlier = $sprint->team->sprints()
            ->where('status', SprintStatus::Completed->value)
            ->whereKeyNot($sprint->id)
            ->whereDate('week_start', '<', $sprint->week_start->toDateString())
            ->reorder('week_start', 'desc')
            ->limit(self::SPRINTS - 1)
            ->pluck('completed_points')
            ->filter(fn ($points) => $points !== null)
            ->map(fn ($points) => (int) $points);

        $points = $earlier->prepend($completed ?? (int) $sprint->completed_points)->values();

        if ($points->sum() === 0) {
            return null;
        }

        $average = $points->avg();

        return [
            'points' => max(1, (int) round($average)),
            'sprints' => $points->count(),
            'average' => round($average, 1),
        ];
    }
}
