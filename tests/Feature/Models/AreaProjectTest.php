<?php

use App\Models\Area;
use App\Models\Project;
use App\Models\Team;

/*
 * ITERA-02: the data layer for Areas and Projects.
 */

test('a new area goes to the end of its team list', function () {
    $team = Team::factory()->create();

    $first = Area::factory()->for($team)->create();
    $second = Area::factory()->for($team)->create();

    expect($first->position)->toBe(1)
        ->and($second->position)->toBe(2)
        ->and($team->areas->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('area positions are counted per team', function () {
    $area = Area::factory()->create();
    $otherTeamArea = Area::factory()->create();

    expect($area->position)->toBe(1)
        ->and($otherTeamArea->position)->toBe(1);
});

test('a new project goes to the end of its area list', function () {
    $area = Area::factory()->create();

    $first = Project::factory()->for($area)->create();
    $second = Project::factory()->for($area)->create();

    expect($first->position)->toBe(1)
        ->and($second->position)->toBe(2)
        ->and($area->projects->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('an area can be archived and restored', function () {
    $area = Area::factory()->create();

    $area->archive();

    expect($area->fresh()->isArchived())->toBeTrue()
        ->and(Area::active()->count())->toBe(0)
        ->and(Area::archived()->count())->toBe(1);

    $area->unarchive();

    expect($area->fresh()->isArchived())->toBeFalse()
        ->and(Area::active()->count())->toBe(1);
});

test('a project can be archived and restored', function () {
    $project = Project::factory()->create();

    $project->archive();

    expect($project->fresh()->isArchived())->toBeTrue()
        ->and(Project::active()->count())->toBe(0);

    $project->unarchive();

    expect($project->fresh()->isArchived())->toBeFalse();
});

test('archiving an area keeps its projects', function () {
    $area = Area::factory()->has(Project::factory()->count(2))->create();

    $area->archive();

    expect($area->fresh()->projects)->toHaveCount(2);
});

test('deleting a team deletes its areas and projects', function () {
    $area = Area::factory()->has(Project::factory())->create();

    $area->team->forceDelete();

    expect(Area::count())->toBe(0)
        ->and(Project::count())->toBe(0);
});
