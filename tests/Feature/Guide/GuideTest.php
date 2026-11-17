<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The guide explains the words and the weekly rhythm of Itera.
 */

test('members can open the guide', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('guide', $user->currentTeam->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Guide'));
});

test('guests cannot open the guide', function () {
    $user = User::factory()->create();

    $this->get(route('guide', $user->currentTeam->slug))->assertRedirect(route('login'));
});
