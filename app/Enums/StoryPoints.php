<?php

namespace App\Enums;

/**
 * The allowed story point values of a parent Task: a short Fibonacci scale.
 * A larger number means more effort, not more hours.
 */
enum StoryPoints: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Five = 5;
    case Eight = 8;

    /**
     * Get all allowed values, for validation and for the form.
     *
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(fn (self $points) => $points->value, self::cases());
    }
}
