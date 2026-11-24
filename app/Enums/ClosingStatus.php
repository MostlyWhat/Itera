<?php

namespace App\Enums;

/**
 * What happened to a committed Task when its Sprint was reviewed (ITERA-10).
 * A done Task closes as Done. The user chooses for each unfinished Task:
 * plan it again in the next Sprint, or send it back to the backlog.
 */
enum ClosingStatus: string
{
    case Done = 'done';
    case CarriedOver = 'carried_over';
    case Dropped = 'dropped';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Done => 'Done',
            self::CarriedOver => 'Planned again',
            self::Dropped => 'Back to backlog',
        };
    }
}
