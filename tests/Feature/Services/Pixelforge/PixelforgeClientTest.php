<?php

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('fetches the price list once and serves it from the cache', function () {
    Http::fake([
        'pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing()),
    ]);
    $client = app(PixelforgeClient::class);

    $client->prices();
    $prices = $client->prices();

    expect($prices->rates['output_tokens'])->toBe('40.00');
    Http::assertSentCount(1);
});

it('submits a generation with its own id as the idempotency key and metadata', function () {
    config(['services.pixelforge.key' => 'pf_test_key']);
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeGeneration($generation, 'queued', ['id' => 'gen_01']), 202),
    ]);

    $remote = app(PixelforgeClient::class)->createGeneration($generation);

    expect($remote)->id->toBe('gen_01')->generationId->toBe($generation->id);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request->hasHeader('Idempotency-Key', $generation->id)
        && $request->data() === [
            'prompt' => $generation->prompt,
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/reference.png'],
            'metadata' => ['generation_id' => $generation->id],
        ]);
});

it('raises the Pixelforge error code and wait time when the account is at its concurrency limit', function () {
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations/gen_01' => Http::response([
            'error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Too many generations queued or processing.'],
        ], 429, ['Retry-After' => '5']),
    ]);

    expect(fn () => app(PixelforgeClient::class)->generation('gen_01'))
        ->toThrow(function (PixelforgeException $exception) {
            expect($exception->errorCode)->toBe('concurrency_limit_exceeded')
                ->and($exception->status)->toBe(429)
                ->and($exception->retryAfter)->toBe(5)
                ->and($exception->getMessage())->toBe('Too many generations queued or processing.');
        });
});

it('treats only temporary Pixelforge errors as retryable', function (string $code, int $status, bool $retryable) {
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations/gen_01' => Http::response(['error' => ['code' => $code, 'message' => 'Rejected.']], $status),
    ]);

    expect(fn () => app(PixelforgeClient::class)->generation('gen_01'))
        ->toThrow(fn (PixelforgeException $exception) => expect($exception->isRetryable())->toBe($retryable));
})->with([
    'rate limited' => ['rate_limit_exceeded', 429, true],
    'too many running' => ['concurrency_limit_exceeded', 429, true],
    'overloaded' => ['overloaded', 503, true],
    'daily quota used up' => ['daily_limit_exceeded', 429, false],
    'prompt blocked' => ['content_policy_violation', 400, false],
    'invalid key' => ['invalid_api_key', 401, false],
]);
