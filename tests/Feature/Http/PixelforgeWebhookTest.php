<?php

use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => config(['services.pixelforge.webhook_secret' => 'whsec_test']));

function sendPixelforgeWebhook(array $event, string $secret = 'whsec_test', ?int $timestamp = null, array $headers = []): TestResponse
{
    $body = json_encode($event);
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', route('webhooks.pixelforge'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'HTTP_PIXELFORGE_DELIVERY_ATTEMPT' => $headers['attempt'] ?? '1',
    ], $body);
}

function generationEvent(Generation $generation, string $status, string $id = 'evt_01', array $overrides = []): array
{
    return [
        'id' => $id,
        'object' => 'event',
        'type' => "generation.{$status}",
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => pixelforgeGeneration($generation, $status, $overrides)],
    ];
}

it('applies a signed event to its generation and answers 204', function () {
    $generation = Generation::factory()->processing()->create();

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'), headers: ['attempt' => '2'])->assertNoContent();

    expect($generation->refresh())->status->toBe(GenerationStatus::Succeeded)->price->toBe(381_887_500);
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_01', 'generation_id' => $generation->id, 'attempt' => 2]);
});

it('rejects a forged signature with 400 and records nothing', function () {
    $generation = Generation::factory()->processing()->create();

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'), secret: 'whsec_wrong')->assertBadRequest();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing);
    $this->assertDatabaseCount('webhook_events', 0);
});

it('rejects a signature older than five minutes with 400', function () {
    $generation = Generation::factory()->processing()->create();

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'), timestamp: now()->subSeconds(301)->getTimestamp())->assertBadRequest();

    $this->assertDatabaseCount('webhook_events', 0);
});

it('rejects every delivery with 400 while no signing secret is configured', function () {
    config(['services.pixelforge.webhook_secret' => null]);
    $generation = Generation::factory()->processing()->create();

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'), secret: '')->assertBadRequest();
});

it('applies an event delivered twice only once, even if the second copy differs', function () {
    $generation = Generation::factory()->processing()->create();
    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'));

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded', overrides: [
        'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 8000],
    ]))->assertNoContent();

    expect($generation->refresh())->reasoning_tokens->toBe(3184)->price->toBe(381_887_500);
    $this->assertDatabaseCount('webhook_events', 1);
});

it('finds a generation by our own id when the event beats the create response', function () {
    $generation = Generation::factory()->create();

    sendPixelforgeWebhook(generationEvent($generation, 'processing', overrides: ['id' => 'gen_early']))->assertNoContent();

    expect($generation->refresh())->provider_id->toBe('gen_early')->status->toBe(GenerationStatus::Processing);
});

it('acknowledges a dashboard test ping', function () {
    sendPixelforgeWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => []])->assertNoContent();

    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_ping', 'type' => 'ping', 'generation_id' => null]);
});

it('acknowledges events for generations it does not know', function () {
    $stranger = Generation::factory()->make(['id' => '01JC2Z8Q4N6V0M3H7X5T9R2B1K']);

    sendPixelforgeWebhook(generationEvent($stranger, 'succeeded', overrides: ['id' => 'gen_unknown']))->assertNoContent();

    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_01', 'generation_id' => null]);
});

it('forgets an event it failed to apply so the provider retry is applied', function () {
    $generation = Generation::factory()->processing()->create();
    $this->mock(SyncGeneration::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Database went away.'));

    sendPixelforgeWebhook(generationEvent($generation, 'succeeded'))->assertServerError();

    $this->assertDatabaseCount('webhook_events', 0);
});
