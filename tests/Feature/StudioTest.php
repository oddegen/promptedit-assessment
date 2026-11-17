<?php

use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('studio'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the studio', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('studio'));
    $response->assertOk();
});

test('the studio shows only the user\'s own images and what they have spent', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded()->create();
    Generation::factory()->succeeded()->create();

    $response = $this->actingAs($user)->get(route('studio'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Studio')
        ->has('generations', 1)
        ->missing('generations.0.cost')
        ->missing('generations.0.rates')
        ->where('summary.count', 1)
        ->where('summary.spent', 381_887_500));
});

test('the studio starts from one of the user\'s generations when asked', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->for($user)->failed()->create();

    $response = $this->actingAs($user)->get(route('studio', ['from' => $generation->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('draft.id', $generation->id)
        ->where('draft.prompt', $generation->prompt));
});

test('the studio ignores another user\'s generation as a starting point', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->failed()->create();

    $response = $this->actingAs($user)->get(route('studio', ['from' => $generation->id]));

    $response->assertInertia(fn (Assert $page) => $page->where('draft', null));
});

test('the studio shows each idea once, as its latest version', function () {
    $user = User::factory()->create();
    $original = Generation::factory()->for($user)->failed()->create();
    Generation::factory()->for($user)->failed()->create(['parent_id' => $original->id]);
    $latest = Generation::factory()->for($user)->succeeded()->create(['parent_id' => $original->id]);
    Generation::factory()->for($user)->succeeded()->create();

    $response = $this->actingAs($user)->get(route('studio'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('generations', 2)
        ->where('generations.1.id', $latest->id)
        ->where('generations.1.version_count', 3)
        ->where('generations.0.version_count', 1)
        ->where('summary.count', 2)
        ->where('summary.spent', 2 * 381_887_500));
});
