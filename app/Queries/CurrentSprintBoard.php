<?php

namespace App\Queries;

use App\Enums\TaskStatus;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\Team;

/**
 * The Kanban board of the team's Sprint for this week: the Sprint summary
 * and one card for each committed Task that still exists. The dashboard
 * shows it in To Do, In Progress, and Done columns.
 */
class CurrentSprintBoard
{
    /**
     * @return array{sprint: array<string, mixed>, cards: array<int, array<string, mixed>>, columns: list<array{status: string, label: string}>}|null
     */
    public function for(Team $team): ?array
    {
        $sprint = $team->sprints()
            ->whereDate('week_start', '<=', today())
            ->whereDate('week_end', '>=', today())
            ->with('commitments.task.project.area')
            ->first();

        if ($sprint === null) {
            return null;
        }

        return [
            'sprint' => $this->summary($sprint),
            'cards' => $sprint->commitments
                ->map(fn (SprintCommitment $commitment) => $commitment->task)
                ->filter()
                ->map(fn (Task $task) => [
                    'taskId' => $task->id,
                    'title' => $task->title,
                    'points' => $task->story_points?->value,
                    'status' => $task->status->value,
                    'dueDate' => $task->due_date?->toDateString(),
                    'isOverdue' => $task->isOverdue(),
                    'projectName' => $task->project->name,
                    'areaName' => $task->project->area->name,
                ])
                ->values()
                ->all(),
            'columns' => array_map(fn (TaskStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
            ], TaskStatus::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Sprint $sprint): array
    {
        return [
            'id' => $sprint->id,
            'weekStart' => $sprint->week_start->toDateString(),
            'weekEnd' => $sprint->week_end->toDateString(),
            'goal' => $sprint->goal,
            'targetPoints' => $sprint->target_points,
            'editable' => $sprint->isEditable(),
        ];
    }
}
