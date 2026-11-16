<?php

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-03: users create and edit Tasks under a Project, give parent Tasks
 * Fibonacci story points (1 to 21), set deadlines, and see overdue Tasks.
 */

afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with one Project in their personal team.
 *
 * @return array{0: User, 1: Project, 2: string}
 */
function userWithProject(): array
{
    $user = User::factory()->create();
    $project = Project::factory()
        ->for(Area::factory()->for($user->currentTeam))
        ->create(['name' => 'Lab report']);

    return [$user, $project, $user->currentTeam->slug];
}

test('the project page lists its tasks and marks overdue tasks', function () {
    Carbon::setTestNow('2026-11-16 09:00');
    [$user, $project, $slug] = userWithProject();

    Task::factory()->for($project)->create([
        'title' => 'Write the method section',
        'story_points' => StoryPoints::Three,
        'due_date' => '2026-11-14',
    ]);
    Task::factory()->for($project)->create(['title' => 'Check the sample sizes', 'due_date' => '2026-11-20']);

    $this->actingAs($user)
        ->get(route('projects.show', [$slug, $project]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Show')
            ->where('project.name', 'Lab report')
            ->has('tasks', 2)
            ->where('tasks.0.title', 'Write the method section')
            ->where('tasks.0.storyPoints', 3)
            ->where('tasks.0.dueDate', '2026-11-14')
            ->where('tasks.0.isOverdue', true)
            ->where('tasks.1.isOverdue', false)
            ->where('storyPointOptions', [1, 2, 3, 5, 8, 13, 21]));
});

test('users can create a task with story points and a deadline', function () {
    [$user, $project, $slug] = userWithProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), [
            'title' => '  Write the method section ',
            'story_points' => 5,
            'due_date' => '2026-11-20',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $task = $project->tasks()->sole();

    expect($task->title)->toBe('Write the method section')
        ->and($task->story_points)->toBe(StoryPoints::Five)
        ->and($task->due_date->toDateString())->toBe('2026-11-20')
        ->and($task->status)->toBe(TaskStatus::Todo);
});

test('a task can be created without story points or a deadline', function () {
    [$user, $project, $slug] = userWithProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), [
            'title' => 'Read chapter 7',
            'story_points' => '',
            'due_date' => '',
        ])
        ->assertSessionHasNoErrors();

    $task = $project->tasks()->sole();

    expect($task->story_points)->toBeNull()
        ->and($task->due_date)->toBeNull();
});

test('a task rejects invalid input', function (array $input, string $field) {
    [$user, $project, $slug] = userWithProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), ['title' => 'Read chapter 7', ...$input])
        ->assertSessionHasErrors($field);

    expect(Task::count())->toBe(0);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'title too long' => [['title' => str_repeat('a', 201)], 'title'],
    '4 story points' => [['story_points' => 4], 'story_points'],
    '34 story points' => [['story_points' => 34], 'story_points'],
    '0 story points' => [['story_points' => 0], 'story_points'],
    'text story points' => [['story_points' => 'large'], 'story_points'],
    'invalid deadline' => [['due_date' => 'next friday'], 'due_date'],
    'unknown status' => [['status' => 'blocked'], 'status'],
]);

test('every allowed story point value is accepted', function (int $points) {
    [$user, $project, $slug] = userWithProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), ['title' => 'Task', 'story_points' => $points])
        ->assertSessionHasNoErrors();

    expect($project->tasks()->sole()->story_points->value)->toBe($points);
})->with([1, 2, 3, 5, 8, 13, 21]);

test('users can edit a task', function () {
    [$user, $project, $slug] = userWithProject();
    $task = Task::factory()->for($project)->create(['title' => 'Draft', 'story_points' => StoryPoints::One]);

    $this->actingAs($user)
        ->patch(route('tasks.update', [$slug, $task]), [
            'title' => 'Write the method section',
            'story_points' => 8,
            'due_date' => '2026-11-21',
            'status' => 'in_progress',
        ])
        ->assertSessionHasNoErrors();

    $task->refresh();

    expect($task->title)->toBe('Write the method section')
        ->and($task->story_points)->toBe(StoryPoints::Eight)
        ->and($task->due_date->toDateString())->toBe('2026-11-21')
        ->and($task->status)->toBe(TaskStatus::InProgress);
});

test('users can remove the deadline and story points of a task', function () {
    [$user, $project, $slug] = userWithProject();
    $task = Task::factory()->for($project)->overdue()->create();

    $this->actingAs($user)
        ->patch(route('tasks.update', [$slug, $task]), [
            'title' => $task->title,
            'story_points' => '',
            'due_date' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->due_date)->toBeNull()
        ->and($task->fresh()->story_points)->toBeNull();
});

test('users can delete a task', function () {
    [$user, $project, $slug] = userWithProject();
    $task = Task::factory()->for($project)->create();

    $this->actingAs($user)
        ->delete(route('tasks.destroy', [$slug, $task]))
        ->assertRedirect();

    expect(Task::count())->toBe(0);
});

test('users cannot add a task to an archived project', function () {
    [$user, $project, $slug] = userWithProject();
    $project->archive();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), ['title' => 'Read chapter 7'])
        ->assertSessionHasErrors('title');

    expect(Task::count())->toBe(0);
});

test('users cannot see or change the tasks of another user', function () {
    [$user, , $slug] = userWithProject();
    [, $otherProject] = userWithProject();
    $otherTask = Task::factory()->for($otherProject)->create(['title' => 'Private']);

    $this->actingAs($user)->get(route('projects.show', [$slug, $otherProject]))->assertForbidden();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $otherProject]), ['title' => 'Intruder'])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('tasks.update', [$slug, $otherTask]), ['title' => 'Changed'])
        ->assertForbidden();

    $this->actingAs($user)->delete(route('tasks.destroy', [$slug, $otherTask]))->assertForbidden();

    expect($otherTask->fresh()->title)->toBe('Private')
        ->and($otherProject->tasks()->count())->toBe(1);
});

test('the areas page shows open and overdue task counts for each project', function () {
    Carbon::setTestNow('2026-11-16 09:00');
    [$user, $project, $slug] = userWithProject();

    Task::factory()->for($project)->create(['due_date' => '2026-11-10']);
    Task::factory()->for($project)->create(['due_date' => '2026-11-30']);
    Task::factory()->for($project)->done()->create(['due_date' => '2026-11-10']);

    $this->actingAs($user)
        ->get(route('areas.index', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('areas.0.projects.0.openTasks', 2)
            ->where('areas.0.projects.0.overdueTasks', 1));
});

test('the projects page opens the first active project', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $archivedArea = Area::factory()->for($team)->archived()->create();
    Project::factory()->for($archivedArea)->create();
    $area = Area::factory()->for($team)->create();
    Project::factory()->for($area)->archived()->create();
    $first = Project::factory()->for($area)->create();

    $this->actingAs($user)
        ->get(route('projects.index', $team->slug))
        ->assertRedirect(route('projects.show', [$team->slug, $first]));
});

test('the projects page sends users without projects to the areas page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index', $user->currentTeam->slug))
        ->assertRedirect(route('areas.index', $user->currentTeam->slug));
});

test('the project page lists all active projects for the selector', function () {
    Carbon::setTestNow('2026-11-16 09:00');
    [$user, $project, $slug] = userWithProject();
    $other = Project::factory()->for($project->area)->create(['name' => 'Midterm review']);
    Project::factory()->for($project->area)->archived()->create(['name' => 'Old']);
    Task::factory()->for($other)->create(['due_date' => '2026-11-10']);

    $this->actingAs($user)
        ->get(route('projects.show', [$slug, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('navigation', 1)
            ->has('navigation.0.projects', 2)
            ->where('navigation.0.projects.0.name', 'Lab report')
            ->where('navigation.0.projects.1.name', 'Midterm review')
            ->where('navigation.0.projects.1.overdueTasks', 1));
});
