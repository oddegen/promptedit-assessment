<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('quotes the lowest, expected and highest price, and the highest price of every quality', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);

    $response->assertOk()->assertJson([
        'minimum' => 58_175_000,
        'expected' => 72_237_500,
        'maximum' => 86_312_500,
        'tiers' => ['low' => 21_875_000, 'medium' => 86_312_500, 'high' => 340_062_500],
        'fee_percent' => 25,
    ]);
});

it('answers 503 when current prices cannot be fetched', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::failedConnection()]);

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'size' => '1024x1024',
        'quality' => 'low',
    ]);

    $response->assertServiceUnavailable()->assertJson(['message' => 'Prices are unavailable right now.']);
});

it('answers 422 for an unknown size', function () {
    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.estimate'), [
        'size' => '512x512',
        'quality' => 'low',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['size']);
});
