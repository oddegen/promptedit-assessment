<?php

use App\Actions\SyncGeneration;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\RemoteGeneration;

it('records the cost, price, tokens and image once the generation succeeds', function () {
    $generation = Generation::factory()->processing()->create();

    app(SyncGeneration::class)->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded')));

    expect($generation)
        ->status->toBe(GenerationStatus::Succeeded)
        ->cost->toBe(305_510_000)
        ->price->toBe(381_887_500)
        ->reasoning_tokens->toBe(3184)
        ->image_url->toStartWith('https://pixelforge.promptedit.dev/images/')
        ->completed_at->toDateTimeString()->toBe('2026-11-04 14:03:29');
});

it('ignores a processing event that arrives after the generation succeeded', function () {
    $generation = Generation::factory()->processing()->create();
    $sync = app(SyncGeneration::class);
    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded')));

    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'processing')));

    expect($generation)->status->toBe(GenerationStatus::Succeeded)->price->toBe(381_887_500);
});

it('keeps the first price when success is reported again with different usage', function () {
    $generation = Generation::factory()->processing()->create();
    $sync = app(SyncGeneration::class);
    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded')));

    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded', [
        'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 8000],
    ])));

    expect($generation)->reasoning_tokens->toBe(3184)->price->toBe(381_887_500);
});

it('takes the provider id from an early webhook without moving back when the create response arrives', function () {
    $generation = Generation::factory()->create();
    $sync = app(SyncGeneration::class);
    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'processing', ['id' => 'gen_early'])));

    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'queued', ['id' => 'gen_early'])));

    expect($generation)->provider_id->toBe('gen_early')->status->toBe(GenerationStatus::Processing);
});

it('bills a generation on the day the provider finished it, not the day the event arrived', function () {
    $this->travelTo('2026-11-05 00:00:10');
    $generation = Generation::factory()->processing()->create();

    app(SyncGeneration::class)->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded', [
        'completed_at' => '2026-11-04T23:59:50Z',
    ])));

    expect($generation->completed_at->toDateString())->toBe('2026-11-04');
});

it('stores the error and charges nothing when the generation fails', function () {
    $generation = Generation::factory()->processing()->create();

    app(SyncGeneration::class)->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'failed')));

    expect($generation)
        ->status->toBe(GenerationStatus::Failed)
        ->error_code->toBe('generation_failed')
        ->cost->toBeNull()
        ->price->toBeNull();
});

it('fails a generation that never reached the provider', function () {
    $generation = Generation::factory()->create();

    app(SyncGeneration::class)->fail($generation, 'content_policy_violation', 'The prompt was rejected.');

    expect($generation)->status->toBe(GenerationStatus::Failed)->error_code->toBe('content_policy_violation');
});

it('does not fail a generation the provider already accepted', function () {
    $generation = Generation::factory()->queued()->create();

    app(SyncGeneration::class)->fail($generation, 'submission_failed');

    expect($generation)->status->toBe(GenerationStatus::Queued)->error_code->toBeNull();
});

it('charges the user exactly once however many times success is reported', function () {
    $generation = Generation::factory()->processing()->create();
    $sync = app(SyncGeneration::class);

    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded')));
    $sync->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'succeeded')));

    $charges = $generation->user->creditTransactions()->where('type', CreditTransactionType::Charge)->get();
    expect($charges)->toHaveCount(1)
        ->and($charges->first())->amount->toBe(-381_887_500)->generation_id->toBe($generation->id);
});

it('never charges for a generation that failed', function () {
    $generation = Generation::factory()->processing()->create();

    app(SyncGeneration::class)->handle($generation, RemoteGeneration::fromArray(pixelforgeGeneration($generation, 'failed')));

    expect($generation->user->creditTransactions()->count())->toBe(0);
});
