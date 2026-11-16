<?php

namespace App\Http\Requests\Sprints;

use App\Enums\TaskStatus;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Validation\Validator;

/**
 * Shared rules for the goal, the story point target, and the chosen Tasks of
 * a Sprint.
 */
trait SprintTaskRules
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function planRules(): array
    {
        return [
            'goal' => ['nullable', 'string', 'max:200'],
            'target_points' => ['nullable', 'integer', 'min:1', 'max:999'],
            'task_ids' => ['array'],
            'task_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Check that each chosen Task belongs to the team, is not done, and is not
     * in another current or upcoming Sprint.
     */
    protected function checkTasks(Validator $validator, Team $team, ?Sprint $sprint): void
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', (array) $this->input('task_ids', []));

        if ($ids === [] || $validator->errors()->isNotEmpty()) {
            return;
        }

        $teamTasks = Task::query()
            ->whereKey($ids)
            ->whereHas('project.area', fn ($areas) => $areas->where('team_id', $team->id))
            ->get();

        if ($teamTasks->count() !== count($ids)) {
            $validator->errors()->add('task_ids', __('Choose tasks from your own projects.'));

            return;
        }

        if ($teamTasks->contains(fn (Task $task) => $task->status === TaskStatus::Done)) {
            $validator->errors()->add('task_ids', __('Done tasks cannot go into a sprint.'));

            return;
        }

        $available = Task::query()->whereKey($ids)->availableForSprint($sprint)->pluck('id');
        $taken = $teamTasks->reject(fn (Task $task) => $available->contains($task->id));

        if ($taken->isNotEmpty()) {
            $validator->errors()->add('task_ids', trans_choice(
                ':titles is already in another current or upcoming sprint.|:titles are already in another current or upcoming sprint.',
                $taken->count(),
                ['titles' => $taken->pluck('title')->join(', ', ' and ')],
            ));
        }
    }

    /**
     * Prepare the data for validation. Empty form fields mean "no value".
     * Only the fields in the request change, so the planning page can save
     * the goal and the tasks separately.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->exists('goal')) {
            $data['goal'] = $this->filled('goal') ? trim((string) $this->input('goal')) : null;
        }

        if ($this->exists('target_points')) {
            $data['target_points'] = $this->filled('target_points') ? $this->input('target_points') : null;
        }

        if ($this->exists('task_ids')) {
            $data['task_ids'] = $this->input('task_ids') ?? [];
        }

        $this->merge($data);
    }
}
