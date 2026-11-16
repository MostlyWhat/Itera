<?php

use App\Enums\SprintStatus;
use App\Enums\StoryPoints;
use App\Models\Sprint;
use App\Models\SprintCommitment;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/*
 * ITERA-04: the data layer for Sprints and sprint commitments.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // A Wednesday.
afterEach(fn () => Carbon::setTestNow());

test('a sprint always ends on the sunday after its monday', function () {
    $sprint = Sprint::factory()->create(['week_start' => '2026-11-16']);

    expect($sprint->week_end->toDateString())->toBe('2026-11-22')
        ->and($sprint->status)->toBe(SprintStatus::Open);
});

test('a team has at most one sprint for each week', function () {
    $team = Team::factory()->create();
    Sprint::factory()->for($team)->create(['week_start' => '2026-11-16']);

    Sprint::factory()->for($team)->create(['week_start' => '2026-11-16']);
})->throws(UniqueConstraintViolationException::class);

test('another team can plan the same week', function () {
    Sprint::factory()->create(['week_start' => '2026-11-16']);
    Sprint::factory()->create(['week_start' => '2026-11-16']);

    expect(Sprint::count())->toBe(2);
});

test('a sprint knows if it is current or upcoming', function () {
    $current = Sprint::factory()->create();
    $next = Sprint::factory()->nextWeek()->create();
    $last = Sprint::factory()->lastWeek()->create();

    expect($current->isCurrent())->toBeTrue()
        ->and($current->isUpcoming())->toBeFalse()
        ->and($next->isUpcoming())->toBeTrue()
        ->and($last->isCurrent())->toBeFalse()
        ->and(Sprint::currentOrUpcoming()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$current->id, $next->id])->sort()->values()->all());
});

test('a completed sprint is not current or upcoming', function () {
    $sprint = Sprint::factory()->create();
    $sprint->forceFill(['status' => SprintStatus::Completed])->save();

    expect(Sprint::currentOrUpcoming()->count())->toBe(0);
});

test('a task in a current or upcoming sprint is not available for another sprint', function () {
    $free = Task::factory()->create();
    $planned = Task::factory()->create();
    $inOldSprint = Task::factory()->create();

    $current = Sprint::factory()->create();
    SprintCommitment::factory()->for($current)->create(['task_id' => $planned->id]);
    SprintCommitment::factory()->for(Sprint::factory()->lastWeek())->create(['task_id' => $inOldSprint->id]);

    expect(Task::availableForSprint()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$free->id, $inOldSprint->id])->sort()->values()->all())
        ->and(Task::availableForSprint($current)->pluck('id'))->toContain($planned->id);
});

test('the same task cannot be committed twice to one sprint', function () {
    $sprint = Sprint::factory()->create();
    $task = Task::factory()->create();

    SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]);
    SprintCommitment::factory()->for($sprint)->create(['task_id' => $task->id]);
})->throws(UniqueConstraintViolationException::class);

test('a commitment keeps the task title and points after the task is deleted', function () {
    $task = Task::factory()->create(['title' => 'Write the method section', 'story_points' => StoryPoints::Five]);
    $commitment = SprintCommitment::factory()->create(['task_id' => $task->id]);

    $task->delete();
    $commitment->refresh();

    expect($commitment->task_id)->toBeNull()
        ->and($commitment->task_title)->toBe('Write the method section')
        ->and($commitment->committed_points)->toBe(5);
});
