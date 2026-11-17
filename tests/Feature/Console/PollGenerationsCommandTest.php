<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;

it('checks on generations whose webhooks are overdue', function () {
    $generation = Generation::factory()->processing()->create(['updated_at' => now()->subMinutes(3)]);
    Http::fake([
        "pixelforge.promptedit.dev/v1/generations/{$generation->provider_id}" => Http::response(pixelforgeGeneration($generation, 'succeeded')),
    ]);

    $this->artisan('generations:poll')->assertSuccessful();

    expect($generation->refresh())->status->toBe(GenerationStatus::Succeeded)->price->toBe(381_887_500);
});

it('leaves recently updated generations to their webhooks', function () {
    Generation::factory()->processing()->create();

    $this->artisan('generations:poll')->assertSuccessful();

    Http::assertNothingSent();
});
