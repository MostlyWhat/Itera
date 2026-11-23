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
 * ITERA-07: users change the priority order of their Areas and Projects.
 * The order is saved. Deadlines, task statuses, and story points stay.
 */

/**
 * Make a user with three Areas: Biology, Job, Home.
 *
 * @return array{0: User, 1: string, 2: list<Area>}
 */
function userWithAreas(): array
{
    $user = User::factory()->create();
    $areas = collect(['Biology', 'Job', 'Home'])
        ->map(fn (string $name) => Area::factory()->for($user->currentTeam)->create(['name' => $name]))
        ->all();

    return [$user, $user->currentTeam->slug, $areas];
}

test('users can save a new order of their areas', function () {
    [$user, $slug, [$biology, $job, $home]] = userWithAreas();

    $this->actingAs($user)
        ->put(route('areas.order', $slug), ['area_ids' => [$home->id, $biology->id, $job->id]])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->areas()->pluck('name')->all())->toBe(['Home', 'Biology', 'Job']);

    $this->actingAs($user)
        ->get(route('projects.index', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('navigation.areas.0.name', 'Home')
            ->where('navigation.areas.2.name', 'Job'));
});

test('archived areas keep their place after the ordered areas', function () {
    [$user, $slug, [$biology, $job, $home]] = userWithAreas();
    $job->archive();

    $this->actingAs($user)->put(route('areas.order', $slug), ['area_ids' => [$home->id, $biology->id]]);

    expect($user->currentTeam->areas()->pluck('name')->all())->toBe(['Home', 'Biology', 'Job']);
});

test('users can save a new order of the projects in an area', function () {
    [$user, $slug, [$biology]] = userWithAreas();
    $lab = Project::factory()->for($biology)->create(['name' => 'Lab report']);
    $midterm = Project::factory()->for($biology)->create(['name' => 'Midterm']);
    $essay = Project::factory()->for($biology)->create(['name' => 'Essay']);

    $this->actingAs($user)
        ->put(route('projects.order', [$slug, $biology]), ['project_ids' => [$midterm->id, $essay->id, $lab->id]])
        ->assertSessionHasNoErrors();

    expect($biology->projects()->pluck('name')->all())->toBe(['Midterm', 'Essay', 'Lab report']);
});

test('a new order does not change deadlines, statuses, or story points', function () {
    [$user, $slug, [$biology, $job]] = userWithAreas();
    $lab = Project::factory()->for($biology)->create();
    $midterm = Project::factory()->for($biology)->create();
    $task = Task::factory()->for($lab)->create([
        'status' => TaskStatus::InProgress,
        'story_points' => StoryPoints::Eight,
        'due_date' => '2026-12-01',
    ]);
    $before = $task->fresh()->only(['status', 'story_points', 'due_date', 'completed_at', 'updated_at']);

    $this->actingAs($user)->put(route('areas.order', $slug), ['area_ids' => [$job->id, $biology->id]]);
    $this->actingAs($user)->put(route('projects.order', [$slug, $biology]), ['project_ids' => [$midterm->id, $lab->id]]);

    expect($task->fresh()->only(['status', 'story_points', 'due_date', 'completed_at', 'updated_at']))->toEqual($before);
});

test('an order must name areas and projects of the right team and area', function () {
    [$user, $slug, [$biology, $job]] = userWithAreas();
    [, , [$otherArea]] = userWithAreas();
    $otherProject = Project::factory()->for($job)->create();

    $this->actingAs($user)
        ->put(route('areas.order', $slug), ['area_ids' => [$otherArea->id, $biology->id]])
        ->assertSessionHasErrors('area_ids.0');

    $this->actingAs($user)
        ->put(route('areas.order', $slug), ['area_ids' => [$biology->id, $biology->id]])
        ->assertSessionHasErrors('area_ids.0');

    $this->actingAs($user)
        ->put(route('projects.order', [$slug, $biology]), ['project_ids' => [$otherProject->id]])
        ->assertSessionHasErrors('project_ids.0');

    $this->actingAs($user)
        ->put(route('areas.order', $slug), ['area_ids' => []])
        ->assertSessionHasErrors('area_ids');

    expect($user->currentTeam->areas()->pluck('name')->all())->toBe(['Biology', 'Job', 'Home']);
});

test('users cannot change the order of another user', function () {
    [$user, $slug] = userWithAreas();
    [$other, $otherSlug, [$otherArea]] = userWithAreas();
    $project = Project::factory()->for($otherArea)->create();

    $this->actingAs($user)
        ->put(route('projects.order', [$slug, $otherArea]), ['project_ids' => [$project->id]])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('areas.order', $otherSlug), ['area_ids' => [$otherArea->id]])
        ->assertForbidden();

    expect($other->currentTeam->areas()->pluck('name')->first())->toBe('Biology');
});

test('the priority page lists the stack and the open tasks in stack order', function () {
    [$user, $slug, [$biology, $job]] = userWithAreas();
    $lab = Project::factory()->for($biology)->create(['name' => 'Lab report']);
    $shifts = Project::factory()->for($job)->create(['name' => 'Shifts']);
    $midterm = Project::factory()->for($biology)->create(['name' => 'Midterm']);

    Task::factory()->for($shifts)->create(['title' => 'Swap a shift', 'due_date' => '2026-12-01']);
    Task::factory()->for($midterm)->create(['title' => 'Make flash cards']);
    Task::factory()->for($lab)->create(['title' => 'Write the method', 'due_date' => '2026-12-10']);
    Task::factory()->for($lab)->create(['title' => 'Read the manual', 'due_date' => '2026-12-02']);
    Task::factory()->for($lab)->create(['title' => 'Ask the tutor']);
    Task::factory()->for($lab)->done()->create(['title' => 'Book the lab']);

    $titles = fn () => collect($this->actingAs($user)->get(route('priority', $slug))->inertiaProps('tasks'))->pluck('title')->all();

    expect($titles())->toBe(['Read the manual', 'Write the method', 'Ask the tutor', 'Make flash cards', 'Swap a shift']);

    $this->actingAs($user)->put(route('areas.order', $slug), ['area_ids' => [$job->id, $biology->id]]);
    $this->actingAs($user)->put(route('projects.order', [$slug, $biology]), ['project_ids' => [$midterm->id, $lab->id]]);

    expect($titles())->toBe(['Swap a shift', 'Make flash cards', 'Read the manual', 'Write the method', 'Ask the tutor']);

    $this->actingAs($user)
        ->get(route('priority', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->component('priority/Index')
            ->where('areas.0.name', 'Job')
            ->where('areas.1.projects.0.name', 'Midterm')
            ->has('tasks', 5)
            ->where('sortingMode', 'stack')
            ->where('nextSprint', null));
});

test('the sprint backlog and the board follow the priority stack', function () {
    Carbon::setTestNow('2026-11-18 10:00');
    [$user, $slug, [$biology, $job]] = userWithAreas();
    $lab = Project::factory()->for($biology)->create();
    $shifts = Project::factory()->for($job)->create();
    $first = Task::factory()->for($lab)->create(['title' => 'Biology task']);
    $second = Task::factory()->for($shifts)->create(['title' => 'Job task', 'due_date' => '2026-11-19']);
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']);

    $this->actingAs($user)->put(route('areas.order', $slug), ['area_ids' => [$job->id, $biology->id]]);

    $backlog = collect($this->actingAs($user)->get(route('sprints.show', [$slug, $sprint]))->inertiaProps('candidates'))->pluck('title')->all();
    expect($backlog)->toBe(['Job task', 'Biology task']);

    foreach ([$first, $second] as $task) {
        SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]);
    }

    $cards = collect($this->actingAs($user)->get(route('dashboard', $slug))->inertiaProps('board.cards'))->pluck('title')->all();
    expect($cards)->toBe(['Job task', 'Biology task']);

    Carbon::setTestNow();
});

test('guests and other teams cannot open the priority page', function () {
    [$user, $slug] = userWithAreas();
    [$other] = userWithAreas();

    $this->get(route('priority', $slug))->assertRedirect(route('login'));
    $this->actingAs($other)->get(route('priority', $slug))->assertForbidden();
});
