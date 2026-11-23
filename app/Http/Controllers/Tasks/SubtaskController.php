<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subtasks\SaveSubtaskRequest;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The Subtasks of a Task (ITERA-06). A Subtask only shows progress. It never
 * changes the story points or the status of its parent Task.
 */
class SubtaskController extends Controller
{
    /**
     * Add a Subtask at the end of the Task's list.
     */
    public function store(SaveSubtaskRequest $request, Task $task): RedirectResponse
    {
        if ($task->project->isArchived() || $task->project->area->isArchived()) {
            throw ValidationException::withMessages([
                'title' => __('Restore the project before you add a subtask.'),
            ]);
        }

        $task->subtasks()->create([
            ...$request->validated(),
            'position' => (int) $task->subtasks()->max('position') + 1,
        ]);

        return back();
    }

    /**
     * Rename a Subtask, or mark it done or not done.
     */
    public function update(SaveSubtaskRequest $request, Subtask $subtask): RedirectResponse
    {
        $subtask->update($request->validated());

        return back();
    }

    /**
     * Delete a Subtask.
     */
    public function destroy(Subtask $subtask): RedirectResponse
    {
        Gate::authorize('update', $subtask->task);

        $subtask->delete();

        return back();
    }
}
