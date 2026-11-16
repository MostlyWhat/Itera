<?php

use App\Models\User;

/*
 * ITERA-01: the system prevents users from viewing or changing the
 * information of another user. Each user has a personal team, and all
 * planning pages live under that team's URL.
 */

test('users cannot view the dashboard of another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard', $otherUser->currentTeam->slug));

    $response->assertForbidden();
    expect($user->fresh()->current_team_id)->toBe($user->current_team_id);
});

test('users cannot view the team settings of another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $otherUser->currentTeam));

    $response->assertForbidden();
});

test('users cannot change the team of another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherTeam = $otherUser->currentTeam;

    $response = $this
        ->actingAs($user)
        ->patch(route('teams.update', $otherTeam), [
            'name' => 'Changed by someone else',
        ]);

    $response->assertForbidden();
    expect($otherTeam->fresh()->name)->toBe($otherTeam->name);
});

test('a profile update changes only the signed in user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'id' => $otherUser->id,
            'name' => 'New Name',
            'email' => $user->email,
        ]);

    $response->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('New Name');
    expect($otherUser->fresh()->name)->toBe($otherUser->name);
});

test('users cannot take the email of another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $otherUser->email,
        ]);

    $response->assertSessionHasErrors('email');
    expect($user->fresh()->email)->toBe($user->email);
});
