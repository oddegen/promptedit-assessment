<?php

use App\Actions\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Jobs\SubmitGeneration;
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
        ->estimated_price->toBe(72_237_500)
        ->max_price->toBe(86_312_500);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});
