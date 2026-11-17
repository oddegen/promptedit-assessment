<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('shows what is available, what running images hold and the ledger with a running balance', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits(5_000_000_000)->create();
    Generation::factory()->for($user)->create();
    $charged = Generation::factory()->for($user)->succeeded()->create();
    $user->creditTransactions()->create(['generation_id' => $charged->id, 'type' => CreditTransactionType::Charge, 'amount' => -381_887_500]);

    $response = $this->actingAs($user)->get(route('credits.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('credits/Index')
        ->where('credits.balance', 4_618_112_500)
        ->where('credits.held', 87_500_000)
        ->where('credits.available', 4_530_612_500)
        ->has('running', 1)
        ->has('packs', 3)
        ->where('packs.0.dollars', 5)
        ->where('transactions.data.0.amount', -381_887_500)
        ->where('transactions.data.0.description', $charged->prompt)
        ->where('transactions.data.0.balance_after', 4_618_112_500)
        ->where('transactions.data.1.balance_after', 5_000_000_000));
});

it('adds a pack of credits to the balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('credits.index'))->post(route('credits.store'), ['pack' => 10]);

    $response->assertRedirect(route('credits.index'))->assertInertiaFlash('toast.message', 'Added $10.00 to your balance.');
    expect($user->creditTransactions()->sole())->type->toBe(CreditTransactionType::Purchase)->amount->toBe(10_000_000_000);
});

it('only sells the packs on offer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('credits.index'))->post(route('credits.store'), ['pack' => 1000]);

    $response->assertSessionHasErrors('pack');
    expect($user->creditTransactions()->count())->toBe(0);
});

it('keeps the ledger append-only', function () {
    $transaction = CreditTransaction::factory()->create();

    expect(fn () => $transaction->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $transaction->delete())->toThrow(LogicException::class);
});
