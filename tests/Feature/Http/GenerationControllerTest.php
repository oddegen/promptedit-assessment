<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

$request = [
    'prompt' => 'A lighthouse on a cliff at dawn',
    'size' => '1024x1024',
    'quality' => 'medium',
    'reference_images' => ['https://example.com/style.png'],
];

it('starts a generation and returns to the studio', function () use ($request) {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->withCredits()->create();

    $response = $this->actingAs($user)->from(route('studio'))->post(route('generations.store'), $request);

    $response->assertRedirect(route('studio'));
    $generation = $user->generations()->sole();
    expect($generation)
        ->status->toBe(GenerationStatus::Pending)
        ->reference_images->toBe(['https://example.com/style.png'])
        ->max_price->toBeGreaterThan($generation->estimated_price);
    Queue::assertPushed(SubmitGeneration::class);
});

it('rejects a request the image model cannot take', function (array $change, string $field, string $message) use ($request) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('studio'))->post(route('generations.store'), [...$request, ...$change]);

    $response->assertSessionHasErrors([$field => $message]);
    $this->assertDatabaseCount('generations', 0);
})->with([
    'no prompt' => [['prompt' => ''], 'prompt', 'Describe the image you want.'],
    'unknown size' => [['size' => '512x512'], 'size', 'The selected size is invalid.'],
    'five references' => [['reference_images' => array_fill(0, 5, 'https://example.com/a.png')], 'reference_images', 'Add up to 4 reference images.'],
    'reference that is not a web link' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0', 'Use a full http or https link to an image.'],
]);

it('tells the user when the image model cannot be reached and creates nothing', function () use ($request) {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::failedConnection()]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('studio'))->post(route('generations.store'), $request);

    $response->assertRedirect(route('studio'))->assertInertiaFlash('toast.type', 'error');
    $this->assertDatabaseCount('generations', 0);
});

it('shows a generation with its receipt to its owner', function () {
    $generation = Generation::factory()->succeeded()->create();

    $response = $this->actingAs($generation->user)->get(route('generations.show', $generation));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('generations/Show')
        ->where('generation.id', $generation->id)
        ->where('receipt.cost', 305_510_000)
        ->where('receipt.fee', 76_377_500));
});

it('returns 404 for a generation that belongs to someone else', function () {
    $generation = Generation::factory()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('generations.show', $generation));

    $response->assertNotFound();
});

it('lists only generations still in progress when filtered to running', function () {
    $user = User::factory()->create();
    Generation::factory()->for($user)->create();
    Generation::factory()->for($user)->processing()->create();
    Generation::factory()->for($user)->succeeded()->create();
    Generation::factory()->for($user)->failed()->create();

    $response = $this->actingAs($user)->get(route('generations.index', ['filter' => 'running']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('generations/Index')
        ->where('filter', 'running')
        ->has('generations.data', 2));
});

it('starts a new version of one of the user\'s generations', function () use ($request) {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $original = Generation::factory()->for(User::factory()->withCredits())->failed()->create();

    $this->actingAs($original->user)->from(route('studio'))->post(route('generations.store'), [...$request, 'parent_id' => $original->id]);

    expect($original->versions()->sole())->status->toBe(GenerationStatus::Pending);
});

it('refuses to add a version to someone else\'s generation', function () use ($request) {
    $theirs = Generation::factory()->failed()->create();

    $response = $this->actingAs(User::factory()->create())->from(route('studio'))->post(route('generations.store'), [...$request, 'parent_id' => $theirs->id]);

    $response->assertSessionHasErrors('parent_id');
    $this->assertDatabaseCount('generations', 1);
});

it('shows every version of the idea and what they cost together', function () {
    $original = Generation::factory()->failed()->create();
    $latest = Generation::factory()->for($original->user)->succeeded()->create(['parent_id' => $original->id]);
    Generation::factory()->for($original->user)->succeeded()->create();

    $response = $this->actingAs($original->user)->get(route('generations.show', $original));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('versions', 2)
        ->where('versions.0.id', $original->id)
        ->where('versions.1.id', $latest->id)
        ->where('spent', 381_887_500));
});

it('lists every attempt in history with its place among the versions', function () {
    $user = User::factory()->create();
    $original = Generation::factory()->for($user)->failed()->create();
    $retry = Generation::factory()->for($user)->failed()->create(['parent_id' => $original->id]);
    Generation::factory()->for($user)->succeeded()->create(['parent_id' => $original->id]);

    $response = $this->actingAs($user)->get(route('generations.index', ['filter' => 'failed']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('generations.data', 2)
        ->where('generations.data.0.id', $retry->id)
        ->where('generations.data.0.version_number', 2)
        ->where('generations.data.0.version_count', 3)
        ->where('generations.data.1.id', $original->id)
        ->where('generations.data.1.version_number', 1));
});

it('tells the user to slow down instead of showing an error page', function () use ($request) {
    $user = User::factory()->create();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($user)->from(route('studio'))->post(route('generations.store'), ['prompt' => '']);
    }

    $response = $this->actingAs($user)
        ->from(route('studio'))
        ->withHeader('X-Inertia', 'true')
        ->post(route('generations.store'), $request);

    $response->assertRedirect(route('studio'))->assertInertiaFlash('toast.type', 'error');
    $this->assertDatabaseCount('generations', 0);
});

it('opens a new version from the detail page', function () use ($request) {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $original = Generation::factory()->for(User::factory()->withCredits())->failed()->create();

    $response = $this->actingAs($original->user)->post(route('generations.versions.store', $original), $request);

    $version = $original->versions()->sole();
    $response->assertRedirect(route('generations.show', $version));
    expect($version)->status->toBe(GenerationStatus::Pending);
});

it('returns 404 when adding a version to someone else\'s generation', function () use ($request) {
    $theirs = Generation::factory()->failed()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('generations.versions.store', $theirs), $request);

    $response->assertNotFound();
    $this->assertDatabaseCount('generations', 1);
});
