<?php

use App\Enums\StoryPoints;
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
 * ITERA-04: users create a Monday-to-Sunday Sprint, set a goal and a story
 * point target, and choose Tasks. A Task can be in only one current or
 * upcoming Sprint.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // Wednesday, week of Monday 16 November.
afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with one Project and two Tasks in their personal team.
 *
 * @return array{0: User, 1: string, 2: Task, 3: Task}
 */
function userWithTasks(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create();

    $first = Task::factory()->for($project)->create(['title' => 'Write the method section', 'story_points' => StoryPoints::Five]);
    $second = Task::factory()->for($project)->create(['title' => 'Read chapter 7', 'story_points' => StoryPoints::Three]);

    return [$user, $user->currentTeam->slug, $first, $second];
}

test('users can create a monday to sunday sprint with a goal, a target, and tasks', function () {
    [$user, $slug, $first, $second] = userWithTasks();

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), [
            'week_start' => '2026-11-16',
            'goal' => 'Hand in the lab report',
            'target_points' => 8,
            'task_ids' => [$first->id, $second->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sprint = $user->currentTeam->sprints()->sole();

    expect($sprint->week_start->toDateString())->toBe('2026-11-16')
        ->and($sprint->week_end->toDateString())->toBe('2026-11-22')
        ->and($sprint->goal)->toBe('Hand in the lab report')
        ->and($sprint->target_points)->toBe(8)
        ->and($sprint->commitments->pluck('task_title')->sort()->values()->all())
        ->toBe(['Read chapter 7', 'Write the method section'])
        ->and($sprint->fresh()->committedPoints())->toBe(8);
});

test('a sprint can start empty and plan the next weeks', function () {
    [$user, $slug] = userWithTasks();

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-23'])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->sprints()->sole()->commitments()->count())->toBe(0);
});

test('a sprint rejects an invalid week, goal, or target', function (array $input, string $field) {
    [$user, $slug] = userWithTasks();

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-23', ...$input])
        ->assertSessionHasErrors($field);

    expect(Sprint::count())->toBe(0);
})->with([
    'missing week' => [['week_start' => ''], 'week_start'],
    'not a monday' => [['week_start' => '2026-11-25'], 'week_start'],
    'a past week' => [['week_start' => '2026-11-09'], 'week_start'],
    'not a date' => [['week_start' => 'next week'], 'week_start'],
    'goal too long' => [['goal' => str_repeat('a', 201)], 'goal'],
    'target of zero' => [['target_points' => 0], 'target_points'],
    'target as text' => [['target_points' => 'many'], 'target_points'],
]);

test('a team cannot plan the same week twice', function () {
    [$user, $slug] = userWithTasks();
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-23'])
        ->assertSessionHasErrors('week_start');
});

test('a task cannot be in two current or upcoming sprints', function () {
    [$user, $slug, $first] = userWithTasks();
    $current = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($current)->create(['task_id' => $first->id]);

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-23', 'task_ids' => [$first->id]])
        ->assertSessionHasErrors(['task_ids' => 'Write the method section is already in another current or upcoming sprint.']);

    expect(Sprint::count())->toBe(1);
});

test('a task from a past sprint can go into a new sprint', function () {
    [$user, $slug, $first] = userWithTasks();
    $past = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-09']);
    SprintCommitment::factory()->for($past)->create(['task_id' => $first->id]);

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-16', 'task_ids' => [$first->id]])
        ->assertSessionHasNoErrors();

    expect($first->commitments()->count())->toBe(2);
});

test('done tasks and tasks of another user cannot go into a sprint', function () {
    [$user, $slug, $first] = userWithTasks();
    [, , $otherTask] = userWithTasks();
    $first->update(['status' => 'done']);

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-16', 'task_ids' => [$first->id]])
        ->assertSessionHasErrors('task_ids');

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-16', 'task_ids' => [$otherTask->id]])
        ->assertSessionHasErrors('task_ids');

    expect(Sprint::count())->toBe(0);
});

test('users can change the goal, target, and tasks of a sprint', function () {
    [$user, $slug, $first, $second] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $first->id]);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $sprint]), [
            'goal' => 'Study for the midterm',
            'target_points' => 5,
            'task_ids' => [$second->id],
        ])
        ->assertSessionHasNoErrors();

    $sprint->refresh();

    expect($sprint->goal)->toBe('Study for the midterm')
        ->and($sprint->target_points)->toBe(5)
        ->and($sprint->commitments()->pluck('task_id')->all())->toBe([$second->id]);
});

test('a past sprint cannot change', function () {
    [$user, $slug, $first] = userWithTasks();
    $past = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-09']);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $past]), ['goal' => 'Changed', 'task_ids' => [$first->id]])
        ->assertForbidden();
});

test('users cannot see or change the sprint of another user', function () {
    [$user, $slug] = userWithTasks();
    $other = Sprint::factory()->for(User::factory()->create()->currentTeam)->create(['goal' => 'Private']);

    $this->actingAs($user)->get(route('sprints.show', [$slug, $other]))->assertForbidden();
    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $other]), ['goal' => 'Changed'])
        ->assertForbidden();

    expect($other->fresh()->goal)->toBe('Private');
});

test('the sprints page lists sprints and the free weeks', function () {
    [$user, $slug] = userWithTasks();
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-09']);

    $this->actingAs($user)
        ->get(route('sprints.index', $slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sprints/Index')
            ->has('sprints', 2)
            ->where('sprints.0.timing', 'current')
            ->where('sprints.1.timing', 'past')
            ->where('freeWeeks', ['2026-11-23', '2026-11-30', '2026-12-07', '2026-12-14']));
});

test('the sprint page shows the plan and marks tasks that another sprint has', function () {
    [$user, $slug, $first, $second] = userWithTasks();
    $current = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16', 'target_points' => 13]);
    SprintCommitment::factory()->for($current)->create(['task_id' => $first->id]);
    $next = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)
        ->get(route('sprints.show', [$slug, $next]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sprints/Show')
            ->where('sprint.timing', 'upcoming')
            ->where('sprint.editable', true)
            ->has('candidates', 2)
            ->where('candidates.0.title', 'Write the method section')
            ->where('candidates.0.takenBy', '2026-11-16')
            ->where('candidates.1.takenBy', null));

    $this->actingAs($user)
        ->get(route('sprints.show', [$slug, $current]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sprint.committedPoints', 5)
            ->where('sprint.targetPoints', 13)
            ->has('commitments', 1)
            ->where('candidates.0.takenBy', null));
});

test('users can create a new task directly in a sprint', function () {
    [$user, $slug, $first] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $sprint]), [
            'title' => 'Email the lab partner',
            'project_id' => $first->project_id,
            'story_points' => 2,
        ])
        ->assertSessionHasNoErrors();

    $task = Task::query()->where('title', 'Email the lab partner')->sole();
    $commitment = $sprint->commitments()->sole();

    expect($task->project_id)->toBe($first->project_id)
        ->and($commitment->task_id)->toBe($task->id)
        ->and($commitment->committed_points)->toBe(2);
});

test('a new sprint task needs a title and one of the team projects', function () {
    [$user, $slug, $first] = userWithTasks();
    [, , $otherTask] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $sprint]), ['title' => '', 'project_id' => $first->project_id])
        ->assertSessionHasErrors('title');

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $sprint]), ['title' => 'Intruder', 'project_id' => $otherTask->project_id])
        ->assertSessionHasErrors('project_id');

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $sprint]), ['title' => 'Task', 'project_id' => $first->project_id, 'story_points' => 4])
        ->assertSessionHasErrors('story_points');

    expect($sprint->commitments()->count())->toBe(0);
});

test('saving only the tasks keeps the goal and target', function () {
    [$user, $slug, $first] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create([
        'week_start' => '2026-11-16',
        'goal' => 'Hand in the lab report',
        'target_points' => 8,
    ]);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $sprint]), ['task_ids' => [$first->id]])
        ->assertSessionHasNoErrors();

    $sprint->refresh();

    expect($sprint->goal)->toBe('Hand in the lab report')
        ->and($sprint->target_points)->toBe(8)
        ->and($sprint->commitments()->count())->toBe(1);
});

test('saving only the goal keeps the tasks', function () {
    [$user, $slug, $first] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $first->id]);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $sprint]), ['goal' => 'New goal', 'target_points' => ''])
        ->assertSessionHasNoErrors();

    expect($sprint->fresh()->goal)->toBe('New goal')
        ->and($sprint->fresh()->target_points)->toBeNull()
        ->and($sprint->commitments()->count())->toBe(1);
});

test('users cannot add a task to a past sprint or the sprint of another user', function () {
    [$user, $slug, $first] = userWithTasks();
    $past = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-09']);
    $other = Sprint::factory()->for(User::factory()->create()->currentTeam)->create(['week_start' => '2026-11-16']);

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $past]), ['title' => 'Late', 'project_id' => $first->project_id])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('sprints.tasks.store', [$slug, $other]), ['title' => 'Intruder', 'project_id' => $first->project_id])
        ->assertForbidden();
});

test('a task finished during the week does not block changes to the sprint', function () {
    [$user, $slug, $first, $second] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $first->id]);
    $first->update(['status' => TaskStatus::Done]);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $sprint]), ['task_ids' => [$first->id, $second->id]])
        ->assertSessionHasNoErrors();

    expect($sprint->commitments()->pluck('task_id')->sort()->values()->all())->toBe([$first->id, $second->id]);
});

test('a done task stays in the sprint when the list leaves it out', function () {
    [$user, $slug, $first, $second] = userWithTasks();
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $first->id]);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $second->id]);
    $first->update(['status' => TaskStatus::Done]);

    $this->actingAs($user)
        ->patch(route('sprints.update', [$slug, $sprint]), ['task_ids' => []])
        ->assertSessionHasNoErrors();

    expect($sprint->commitments()->pluck('task_id')->all())->toBe([$first->id]);
});
