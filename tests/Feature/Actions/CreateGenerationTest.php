<?php

use App\Actions\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('saves a pending generation priced from current rates and queues its submission', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->create();

    $generation = app(CreateGeneration::class)->handle($user, 'A lighthouse on a cliff at dawn', Size::Square, Quality::Medium);

    expect($generation->refresh())
        ->user_id->toBe($user->id)
        ->status->toBe(GenerationStatus::Pending)
        ->min_price->toBe(58_175_000)
        ->estimated_price->toBe(72_237_500)
        ->max_price->toBe(86_312_500);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

it('files every new version under the original generation', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $original = Generation::factory()->failed()->create();
    $create = app(CreateGeneration::class);

    $second = $create->handle($original->user, $original->prompt, Size::Square, Quality::Medium, versionOf: $original);
    $third = $create->handle($original->user, $original->prompt, Size::Square, Quality::Medium, versionOf: $second);

    expect($second->parent_id)->toBe($original->id)
        ->and($third->parent_id)->toBe($original->id);
});
