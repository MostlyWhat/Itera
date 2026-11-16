<?php

use App\Models\Area;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ITERA-02: users create, rename, and archive their Areas. Only members of
 * the owning team can change an Area.
 */

test('guests cannot see the areas page', function () {
    $user = User::factory()->create();

    $this->get(route('areas.index', $user->currentTeam->slug))
        ->assertRedirect(route('login'));
});

test('the areas page lists active and archived areas of the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $area = Area::factory()->for($team)->create(['name' => 'Biology 201']);
    Project::factory()->for($area)->create(['name' => 'Lab report']);
    Project::factory()->for($area)->archived()->create(['name' => 'Old quiz']);
    Area::factory()->for($team)->archived()->create(['name' => 'Summer job']);
    Area::factory()->create(['name' => 'Someone else']);

    $this->actingAs($user)
        ->get(route('areas.index', $team->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('areas/Index', false)
            ->has('areas', 1)
            ->where('areas.0.name', 'Biology 201')
            ->has('areas.0.projects', 1)
            ->where('areas.0.projects.0.name', 'Lab report')
            ->has('archivedAreas', 1)
            ->where('archivedAreas.0.name', 'Summer job')
            ->has('archivedProjects', 1)
            ->where('archivedProjects.0.name', 'Old quiz')
            ->where('archivedProjects.0.areaName', 'Biology 201'));
});

test('users can create an area', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('areas.store', $user->currentTeam->slug), ['name' => '  Biology 201  '])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $area = $user->currentTeam->areas()->sole();

    expect($area->name)->toBe('Biology 201')
        ->and($area->position)->toBe(1);
});

test('an area needs a valid name', function (mixed $name) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('areas.store', $user->currentTeam->slug), ['name' => $name])
        ->assertSessionHasErrors('name');

    expect(Area::count())->toBe(0);
})->with([
    'empty' => [''],
    'only spaces' => ['   '],
    'too long' => [str_repeat('a', 101)],
]);

test('users can rename their area', function () {
    $user = User::factory()->create();
    $area = Area::factory()->for($user->currentTeam)->create(['name' => 'Bio']);

    $this->actingAs($user)
        ->patch(route('areas.update', [$user->currentTeam->slug, $area]), ['name' => 'Biology 201'])
        ->assertSessionHasNoErrors();

    expect($area->fresh()->name)->toBe('Biology 201');
});

test('users can archive and restore their area', function () {
    $user = User::factory()->create();
    $area = Area::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('areas.archive', [$user->currentTeam->slug, $area]))
        ->assertRedirect();

    expect($area->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('areas.unarchive', [$user->currentTeam->slug, $area]))
        ->assertRedirect();

    expect($area->fresh()->isArchived())->toBeFalse();
});

test('users cannot rename or archive the area of another user', function () {
    $user = User::factory()->create();
    $otherArea = Area::factory()->for(User::factory()->create()->currentTeam)->create(['name' => 'Private']);
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->patch(route('areas.update', [$slug, $otherArea]), ['name' => 'Changed'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('areas.archive', [$slug, $otherArea]))
        ->assertForbidden();

    expect($otherArea->fresh()->name)->toBe('Private')
        ->and($otherArea->fresh()->isArchived())->toBeFalse();
});

test('users cannot open the areas page of another team', function () {
    $user = User::factory()->create();
    $otherTeam = User::factory()->create()->currentTeam;

    $this->actingAs($user)
        ->get(route('areas.index', $otherTeam->slug))
        ->assertForbidden();
});
