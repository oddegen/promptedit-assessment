<?php

namespace App\Http\Controllers;

use App\Actions\PurchaseCredits;
use App\Enums\CreditTransactionType;
use App\Enums\Quality;
use App\Enums\Size;
use App\Exceptions\PixelforgeException;
use App\Http\Requests\Credits\PurchaseCreditsRequest;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditBalance;
use App\Services\Markup;
use App\Services\Money;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(#[CurrentUser] User $user, PixelforgeClient $pixelforge, Markup $markup): Response
    {
        return Inertia::render('credits/Index', [
            'credits' => CreditBalance::of($user)->toArray(),
            'packs' => $this->packs($pixelforge, $markup),
            'running' => $user->generations()->inFlight()->latest()->get(['id', 'prompt', 'max_price', 'created_at']),
            'transactions' => Inertia::scroll(fn () => $user->creditTransactions()
                ->select('*')
                ->selectRaw('sum(amount) over (order by created_at, id) as balance_after')
                ->with('generation:id,prompt')
                ->latest()
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (CreditTransaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'description' => match ($transaction->type) {
                        CreditTransactionType::Bonus => 'Welcome credit',
                        CreditTransactionType::Purchase => 'Added credits',
                        CreditTransactionType::Charge => $transaction->generation->prompt ?? 'Image',
                    },
                    'generation_id' => $transaction->generation_id,
                    'amount' => $transaction->amount,
                    'balance_after' => (int) $transaction->getAttribute('balance_after'),
                    'created_at' => $transaction->created_at,
                ])),
        ]);
    }

    public function store(PurchaseCreditsRequest $request, #[CurrentUser] User $user, PurchaseCredits $purchase): RedirectResponse
    {
        $transaction = $purchase->handle($user, $request->dollars());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('Added %s to your balance.', Money::format($transaction->amount)),
        ])->back();
    }

    /** @return list<array{dollars: int, amount: int, images: int|null}> */
    private function packs(PixelforgeClient $pixelforge, Markup $markup): array
    {
        try {
            $typical = $markup->apply($pixelforge->prices()->estimate('', Size::Square, Quality::High, 0)->expectedCost());
        } catch (PixelforgeException) {
            $typical = null;
        }

        return array_map(fn (int $dollars) => [
            'dollars' => $dollars,
            'amount' => $dollars * 1_000_000_000,
            'images' => $typical ? intdiv($dollars * 1_000_000_000, $typical) : null,
        ], array_values(array_map('intval', Config::array('credits.packs'))));
    }
}
