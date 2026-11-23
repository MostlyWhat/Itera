<?php

use App\Actions\Tasks\CreateWeeklyTasks;
use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\RecurrenceTemplate;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-09: users repeat a task every week on one weekday. Itera makes one
 * Task for each week, never two for the same date, and each week's Task
 * keeps its own status.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // Wednesday
afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: User, 1: Project, 2: string}
 */
function userWithWeeklyProject(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create(['name' => 'Biology']);

    return [$user, $project, $user->currentTeam->slug];
}

test('users can make a task repeat on a weekday', function () {
    [$user, $project, $slug] = userWithWeeklyProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), [
            'title' => 'Do the weekly quiz',
            'story_points' => 2,
            'repeat_weekday' => 5, // Friday
        ])
        ->assertSessionHasNoErrors();

    $template = RecurrenceTemplate::sole();
    expect($template)->weekday->toBe(5)->title->toBe('Do the weekly quiz')->story_points->toBe(StoryPoints::Two);

    // This week and next week: Friday 20 and Friday 27 November.
    expect($project->tasks()->orderBy('due_date')->get()->map(fn (Task $task) => $task->due_date->toDateString())->all())
        ->toBe(['2026-11-20', '2026-11-27']);
});

test('a weekday that already passed this week starts next week', function () {
    [$user, $project, $slug] = userWithWeeklyProject();

    $this->actingAs($user)->post(route('tasks.store', [$slug, $project]), ['title' => 'Plan the shifts', 'repeat_weekday' => 1]);

    // Monday 16 November is past, so the Tasks are on 23 and 30 November.
    expect($project->tasks()->orderBy('due_date')->pluck('due_date')->map->toDateString()->all())
        ->toBe(['2026-11-23', '2026-11-30']);
});

test('running the weekly job again makes no duplicate tasks', function () {
    [$user, $project] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['weekday' => 5]);
    $weekly = app(CreateWeeklyTasks::class);

    expect($weekly->forTemplate($template))->toBe(2)
        ->and($weekly->forTemplate($template))->toBe(0)
        ->and($weekly->forTeam($user->currentTeam))->toBe(0)
        ->and(Task::count())->toBe(2);
});

test('the database refuses a second task for the same week', function () {
    [, $project] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create();
    $task = Task::factory()->for($project)->make();
    $task->recurrence_template_id = $template->id;
    $task->recurrence_date = Carbon::parse('2026-11-23');
    $task->save();

    $copy = $task->replicate();

    expect(fn () => $copy->save())->toThrow(UniqueConstraintViolationException::class);
});

test('a deleted weekly task does not come back', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);
    $thisWeek = $template->tasks()->orderBy('recurrence_date')->first();

    $this->actingAs($user)->delete(route('tasks.destroy', [$slug, $thisWeek]));
    $this->actingAs($user)->get(route('projects.show', [$slug, $project]))->assertOk();

    expect($template->tasks()->pluck('recurrence_date')->map->toDateString()->all())->toBe(['2026-11-27']);
});

test('a new week makes its task when the user opens a page', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);

    // One week later, the next Friday comes into the window.
    Carbon::setTestNow('2026-11-25 09:00');
    $this->actingAs($user)->get(route('projects.show', [$slug, $project]))->assertOk();

    expect($template->tasks()->orderBy('recurrence_date')->pluck('recurrence_date')->map->toDateString()->all())
        ->toBe(['2026-11-20', '2026-11-27', '2026-12-04']);
});

test('each weekly task keeps its own status', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);
    [$thisWeek, $nextWeek] = $template->tasks()->orderBy('recurrence_date')->get()->all();

    $this->actingAs($user)->patch(route('tasks.status.update', [$slug, $thisWeek]), ['status' => 'done']);

    expect($thisWeek->fresh()->status)->toBe(TaskStatus::Done)
        ->and($nextWeek->fresh()->status)->toBe(TaskStatus::Todo);

    // The done week is not made again.
    Carbon::setTestNow('2026-11-19 09:00');
    app(CreateWeeklyTasks::class)->forTemplate($template);
    expect($template->tasks()->count())->toBe(2);
});

test('the project page marks weekly tasks with their day', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['title' => 'Do the weekly quiz', 'weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);
    Task::factory()->for($project)->create(['title' => 'One-off task']);

    $this->actingAs($user)
        ->get(route('projects.show', [$slug, $project]))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('weekly')
            ->where('tasks', function ($tasks) use ($template) {
                $oneOff = collect($tasks)->firstWhere('title', 'One-off task');
                $weekly = collect($tasks)->where('title', 'Do the weekly quiz');

                return $oneOff['isWeekly'] === false && $oneOff['repeatsOn'] === null
                    && $weekly->count() === 2
                    && $weekly->every(fn ($task) => $task['isWeekly'] && $task['repeatsOn'] === 'Friday' && $task['weeklyId'] === $template->id);
            }));
});

test('editing a weekly task changes the later open weeks too', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['title' => 'Quiz', 'weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);
    [$thisWeek, $nextWeek] = $template->tasks()->orderBy('recurrence_date')->get()->all();

    $this->actingAs($user)
        ->patch(route('tasks.update', [$slug, $thisWeek]), ['title' => 'Weekly quiz', 'story_points' => 3, 'due_date' => '2026-11-20'])
        ->assertSessionHasNoErrors();

    expect($thisWeek->fresh()->title)->toBe('Weekly quiz')
        ->and($nextWeek->fresh())->title->toBe('Weekly quiz')->story_points->toBe(StoryPoints::Three)
        ->and($nextWeek->fresh()->due_date->toDateString())->toBe('2026-11-27')
        ->and($template->fresh()->title)->toBe('Weekly quiz');

    // A new week uses the new title.
    Carbon::setTestNow('2026-11-25 09:00');
    app(CreateWeeklyTasks::class)->forTemplate($template->fresh());
    expect($template->tasks()->whereDate('recurrence_date', '2026-12-04')->value('title'))->toBe('Weekly quiz');
});

test('editing an earlier week leaves a later week that is done', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['title' => 'Quiz', 'weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);
    [$thisWeek, $nextWeek] = $template->tasks()->orderBy('recurrence_date')->get()->all();
    $nextWeek->update(['status' => TaskStatus::Done]);

    $this->actingAs($user)->patch(route('tasks.update', [$slug, $thisWeek]), ['title' => 'Weekly quiz']);

    expect($nextWeek->fresh()->title)->toBe('Quiz');
});

test('a one-off task can start to repeat from the edit panel', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $task = Task::factory()->for($project)->create(['title' => 'Read chapter', 'due_date' => '2026-11-19']);

    $this->actingAs($user)
        ->patch(route('tasks.update', [$slug, $task]), ['title' => 'Read a chapter', 'repeat_weekday' => 5])
        ->assertSessionHasNoErrors();

    $template = RecurrenceTemplate::sole();
    $task->refresh();

    expect($template)->weekday->toBe(5)->title->toBe('Read a chapter')
        ->and($task->recurrence_template_id)->toBe($template->id)
        ->and($task->due_date->toDateString())->toBe('2026-11-20')
        ->and($template->tasks()->orderBy('recurrence_date')->pluck('recurrence_date')->map->toDateString()->all())
        ->toBe(['2026-11-20', '2026-11-27']);
});

test('stopping a weekly task keeps its past tasks', function () {
    [$user, $project, $slug] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($project)->create(['weekday' => 5]);
    app(CreateWeeklyTasks::class)->forTemplate($template);

    $this->actingAs($user)->delete(route('weekly.destroy', [$slug, $template]))->assertSessionHasNoErrors();

    expect(RecurrenceTemplate::count())->toBe(0)
        ->and(Task::count())->toBe(2)
        ->and(Task::query()->whereNotNull('recurrence_template_id')->count())->toBe(0);
});

test('a repeat day must be a weekday', function () {
    [$user, $project, $slug] = userWithWeeklyProject();

    $this->actingAs($user)
        ->post(route('tasks.store', [$slug, $project]), ['title' => 'Quiz', 'repeat_weekday' => 7])
        ->assertSessionHasErrors('repeat_weekday');

    expect(RecurrenceTemplate::count())->toBe(0);
});

test('users cannot stop the weekly task of another user', function () {
    [$user, , $slug] = userWithWeeklyProject();
    [, $otherProject] = userWithWeeklyProject();
    $template = RecurrenceTemplate::factory()->for($otherProject)->create();

    $this->actingAs($user)->delete(route('weekly.destroy', [$slug, $template]))->assertForbidden();

    expect(RecurrenceTemplate::count())->toBe(1);
});
