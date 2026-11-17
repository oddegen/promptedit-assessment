<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\User;

class CreditBalance
{
    public function __construct(
        public int $balance,
        public int $held,
        public int $running,
    ) {}

    public static function of(User $user): self
    {
        $holds = [CreditTransactionType::Hold->value, CreditTransactionType::Release->value];

        $totals = $user->creditTransactions()
            ->toBase()
            ->selectRaw('coalesce(sum(case when type in (?, ?) then 0 else amount end), 0) as balance', $holds)
            ->selectRaw('coalesce(sum(case when type in (?, ?) then amount else 0 end), 0) as held', $holds)
            ->first();

        return new self(
            (int) $totals?->balance,
            -(int) $totals?->held,
            $user->generations()->inFlight()->count(),
        );
    }

    public function available(): int
    {
        return $this->balance - $this->held;
    }

    /** @return array{balance: int, held: int, available: int, running: int} */
    public function toArray(): array
    {
        return [
            'balance' => $this->balance,
            'held' => $this->held,
            'available' => $this->available(),
            'running' => $this->running,
        ];
    }
}
