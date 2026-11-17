<?php

namespace App\Actions;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;

class PurchaseCredits
{
    public function handle(User $user, int $dollars): CreditTransaction
    {
        return $user->creditTransactions()->create([
            'type' => CreditTransactionType::Purchase,
            'amount' => $dollars * 1_000_000_000,
        ]);
    }
}
