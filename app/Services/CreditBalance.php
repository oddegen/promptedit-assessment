<?php

namespace App\Services;

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
        $inFlight = $user->generations()->inFlight();

        return new self(
            (int) $user->creditTransactions()->sum('amount'),
            (int) (clone $inFlight)->sum('max_price'),
            (clone $inFlight)->count(),
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
