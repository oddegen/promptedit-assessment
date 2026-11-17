<?php

use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

it('submits the generation with its own id as the idempotency key and records it as queued', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeGeneration($generation, 'queued', ['id' => 'gen_new']), 202),
    ]);

    SubmitGeneration::dispatch($generation);

    expect($generation->refresh())->provider_id->toBe('gen_new')->status->toBe(GenerationStatus::Queued);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->id));
});

it('applies whatever state a replayed request returns', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeGeneration($generation, 'succeeded'), 202, ['Idempotent-Replayed' => 'true']),
    ]);

    SubmitGeneration::dispatch($generation);

    expect($generation->refresh())->status->toBe(GenerationStatus::Succeeded)->price->toBe(381_887_500);
});

it('waits at least as long as the provider asks before retrying', function (string $code, int $status, int $retryAfter, int $shortestWait) {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => $code, 'message' => 'Busy.']], $status, ['Retry-After' => (string) $retryAfter]),
    ]);
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertReleased();
    expect($job->job->releaseDelay)->toBeGreaterThanOrEqual($shortestWait)->toBeLessThanOrEqual($shortestWait + 3)
        ->and($generation->refresh()->status)->toBe(GenerationStatus::Pending);
})->with([
    'overloaded' => ['overloaded', 503, 2, 5],
    'too many running' => ['concurrency_limit_exceeded', 429, 30, 30],
]);

it('fails the generation for free when the provider rejects the request', function (string $code, int $status) {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => $code, 'message' => 'Rejected.']], $status),
    ]);
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();

    app()->call([$job, 'handle']);

    $job->assertNotFailed();
    expect($generation->refresh())->status->toBe(GenerationStatus::Failed)->error_code->toBe($code)->price->toBeNull();
})->with([
    'blocked prompt' => ['content_policy_violation', 400],
    'invalid request' => ['validation_failed', 422],
    'daily quota used up' => ['daily_limit_exceeded', 429],
]);

it('fails the job and the generation when our credentials are rejected', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'invalid_api_key', 'message' => 'Invalid key.']], 401),
    ]);

    SubmitGeneration::dispatch($generation);

    expect($generation->refresh())->status->toBe(GenerationStatus::Failed)->error_code->toBe('submission_failed');
});

it('waits for a free slot instead of calling the provider when three generations are running', function () {
    Generation::factory()->count(3)->processing()->create();
    $generation = Generation::factory()->create();

    SubmitGeneration::dispatch($generation);

    Http::assertNothingSent();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Pending);
});

it('does not submit a generation the provider already has', function () {
    $generation = Generation::factory()->queued()->create();
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();

    $job->handle(app(PixelforgeClient::class), app(SyncGeneration::class));

    Http::assertNothingSent();
});

it('shares one rate limit for every call to the provider', function () {
    expect(RateLimiter::limiter('pixelforge'))->not->toBeNull();
});
