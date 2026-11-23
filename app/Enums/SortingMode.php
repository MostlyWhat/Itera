<?php

namespace App\Enums;

use App\Priority\BalancedOrder;
use App\Priority\DeadlineOrder;
use App\Priority\StackOrder;
use App\Priority\TaskOrder;

/**
 * How the user's Tasks are sorted (FR-5, ITERA-08). Each mode names one
 * TaskOrder strategy.
 */
enum SortingMode: string
{
    case Stack = 'stack';
    case Deadline = 'deadline';
    case Balanced = 'balanced';

    /**
     * Get the display label for the mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stack => 'Stack order',
            self::Deadline => 'Deadline',
            self::Balanced => 'Balanced',
        };
    }

    /**
     * Get a short explanation of the mode for the user.
     */
    public function description(): string
    {
        return match ($this) {
            self::Stack => 'Your priority stack first: by area, then project, then deadline.',
            self::Deadline => 'The nearest deadline first. Tasks without a deadline come last.',
            self::Balanced => 'Overdue tasks and tasks due in the next '.BalancedOrder::SOON_DAYS.' days first, then your priority stack.',
        };
    }

    /**
     * Get the strategy that sorts Tasks in this mode.
     */
    public function strategy(): TaskOrder
    {
        return match ($this) {
            self::Stack => new StackOrder,
            self::Deadline => new DeadlineOrder,
            self::Balanced => new BalancedOrder,
        };
    }
}
