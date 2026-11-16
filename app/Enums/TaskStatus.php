<?php

namespace App\Enums;

/**
 * The column of a Task on the Kanban board.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }
}
