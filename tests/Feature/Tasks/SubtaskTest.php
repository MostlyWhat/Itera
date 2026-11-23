<?php

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-06: users divide a Task into optional Subtasks and see the percent
 * complete. The story points stay on the parent Task, and only a done parent
 * Task counts them.
 */

afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with one Task of 5 story points.
 *
 * @return array{0: User, 1: Task, 2: string}
 */
function userWithSubtaskParent(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create();
    $task = Task::factory()->for($project)->create(['title' => 'Write the essay', 'story_points' => StoryPoints::Five]);

    return [$user, $task, $user->currentTeam->slug];
}

test('a task has no subtasks and no progress by default', function () {
    [$user, $task, $slug] = userWithSubtaskParent();

    $this->actingAs($user)
        ->get(route('projects.show', [$slug, $task->project]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tasks.0.subtasks', [])
            ->where('tasks.0.progress', null));
});

test('users can add subtasks under a task, in order', function () {
    [$user, $task, $slug] = userWithSubtaskParent();

    $this->actingAs($user)
        ->post(route('subtasks.store', [$slug, $task]), ['title' => '  Find three sources  '])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('subtasks.store', [$slug, $task]), ['title' => 'Write the outline']);

    expect($task->subtasks()->pluck('title')->all())->toBe(['Find three sources', 'Write the outline'])
        ->and($task->subtasks()->pluck('position')->all())->toBe([1, 2])
        ->and($task->subtasks()->first()->completed)->toBeFalse();
});

test('a subtask needs a short title', function (string $title) {
    [$user, $task, $slug] = userWithSubtaskParent();

    $this->actingAs($user)
        ->post(route('subtasks.store', [$slug, $task]), ['title' => $title])
        ->assertSessionHasErrors('title');

    expect($task->subtasks()->count())->toBe(0);
})->with([
    'empty' => [''],
    'only spaces' => ['   '],
    'too long' => [str_repeat('a', 201)],
]);

test('users can mark a subtask done, rename it, and delete it', function () {
    [$user, $task, $slug] = userWithSubtaskParent();
    $subtask = Subtask::factory()->for($task)->create(['title' => 'Draft']);

    $this->actingAs($user)->patch(route('subtasks.update', [$slug, $subtask]), ['completed' => true]);
    expect($subtask->fresh()->completed)->toBeTrue();

    $this->actingAs($user)->patch(route('subtasks.update', [$slug, $subtask]), ['title' => 'First draft']);
    expect($subtask->fresh())->title->toBe('First draft')->completed->toBeTrue();

    $this->actingAs($user)->patch(route('subtasks.update', [$slug, $subtask]), ['completed' => false]);
    expect($subtask->fresh()->completed)->toBeFalse();

    $this->actingAs($user)->delete(route('subtasks.destroy', [$slug, $subtask]));
    expect(Subtask::count())->toBe(0);
});

test('the percent complete comes from the done subtasks', function (int $done, int $total, int $percent) {
    [$user, $task, $slug] = userWithSubtaskParent();
    Subtask::factory()->for($task)->count($done)->completed()->create();
    Subtask::factory()->for($task)->count($total - $done)->create();

    $this->actingAs($user)
        ->get(route('projects.show', [$slug, $task->project]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tasks.0.subtasks', $total)
            ->where('tasks.0.progress', ['done' => $done, 'total' => $total, 'percent' => $percent]));
})->with([
    'none done' => [0, 4, 0],
    'one of three' => [1, 3, 33],
    'two of three' => [2, 3, 66],
    'half' => [2, 4, 50],
    'all done' => [3, 3, 100],
]);

test('done subtasks do not finish the parent task or give it points', function () {
    Carbon::setTestNow('2026-11-18 10:00');
    [$user, $task, $slug] = userWithSubtaskParent();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id, 'committed_points' => 5]);
    $subtasks = Subtask::factory()->for($task)->count(2)->create();

    foreach ($subtasks as $subtask) {
        $this->actingAs($user)->patch(route('subtasks.update', [$slug, $subtask]), ['completed' => true]);
    }

    expect($task->fresh())
        ->status->toBe(TaskStatus::Todo)
        ->story_points->toBe(StoryPoints::Five)
        ->completed_at->toBeNull();

    // The board counts the 5 points only from the Done column.
    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.cards.0.status', 'todo')
            ->where('board.cards.0.points', 5)
            ->where('board.cards.0.progress', ['done' => 2, 'total' => 2, 'percent' => 100])
            ->has('board.cards.0.subtasks', 2));
});

test('users cannot add a subtask to a task in an archived project', function () {
    [$user, $task, $slug] = userWithSubtaskParent();
    $task->project->archive();

    $this->actingAs($user)
        ->post(route('subtasks.store', [$slug, $task]), ['title' => 'Find sources'])
        ->assertSessionHasErrors('title');

    expect(Subtask::count())->toBe(0);
});

test('deleting a task deletes its subtasks', function () {
    [$user, $task, $slug] = userWithSubtaskParent();
    Subtask::factory()->for($task)->count(2)->create();

    $this->actingAs($user)->delete(route('tasks.destroy', [$slug, $task]));

    expect(Subtask::count())->toBe(0);
});

test('users cannot see or change the subtasks of another user', function () {
    [$user, , $slug] = userWithSubtaskParent();
    [, $otherTask] = userWithSubtaskParent();
    $otherSubtask = Subtask::factory()->for($otherTask)->create(['title' => 'Private']);

    $this->actingAs($user)
        ->post(route('subtasks.store', [$slug, $otherTask]), ['title' => 'Intruder'])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('subtasks.update', [$slug, $otherSubtask]), ['completed' => true, 'title' => 'Changed'])
        ->assertForbidden();

    $this->actingAs($user)->delete(route('subtasks.destroy', [$slug, $otherSubtask]))->assertForbidden();

    expect($otherSubtask->fresh())->title->toBe('Private')->completed->toBeFalse()
        ->and($otherTask->subtasks()->count())->toBe(1);
});
