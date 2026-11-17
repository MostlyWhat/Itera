<?php

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Users choose the first day of their week. New sprints start on that day.
 */

beforeEach(fn () => Carbon::setTestNow('2026-11-18 10:00')); // Wednesday.
afterEach(fn () => Carbon::setTestNow());

test('the planning settings show the first day of the week, monday by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('planning.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Planning')->where('weekStart', 1));
});

test('users can change the first day of the week', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('planning.update'), ['week_start' => 0])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('planning.edit'));

    expect($user->fresh()->week_start)->toBe(0);
});

test('the first day of the week must be a day', function (mixed $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('planning.update'), ['week_start' => $value])
        ->assertSessionHasErrors('week_start');
})->with([7, -1, 'monday', '']);

test('with a sunday week, sprints start on sunday and the free weeks follow', function () {
    $user = User::factory()->create(['week_start' => 0]);
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->get(route('sprints.index', $slug))
        ->assertInertia(fn (Assert $page) => $page->where('freeWeeks.0', '2026-11-15'));

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-16'])
        ->assertSessionHasErrors('week_start');

    $this->actingAs($user)
        ->post(route('sprints.store', $slug), ['week_start' => '2026-11-15'])
        ->assertSessionHasNoErrors();

    $sprint = $user->currentTeam->sprints()->sole();

    expect($sprint->week_end->toDateString())->toBe('2026-11-21');
});

test('a new sprint cannot overlap a sprint with another start day', function () {
    $user = User::factory()->create(['week_start' => 0]);
    Sprint::factory()->for($user->currentTeam)->create(['week_start' => '2026-11-16']); // Monday to Sunday.

    $this->actingAs($user)
        ->post(route('sprints.store', $user->currentTeam->slug), ['week_start' => '2026-11-22'])
        ->assertSessionHasErrors('week_start');

    $this->actingAs($user)
        ->get(route('sprints.index', $user->currentTeam->slug))
        ->assertInertia(fn (Assert $page) => $page->where('freeWeeks.0', '2026-11-29'));
});
