<?php

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;

/*
 * ITERA-03: the data layer for Tasks.
 */

afterEach(fn () => Carbon::setTestNow());

test('a new task starts in to do without a completion date', function () {
    $task = Project::factory()->create()->tasks()->create(['title' => 'Read chapter 7']);

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->completed_at)->toBeNull();
});

test('story points are stored as the allowed values', function () {
    $task = Task::factory()->create(['story_points' => StoryPoints::Five]);

    expect($task->fresh()->story_points)->toBe(StoryPoints::Five)
        ->and(StoryPoints::values())->toBe([1, 2, 3, 5, 8]);
});

test('a task is overdue only when the deadline has passed and it is not done', function () {
    Carbon::setTestNow('2026-11-16 09:00');

    expect(Task::factory()->make(['due_date' => '2026-11-15'])->isOverdue())->toBeTrue()
        ->and(Task::factory()->make(['due_date' => '2026-11-16'])->isOverdue())->toBeFalse()
        ->and(Task::factory()->make(['due_date' => '2026-11-17'])->isOverdue())->toBeFalse()
        ->and(Task::factory()->make(['due_date' => null])->isOverdue())->toBeFalse()
        ->and(Task::factory()->done()->make(['due_date' => '2026-11-15'])->isOverdue())->toBeFalse();
});

test('the overdue scope matches isOverdue', function () {
    Carbon::setTestNow('2026-11-16 09:00');

    $late = Task::factory()->create(['due_date' => '2026-11-14']);
    Task::factory()->create(['due_date' => '2026-11-16']);
    Task::factory()->create(['due_date' => null]);
    Task::factory()->done()->create(['due_date' => '2026-11-14']);

    expect(Task::overdue()->pluck('id')->all())->toBe([$late->id]);
});

test('completed_at follows the status', function () {
    $task = Task::factory()->create();

    $task->update(['status' => TaskStatus::Done]);
    expect($task->fresh()->completed_at)->not->toBeNull();

    $task->update(['status' => TaskStatus::InProgress]);
    expect($task->fresh()->completed_at)->toBeNull();
});

test('project tasks list open tasks first, then by deadline', function () {
    $project = Project::factory()->create();

    $done = Task::factory()->for($project)->done()->create(['due_date' => '2026-11-01']);
    $noDate = Task::factory()->for($project)->create(['due_date' => null]);
    $later = Task::factory()->for($project)->create(['due_date' => '2026-11-20']);
    $sooner = Task::factory()->for($project)->create(['due_date' => '2026-11-18']);

    expect($project->tasks->pluck('id')->all())
        ->toBe([$sooner->id, $later->id, $noDate->id, $done->id]);
});

test('deleting a project deletes its tasks', function () {
    $task = Task::factory()->create();

    $task->project->delete();

    expect(Task::count())->toBe(0);
});
