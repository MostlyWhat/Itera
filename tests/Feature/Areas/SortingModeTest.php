<?php

use App\Enums\SortingMode;
use App\Models\Area;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-08: users choose how the system sorts their tasks: Stack order,
 * Deadline, or Balanced. The choice is saved, and overdue tasks show in
 * every mode.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // Wednesday
afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with two Areas (Biology on top, Job second) and five Tasks:
 * - Read the manual: Biology, no deadline
 * - Write the method: Biology, due in 10 days
 * - Swap a shift: Job, overdue
 * - Email the manager: Job, due tomorrow
 * - Update the CV: Job, no deadline
 *
 * @return array{0: User, 1: string}
 */
function userWithMixedTasks(): array
{
    $user = User::factory()->create();
    $biology = Project::factory()->for(Area::factory()->for($user->currentTeam)->create(['name' => 'Biology']))->create();
    $job = Project::factory()->for(Area::factory()->for($user->currentTeam)->create(['name' => 'Job']))->create();

    Task::factory()->for($biology)->create(['title' => 'Read the manual']);
    Task::factory()->for($biology)->create(['title' => 'Write the method', 'due_date' => '2026-11-28']);
    Task::factory()->for($job)->create(['title' => 'Swap a shift', 'due_date' => '2026-11-16']);
    Task::factory()->for($job)->create(['title' => 'Email the manager', 'due_date' => '2026-11-19']);
    Task::factory()->for($job)->create(['title' => 'Update the CV']);

    return [$user, $user->currentTeam->slug];
}

/**
 * @return list<string>
 */
function priorityTitles(User $user, string $slug): array
{
    return collect(test()->actingAs($user)->get(route('priority', $slug))->inertiaProps('tasks'))
        ->pluck('title')
        ->all();
}

test('a new user sorts by the priority stack', function () {
    [$user, $slug] = userWithMixedTasks();

    expect($user->fresh()->sorting_mode)->toBe(SortingMode::Stack)
        ->and(priorityTitles($user, $slug))->toBe([
            'Write the method', 'Read the manual', 'Swap a shift', 'Email the manager', 'Update the CV',
        ]);
});

test('each mode sorts the tasks its own way', function (string $mode, array $order) {
    [$user, $slug] = userWithMixedTasks();

    $this->actingAs($user)
        ->put(route('priority.mode', $slug), ['sorting_mode' => $mode])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->sorting_mode->value)->toBe($mode)
        ->and(priorityTitles($user, $slug))->toBe($order);
})->with([
    'deadline: nearest first, no deadline last' => ['deadline', [
        'Swap a shift', 'Email the manager', 'Write the method', 'Read the manual', 'Update the CV',
    ]],
    'balanced: overdue, then due soon, then the stack' => ['balanced', [
        'Swap a shift', 'Email the manager', 'Write the method', 'Read the manual', 'Update the CV',
    ]],
    'stack: the priority stack' => ['stack', [
        'Write the method', 'Read the manual', 'Swap a shift', 'Email the manager', 'Update the CV',
    ]],
]);

test('balanced mode puts the stack first among tasks that are not urgent', function () {
    [$user, $slug] = userWithMixedTasks();
    $user->update(['sorting_mode' => SortingMode::Balanced]);

    // A second overdue task, in the top area: the stack decides inside the
    // overdue group.
    $biology = Project::query()->whereHas('area', fn ($areas) => $areas->where('name', 'Biology'))->first();
    Task::factory()->for($biology)->create(['title' => 'Return the kit', 'due_date' => '2026-11-17']);

    expect(priorityTitles($user, $slug))->toBe([
        'Return the kit', 'Swap a shift', 'Email the manager', 'Write the method', 'Read the manual', 'Update the CV',
    ]);
});

test('overdue tasks show and are marked in every mode', function (SortingMode $mode) {
    [$user, $slug] = userWithMixedTasks();
    $user->update(['sorting_mode' => $mode]);

    $tasks = collect($this->actingAs($user)->get(route('priority', $slug))->inertiaProps('tasks'));

    expect($tasks->where('isOverdue', true)->pluck('title')->all())->toBe(['Swap a shift'])
        ->and($tasks->firstWhere('title', 'Email the manager')['isDueSoon'])->toBeTrue()
        ->and($tasks->firstWhere('title', 'Write the method')['isDueSoon'])->toBeFalse();
})->with(SortingMode::cases());

test('the sprint backlog and the board use the saved mode', function () {
    [$user, $slug] = userWithMixedTasks();
    $user->update(['sorting_mode' => SortingMode::Deadline]);
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);

    $this->actingAs($user)
        ->get(route('sprints.show', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sortingLabel', 'Deadline')
            ->where('candidates.0.title', 'Swap a shift')
            ->where('candidates.4.title', 'Update the CV'));

    foreach (Task::query()->whereIn('title', ['Read the manual', 'Email the manager'])->get() as $task) {
        SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]);
    }

    $cards = collect($this->actingAs($user)->get(route('dashboard', $slug))->inertiaProps('board.cards'))->pluck('title')->all();
    expect($cards)->toBe(['Email the manager', 'Read the manual']);
});

test('the page lists the three modes and the saved one', function () {
    [$user, $slug] = userWithMixedTasks();

    $this->actingAs($user)
        ->get(route('priority', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sortingMode', 'stack')
            ->has('sortingModes', 3)
            ->where('sortingModes.0.label', 'Stack order')
            ->where('sortingModes.2.value', 'balanced'));
});

test('an unknown mode is rejected', function () {
    [$user, $slug] = userWithMixedTasks();

    $this->actingAs($user)
        ->put(route('priority.mode', $slug), ['sorting_mode' => 'random'])
        ->assertSessionHasErrors('sorting_mode');

    expect($user->fresh()->sorting_mode)->toBe(SortingMode::Stack);
});

test('guests cannot change the sorting mode', function () {
    [, $slug] = userWithMixedTasks();

    $this->put(route('priority.mode', $slug), ['sorting_mode' => 'deadline'])->assertRedirect(route('login'));
});

test('a deadline that is not soon moves up in deadline mode but not in balanced mode', function () {
    [$user, $slug] = userWithMixedTasks();
    $job = Project::query()->whereHas('area', fn ($areas) => $areas->where('name', 'Job'))->first();
    Task::factory()->for($job)->create(['title' => 'Plan the rota', 'due_date' => '2026-11-23']);

    $user->update(['sorting_mode' => SortingMode::Deadline]);
    expect(priorityTitles($user, $slug))->toBe([
        'Swap a shift', 'Email the manager', 'Plan the rota', 'Write the method', 'Read the manual', 'Update the CV',
    ]);

    $user->update(['sorting_mode' => SortingMode::Balanced]);
    expect(priorityTitles($user, $slug))->toBe([
        'Swap a shift', 'Email the manager', 'Write the method', 'Read the manual', 'Plan the rota', 'Update the CV',
    ]);
});

test('the priority page gives the room left in the next sprint', function () {
    [$user, $slug] = userWithMixedTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16', 'target_points' => 20]);
    $task = Task::query()->where('title', 'Swap a shift')->first();
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id, 'committed_points' => 8]);

    $this->actingAs($user)
        ->get(route('priority', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nextSprint.id', $sprint->id)
            ->where('nextSprint.current', true)
            ->where('nextSprint.targetPoints', 20)
            ->where('nextSprint.committedPoints', 8));
});
