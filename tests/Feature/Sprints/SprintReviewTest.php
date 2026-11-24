<?php

use App\Enums\ClosingStatus;
use App\Enums\SprintStatus;
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
 * ITERA-10: after its week ends, the user reviews a Sprint. The review shows
 * the committed and completed points, saves a retrospective, and sends each
 * unfinished Task back to the backlog or into the next Sprint. The saved
 * results of the old Sprint do not change after that.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-23 10:00')); // Monday, the day after last week's sprint.
afterEach(fn () => Carbon::setTestNow());

/**
 * Make a user with last week's sprint: one done task (5 points) and two
 * unfinished tasks (3 and 2 points).
 *
 * @return array{0: User, 1: string, 2: Sprint, 3: array<string, Task>, 4: array<string, SprintCommitment>}
 */
function userWithPastSprint(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create(['name' => 'Lab report']);
    $sprint = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16', 'target_points' => 13]);

    $tasks = [
        'done' => Task::factory()->for($project)->create(['title' => 'Book the lab', 'story_points' => StoryPoints::Five, 'status' => TaskStatus::Done]),
        'started' => Task::factory()->for($project)->create(['title' => 'Write the method', 'story_points' => StoryPoints::Three, 'status' => TaskStatus::InProgress]),
        'waiting' => Task::factory()->for($project)->create(['title' => 'Read the manual', 'story_points' => StoryPoints::Two, 'status' => TaskStatus::Todo]),
    ];

    $commitments = array_map(
        fn (Task $task) => SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]),
        $tasks,
    );

    return [$user, $user->currentTeam->slug, $sprint, $tasks, $commitments];
}

test('the dashboard reminds the user to review a past sprint', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('review.id', $sprint->id)
            ->where('review.weekStart', '2026-11-16')
            ->where('review.waiting', 1));
});

test('there is no reminder while the sprint week runs', function () {
    [$user, $slug] = userWithPastSprint();
    Carbon::setTestNow('2026-11-22 20:00'); // Sunday of the sprint week.

    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page->where('review', null));
});

test('the reminder does not stop the user from starting the next sprint', function () {
    [$user, $slug] = userWithPastSprint();

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-23', 'goal' => 'Start the essay'])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->sprints()->whereDate('week_start', '2026-11-23')->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard', $slug))
        ->assertInertia(fn (Assert $page) => $page->where('review.weekStart', '2026-11-16'));
});

test('the review page shows the committed and completed points', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)
        ->get(route('sprints.review', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sprints/Review')
            ->where('sprint.committedPoints', 10)
            ->where('sprint.completedPoints', 5)
            ->where('sprint.targetPoints', 13)
            ->has('done', 1)
            ->has('unfinished', 2)
            ->where('unfinished.0.title', 'Write the method')
            ->where('nextSprint', null));
});

test('a review saves the retrospective and the results of the week', function () {
    [$user, $slug, $sprint, , $commitments] = userWithPastSprint();

    $this->actingAs($user)
        ->post(route('sprints.review.store', [$slug, $sprint]), [
            'went_well' => 'I booked the lab early.',
            'blockers' => '  ',
            'improve_next' => 'Plan fewer points.',
            'decisions' => [
                $commitments['started']->id => 'carried_over',
                $commitments['waiting']->id => 'dropped',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('sprints.show', [$slug, $sprint]));

    $sprint->refresh();

    expect($sprint->status)->toBe(SprintStatus::Completed)
        ->and($sprint->completed_points)->toBe(5)
        ->and($sprint->review_completed_at)->not->toBeNull()
        ->and($sprint->retrospective)
        ->went_well->toBe('I booked the lab early.')
        ->blockers->toBeNull()
        ->improve_next->toBe('Plan fewer points.')
        ->and($commitments['done']->fresh()->closing_status)->toBe(ClosingStatus::Done)
        ->and($commitments['started']->fresh()->closing_status)->toBe(ClosingStatus::CarriedOver)
        ->and($commitments['waiting']->fresh()->closing_status)->toBe(ClosingStatus::Dropped);
});

test('a task planned again goes into the next sprint', function () {
    [$user, $slug, $sprint, $tasks, $commitments] = userWithPastSprint();
    $next = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), [
        'decisions' => [$commitments['started']->id => 'carried_over'],
    ]);

    expect($next->commitments()->pluck('task_id')->all())->toBe([$tasks['started']->id])
        ->and($next->commitments()->first()->committed_points)->toBe(3);
});

test('without a next sprint, planning a task again makes this week\'s sprint', function () {
    [$user, $slug, $sprint, $tasks, $commitments] = userWithPastSprint();

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), [
        'decisions' => [$commitments['started']->id => 'carried_over'],
    ]);

    $next = $user->currentTeam->sprints()->whereDate('week_start', '2026-11-23')->sole();

    expect($next->status)->toBe(SprintStatus::Open)
        ->and($next->commitments()->pluck('task_id')->all())->toBe([$tasks['started']->id]);
});

test('a task sent back to the backlog is free to plan', function () {
    [$user, $slug, $sprint, $tasks, $commitments] = userWithPastSprint();
    $next = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), [
        'decisions' => [$commitments['waiting']->id => 'dropped'],
    ]);

    expect($tasks['waiting']->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($next->commitments()->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('sprints.show', [$slug, $next]))
        ->assertInertia(fn (Assert $page) => $page->where('candidates', fn ($candidates) => collect($candidates)
            ->contains(fn ($task) => $task['id'] === $tasks['waiting']->id && $task['takenBy'] === null)));
});

test('later changes do not change the results of a reviewed sprint', function () {
    [$user, $slug, $sprint, $tasks, $commitments] = userWithPastSprint();

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), [
        'decisions' => [$commitments['started']->id => 'carried_over'],
    ]);

    // The next week: the planned-again task gets done and renamed.
    $tasks['started']->update(['status' => TaskStatus::Done, 'title' => 'Write the method section', 'story_points' => StoryPoints::Eight]);
    $tasks['done']->delete();

    $this->actingAs($user)
        ->get(route('sprints.show', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sprint.completedPoints', 5)
            ->where('sprint.committedPoints', 10)
            ->where('sprint.reviewed', true)
            ->where('commitments', fn ($rows) => collect($rows)->pluck('closingStatus', 'title')->all() === [
                'Book the lab' => 'done',
                'Write the method' => 'carried_over',
                'Read the manual' => 'dropped',
            ]));
});

test('a sprint can be reviewed only once', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]))->assertRedirect();
    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]))->assertForbidden();
    $this->actingAs($user)
        ->get(route('sprints.review', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page->component('sprints/Review')->where('reviewed', true));
});

test('the review page of a running sprint goes back to the sprint', function () {
    [$user, $slug] = userWithPastSprint();
    $current = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23']);

    $this->actingAs($user)
        ->get(route('sprints.review', [$slug, $current]))
        ->assertRedirect(route('sprints.show', [$slug, $current]));
});

test('users can edit the notes after the review without changing the results', function () {
    [$user, $slug, $sprint, $tasks, $commitments] = userWithPastSprint();
    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), [
        'went_well' => 'First answer.',
        'decisions' => [$commitments['started']->id => 'carried_over'],
    ]);
    $tasks['started']->update(['status' => TaskStatus::Done]);

    $this->actingAs($user)
        ->patch(route('sprints.review.update', [$slug, $sprint]), [
            'went_well' => 'A better answer.',
            'blockers' => 'The lab was closed on Friday.',
            // The results are fixed: a choice here changes nothing.
            'decisions' => [$commitments['started']->id => 'dropped'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('sprints.show', [$slug, $sprint]));

    $sprint->refresh();

    expect($sprint->retrospective)
        ->went_well->toBe('A better answer.')
        ->blockers->toBe('The lab was closed on Friday.')
        ->and($sprint->completed_points)->toBe(5)
        ->and($commitments['started']->fresh()->closing_status)->toBe(ClosingStatus::CarriedOver);

    $this->actingAs($user)
        ->get(route('sprints.review', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sprint.completedPoints', 5)
            ->has('done', 1)
            ->where('unfinished.0.closingStatus', 'carried_over')
            ->where('retrospective.went_well', 'A better answer.'));
});

test('a sprint without a review cannot be edited', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)
        ->patch(route('sprints.review.update', [$slug, $sprint]), ['went_well' => 'Too early.'])
        ->assertForbidden();
});

test('the review suggests a target from the completed points', function () {
    [$user, $slug, $sprint] = userWithPastSprint();
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-02'])
        ->forceFill(['status' => 'completed', 'completed_points' => 8])->save();
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-09'])
        ->forceFill(['status' => 'completed', 'completed_points' => 11])->save();

    // This week completed 5 points: (5 + 11 + 8) / 3 = 8.
    $this->actingAs($user)
        ->get(route('sprints.review', [$slug, $sprint]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestion.points', 8)
            ->where('suggestion.sprints', 3));
});

test('the review sets the target of the next sprint', function () {
    [$user, $slug, $sprint] = userWithPastSprint();
    $next = Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-23', 'target_points' => 20]);

    $this->actingAs($user)
        ->post(route('sprints.review.store', [$slug, $sprint]), ['next_target' => 8])
        ->assertSessionHasNoErrors();

    expect($next->fresh()->target_points)->toBe(8);

    $this->actingAs($user)->patch(route('sprints.review.update', [$slug, $sprint]), ['next_target' => 10]);

    expect($next->fresh()->target_points)->toBe(10)
        ->and($sprint->fresh()->target_points)->toBe(13);
});

test('without a next sprint, a new target makes the sprint of this week', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]), ['next_target' => 8]);

    expect($user->currentTeam->sprints()->whereDate('week_start', '2026-11-23')->value('target_points'))->toBe(8);
});

test('a next target must be at least one point', function () {
    [$user, $slug, $sprint] = userWithPastSprint();

    $this->actingAs($user)
        ->post(route('sprints.review.store', [$slug, $sprint]), ['next_target' => 0])
        ->assertSessionHasErrors('next_target');
});

test('a sprint cannot be reviewed before its week ends', function () {
    [$user, $slug, $sprint] = userWithPastSprint();
    Carbon::setTestNow('2026-11-22 20:00');

    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $sprint]))->assertForbidden();

    expect($sprint->fresh()->status)->toBe(SprintStatus::Open);
});

test('a choice must be plan again or back to backlog', function () {
    [$user, $slug, $sprint, , $commitments] = userWithPastSprint();

    $this->actingAs($user)
        ->post(route('sprints.review.store', [$slug, $sprint]), ['decisions' => [$commitments['started']->id => 'done']])
        ->assertSessionHasErrors('decisions.'.$commitments['started']->id);
});

test('users cannot review the sprint of another team', function () {
    [$user, $slug] = userWithPastSprint();
    [, , $otherSprint] = userWithPastSprint();

    $this->actingAs($user)->get(route('sprints.review', [$slug, $otherSprint]))->assertForbidden();
    $this->actingAs($user)->post(route('sprints.review.store', [$slug, $otherSprint]))->assertForbidden();
});
