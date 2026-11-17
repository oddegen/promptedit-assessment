<?php

use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

function pixelforgePricing(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')), true);
}

function pixelforgeGeneration(Generation $generation, string $status, array $overrides = []): array
{
    $started = in_array($status, ['processing', 'succeeded', 'failed'], true);
    $finished = in_array($status, ['succeeded', 'failed'], true);

    return array_replace([
        'id' => $generation->provider_id ?? 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => $status,
        'prompt' => $generation->prompt,
        'size' => $generation->size->value,
        'quality' => $generation->quality->value,
        'reference_images' => $generation->reference_images,
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => $status === 'succeeded' ? 'https://pixelforge.promptedit.dev/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png?expires=1762351409&signature=abc' : null,
        'image_url_expires_at' => $status === 'succeeded' ? '2026-11-05T14:03:29Z' : null,
        'usage' => $status === 'succeeded' ? [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ] : null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => "The model couldn't produce an image for the prompt."] : null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => $started ? '2026-11-04T14:03:13Z' : null,
        'completed_at' => $finished ? '2026-11-04T14:03:29Z' : null,
    ], $overrides);
}
