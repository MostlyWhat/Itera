<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Area;
use App\Models\User;

/*
 * Actions end with back(). Without a Referer header, back() uses the last
 * page the user saw, also when Inertia showed that page.
 */

/**
 * The headers of an Inertia visit, with the current asset version.
 *
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function inertiaHeaders(array $extra = []): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        ...$extra,
    ];
}

test('an action without a referer goes back to the last inertia page', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    // A full page load of the dashboard, then an Inertia visit to Priority.
    $this->actingAs($user)->get(route('dashboard', $slug))->assertOk();
    $this->actingAs($user)
        ->withHeaders(inertiaHeaders())
        ->get(route('priority', $slug))
        ->assertOk();

    // The action comes without a Referer header.
    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => '', 'X-Requested-With' => '', 'X-Inertia-Version' => ''])
        ->put(route('priority.mode', $slug), ['sorting_mode' => 'deadline'])
        ->assertRedirect(route('priority', $slug));
});

test('a partial reload does not change the page to go back to', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;
    Area::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->withHeaders(inertiaHeaders())
        ->get(route('priority', $slug))
        ->assertOk();
    $this->actingAs($user)
        ->withHeaders(inertiaHeaders(['X-Inertia-Partial-Data' => 'sprints', 'X-Inertia-Partial-Component' => 'sprints/Index']))
        ->get(route('sprints.index', $slug))
        ->assertOk();

    $this->actingAs($user)
        ->withHeaders(['X-Inertia' => '', 'X-Requested-With' => '', 'X-Inertia-Version' => ''])
        ->put(route('priority.mode', $slug), ['sorting_mode' => 'balanced'])
        ->assertRedirect(route('priority', $slug));
});

test('a full page load still sets the page to go back to', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    // Only a full page load of the dashboard: back() returns there.
    $this->actingAs($user)->get(route('dashboard', $slug))->assertOk();

    $this->actingAs($user)
        ->put(route('priority.mode', $slug), ['sorting_mode' => 'deadline'])
        ->assertRedirect(route('dashboard', $slug));
});
