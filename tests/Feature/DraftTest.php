<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

$draft = ['prompt' => 'A lighthouse on a cliff at dawn', 'size' => '1536x1024', 'quality' => 'high'];

test('a guest\'s prompt is kept for after they sign up', function () use ($draft) {
    $response = $this->from(route('home'))->post(route('draft.store'), $draft);

    $response->assertRedirect(route('home'))->assertSessionHas('draft', $draft);
});

test('a signed-in user goes straight to the studio with the prompt filled in once', function () use ($draft) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('draft.store'), $draft)->assertRedirect(route('studio'));

    $this->actingAs($user)->get(route('studio'))
        ->assertInertia(fn (Assert $page) => $page->where('prefill', $draft));
    $this->actingAs($user)->get(route('studio'))
        ->assertInertia(fn (Assert $page) => $page->where('prefill', null));
});

test('a draft needs a prompt', function () use ($draft) {
    $response = $this->from(route('home'))->post(route('draft.store'), [...$draft, 'prompt' => '']);

    $response->assertSessionHasErrors(['prompt' => 'Describe the image you want.'])->assertSessionMissing('draft');
});
