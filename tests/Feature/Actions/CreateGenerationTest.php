<?php

use App\Actions\CreateGeneration;
use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('saves a pending generation priced from current rates and queues its submission', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->withCredits()->create();

    $generation = app(CreateGeneration::class)->handle($user, 'A lighthouse on a cliff at dawn', Size::Square, Quality::Medium);

    expect($generation->refresh())
        ->user_id->toBe($user->id)
        ->status->toBe(GenerationStatus::Pending)
        ->min_price->toBe(58_168_750)
        ->estimated_price->toBe(72_237_500)
        ->max_price->toBe(86_312_500);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

it('sets the most the image can cost aside in the ledger', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->withCredits()->create();

    $generation = app(CreateGeneration::class)->handle($user, 'A lighthouse on a cliff at dawn', Size::Square, Quality::Medium);

    expect($user->creditTransactions()->where('type', CreditTransactionType::Hold)->sole())
        ->amount->toBe(-86_312_500)
        ->generation_id->toBe($generation->id);
});

it('files every new version under the original generation', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $original = Generation::factory()->for(User::factory()->withCredits())->failed()->create();
    $create = app(CreateGeneration::class);

    $second = $create->handle($original->user, $original->prompt, Size::Square, Quality::Medium, versionOf: $original);
    $third = $create->handle($original->user, $original->prompt, Size::Square, Quality::Medium, versionOf: $second);

    expect($second->parent_id)->toBe($original->id)
        ->and($third->parent_id)->toBe($original->id);
});

it('refuses an image the balance cannot cover', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->withCredits(10_000_000)->create();

    expect(fn () => app(CreateGeneration::class)->handle($user, 'A lighthouse on a cliff at dawn', Size::Square, Quality::Medium))
        ->toThrow(ValidationException::class, 'This needs up to $0.087, but your balance is $0.010. Add credits to keep going.');

    expect($user->generations()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('explains when images in progress are holding the balance', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    Queue::fake([SubmitGeneration::class]);
    $user = User::factory()->withCredits(500_000_000)->create();
    Generation::factory()->for($user)->create();

    expect(fn () => app(CreateGeneration::class)->handle($user, 'A lighthouse on a cliff at dawn', Size::Square, Quality::Medium))
        ->toThrow(ValidationException::class, '$0.46 is set aside for 1 image in progress. This one needs up to $0.087 and you have $0.042 free.');
});
