<?php

namespace App\Enums;

/**
 * The allowed story point values of a parent Task: the Fibonacci scale
 * from 1 to 21. A larger number means more effort, not more hours. A task
 * above 8 is a sign to split it into smaller tasks.
 */
enum StoryPoints: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Five = 5;
    case Eight = 8;
    case Thirteen = 13;
    case TwentyOne = 21;

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
