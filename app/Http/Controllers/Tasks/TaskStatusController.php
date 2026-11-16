<?php

namespace App\Http\Controllers\Tasks;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\UpdateTaskStatusRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

/**
 * Move a Task to another column of the Kanban board. The status is saved,
 * so the board shows the same columns after a reload.
 */
class TaskStatusController extends Controller
{
    public function __invoke(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        $task->update(['status' => $request->enum('status', TaskStatus::class)]);

        return back();
    }
}
