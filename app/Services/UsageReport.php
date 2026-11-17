<?php

namespace App\Services;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsageReport
{
    /** @var Collection<string, array{date: string, generations: int, cost: int, charged: int}>|null */
    private ?Collection $days = null;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /** @return Collection<string, array{date: string, generations: int, cost: int, charged: int}> */
    public function days(): Collection
    {
        if ($this->days !== null) {
            return $this->days;
        }

        $totals = $this->succeeded()
            ->selectRaw('date(completed_at) as day, count(*) as generations, sum(cost) as cost, sum(price) as charged')
            ->groupByRaw('date(completed_at)')
            ->toBase()
            ->get()
            ->keyBy('day');

        /** @var Collection<string, array{date: string, generations: int, cost: int, charged: int}> $days */
        $days = new Collection;

        for ($date = $this->to; $date->greaterThanOrEqualTo($this->from); $date = $date->subDay()) {
            $day = $date->toDateString();
            $row = $totals->get($day);

            $days->put($day, [
                'date' => $day,
                'generations' => (int) ($row->generations ?? 0),
                'cost' => (int) ($row->cost ?? 0),
                'charged' => (int) ($row->charged ?? 0),
            ]);
        }

        return $this->days = $days;
    }

    /** @return list<array{id: int, name: string, email: string, generations: int, cost: int, charged: int}> */
    public function users(): array
    {
        $rows = $this->succeeded()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->selectRaw('users.id, users.name, users.email, count(*) as generations, sum(generations.cost) as cost, sum(generations.price) as charged')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('charged')
            ->limit(20)
            ->toBase()
            ->get();

        $users = [];

        foreach ($rows as $row) {
            $users[] = [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'generations' => (int) $row->generations,
                'cost' => (int) $row->cost,
                'charged' => (int) $row->charged,
            ];
        }

        return $users;
    }

    /** @return array{generations: int, cost: int, charged: int, profit: int, margin: string|null} */
    public function summary(): array
    {
        $days = $this->days();
        $charged = $days->sum('charged');
        $cost = $days->sum('cost');
        $profit = $charged - $cost;

        return [
            'generations' => $days->sum('generations'),
            'cost' => $cost,
            'charged' => $charged,
            'profit' => $profit,
            'margin' => $charged === 0 ? null : (string) BigDecimal::of($profit)->multipliedBy(100)->dividedBy($charged, 1, RoundingMode::HalfUp),
        ];
    }

    /** @return array{available: bool, days: array<string, array{cost: int, generations: int, difference: int, status: string}>} */
    public function reconcile(PixelforgeClient $pixelforge): array
    {
        try {
            $usage = $pixelforge->usage($this->from, $this->to);
        } catch (PixelforgeException) {
            return ['available' => false, 'days' => []];
        }

        $today = CarbonImmutable::today()->toDateString();

        return [
            'available' => true,
            'days' => $this->days()->map(function (array $day) use ($usage, $today) {
                $provider = $usage[$day['date']] ?? ['generations' => 0, 'cost' => 0];
                $difference = $day['cost'] - $provider['cost'];

                return [
                    'cost' => $provider['cost'],
                    'generations' => $provider['generations'],
                    'difference' => $difference,
                    'status' => match (true) {
                        $day['date'] === $today => 'in_progress',
                        $difference === 0 => 'matched',
                        default => 'differs',
                    },
                ];
            })->all(),
        ];
    }

    /** @return Builder<Generation> */
    private function succeeded(): Builder
    {
        return Generation::query()
            ->whereNotNull('generations.cost')
            ->whereBetween('generations.completed_at', [$this->from->startOfDay(), $this->to->endOfDay()]);
    }
}
