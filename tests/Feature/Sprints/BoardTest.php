<?php

use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-05: the dashboard shows this week's sprint as a Kanban board in To
 * Do, In Progress, and Done columns. Moving a card saves the task status.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // Wednesday, week of Monday 16 November.
afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with this week's sprint and three committed tasks.
 *
 * @return array{0: User, 1: string, 2: Sprint, 3: list<Task>}
 */
function userWithBoard(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create(['name' => 'Lab report']);
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16', 'goal' => 'Hand in the lab report']);

    $tasks = [
        Task::factory()->for($project)->create(['title' => 'Read the manual', 'status' => TaskStatus::Todo]),
        Task::factory()->for($project)->create(['title' => 'Write the method', 'status' => TaskStatus::InProgress]),
        Task::factory()->for($project)->create(['title' => 'Book the lab', 'status' => TaskStatus::Done]),
    ];

    foreach ($tasks as $task) {
        SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]);
    }

    return [$user, $user->currentTeam->slug, $sprint, $tasks];
}

test('the dashboard shows the sprint tasks in to do, in progress, and done', function () {
    [$user, $slug] = userWithBoard();

    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('board.sprint.goal', 'Hand in the lab report')
            ->where('board.columns', [
                ['status' => 'todo', 'label' => 'To Do'],
                ['status' => 'in_progress', 'label' => 'In Progress'],
                ['status' => 'done', 'label' => 'Done'],
            ])
            ->has('board.cards', 3)
            ->where('board.cards.0.status', 'todo')
            ->where('board.cards.1.status', 'in_progress')
            ->where('board.cards.2.status', 'done')
            ->where('board.cards.0.projectName', 'Lab report'));
});

test('the dashboard has no board without a sprint this week', function () {
    $user = User::factory()->create();
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)
        ->get(route('dashboard', $user->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('board', null));
});

test('moving a card saves the status and the board shows it after a reload', function () {
    [$user, $slug, , $tasks] = userWithBoard();

    $this->actingAs($user)
        ->patch(route('tasks.status.update', [$slug, $tasks[0]]), ['status' => 'in_progress'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($tasks[0]->fresh()->status)->toBe(TaskStatus::InProgress);

    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page->where('board.cards.0.status', 'in_progress'));
});

test('moving a card to done sets the completion time and back clears it', function () {
    [$user, $slug, , $tasks] = userWithBoard();

    $this->actingAs($user)->patch(route('tasks.status.update', [$slug, $tasks[1]]), ['status' => 'done']);
    expect($tasks[1]->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('tasks.status.update', [$slug, $tasks[1]]), ['status' => 'todo']);
    expect($tasks[1]->fresh()->completed_at)->toBeNull();
});

test('a move needs a known status', function (mixed $status) {
    [$user, $slug, , $tasks] = userWithBoard();

    $this->actingAs($user)
        ->patch(route('tasks.status.update', [$slug, $tasks[0]]), ['status' => $status])
        ->assertSessionHasErrors('status');

    expect($tasks[0]->fresh()->status)->toBe(TaskStatus::Todo);
})->with(['', 'blocked', 'DONE']);

test('users cannot move the tasks of another user', function () {
    [$user, $slug] = userWithBoard();
    [, , , $otherTasks] = userWithBoard();

    $this->actingAs($user)
        ->patch(route('tasks.status.update', [$slug, $otherTasks[0]]), ['status' => 'done'])
        ->assertForbidden();

    expect($otherTasks[0]->fresh()->status)->toBe(TaskStatus::Todo);
});
