<?php

use App\Models\Area;
use App\Models\Project;
use App\Models\User;

/*
 * ITERA-02: users add Projects under their Areas, and rename and archive
 * them. Only members of the team that owns the Area can change a Project.
 */

test('users can add a project to their area', function () {
    $user = User::factory()->create();
    $area = Area::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('projects.store', [$user->currentTeam->slug, $area]), ['name' => 'Lab report'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $project = $area->projects()->sole();

    expect($project->name)->toBe('Lab report')
        ->and($project->position)->toBe(1);
});

test('a project needs a valid name', function (mixed $name) {
    $user = User::factory()->create();
    $area = Area::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('projects.store', [$user->currentTeam->slug, $area]), ['name' => $name])
        ->assertSessionHasErrors('name');

    expect(Project::count())->toBe(0);
})->with([
    'empty' => [''],
    'too long' => [str_repeat('a', 101)],
]);

test('users cannot add a project to an archived area', function () {
    $user = User::factory()->create();
    $area = Area::factory()->for($user->currentTeam)->archived()->create();

    $this->actingAs($user)
        ->post(route('projects.store', [$user->currentTeam->slug, $area]), ['name' => 'Lab report'])
        ->assertSessionHasErrors('name');

    expect(Project::count())->toBe(0);
});

test('users can rename their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()
        ->for(Area::factory()->for($user->currentTeam))
        ->create(['name' => 'Lab']);

    $this->actingAs($user)
        ->patch(route('projects.update', [$user->currentTeam->slug, $project]), ['name' => 'Lab report'])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->name)->toBe('Lab report');
});

test('users can archive and restore their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)->post(route('projects.archive', [$slug, $project]))->assertRedirect();

    expect($project->fresh()->isArchived())->toBeTrue();

    $this->actingAs($user)->delete(route('projects.unarchive', [$slug, $project]))->assertRedirect();

    expect($project->fresh()->isArchived())->toBeFalse();
});

test('users cannot change the projects of another user', function () {
    $user = User::factory()->create();
    $otherArea = Area::factory()->for(User::factory()->create()->currentTeam)->create();
    $otherProject = Project::factory()->for($otherArea)->create(['name' => 'Private']);
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->post(route('projects.store', [$slug, $otherArea]), ['name' => 'Intruder'])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('projects.update', [$slug, $otherProject]), ['name' => 'Changed'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('projects.archive', [$slug, $otherProject]))
        ->assertForbidden();

    expect($otherProject->fresh()->name)->toBe('Private')
        ->and($otherProject->fresh()->isArchived())->toBeFalse()
        ->and($otherArea->projects()->count())->toBe(1);
});

test('users can give their project a description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patch(route('projects.update', [$user->currentTeam->slug, $project]), [
            'name' => 'Lab report',
            'description' => '  Biology 101, due in week 12.  ',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->description)->toBe('Biology 101, due in week 12.');
});

test('a rename without a description keeps the description', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create(['description' => 'Biology 101']);

    $this->actingAs($user)
        ->patch(route('projects.update', [$user->currentTeam->slug, $project]), ['name' => 'Lab report'])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->description)->toBe('Biology 101');
});

test('an empty description removes it, and a long one is rejected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for(Area::factory()->for($user->currentTeam))->create(['description' => 'Biology 101']);
    $url = route('projects.update', [$user->currentTeam->slug, $project]);

    $this->actingAs($user)->patch($url, ['name' => 'Lab report', 'description' => ' ']);
    expect($project->fresh()->description)->toBeNull();

    $this->actingAs($user)
        ->patch($url, ['name' => 'Lab report', 'description' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('description');
});
