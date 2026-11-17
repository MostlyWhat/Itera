<?php

namespace App\Queries;

use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Team;

/**
 * The project list of a team, grouped by Area: the active Areas with their
 * active Projects and task counts, then the archived Areas and Projects.
 * The Projects page shows it next to the open Project.
 */
class ProjectNavigation
{
    /**
     * @return array{
     *     areas: array<int, array{id: int, name: string, projects: array<int, array{id: int, name: string, openTasks: int, overdueTasks: int}>}>,
     *     archivedAreas: array<int, array{id: int, name: string}>,
     *     archivedProjects: array<int, array{id: int, name: string, areaName: string}>
     * }
     */
    public function for(Team $team): array
    {
        $areas = $team->areas()
            ->with(['projects' => fn ($query) => $query->withCount([
                'tasks as open_tasks_count' => fn ($tasks) => $tasks->where('status', '!=', TaskStatus::Done->value),
                'tasks as overdue_tasks_count' => fn ($tasks) => $tasks->overdue(),
            ])])
            ->get();

        $active = $areas->reject(fn (Area $area) => $area->isArchived())->values();

        return [
            'areas' => $active->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'projects' => $area->projects
                    ->reject(fn (Project $project) => $project->isArchived())
                    ->values()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'openTasks' => (int) $project->getAttribute('open_tasks_count'),
                        'overdueTasks' => (int) $project->getAttribute('overdue_tasks_count'),
                    ])
                    ->all(),
            ])->all(),
            'archivedAreas' => $areas
                ->filter(fn (Area $area) => $area->isArchived())
                ->values()
                ->map(fn (Area $area) => ['id' => $area->id, 'name' => $area->name])
                ->all(),
            'archivedProjects' => $active
                ->flatMap(fn (Area $area) => $area->projects
                    ->filter(fn (Project $project) => $project->isArchived())
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'areaName' => $area->name,
                    ]))
                ->values()
                ->all(),
        ];
    }
}
