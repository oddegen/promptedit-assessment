<?php

namespace App\Actions;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Services\Markup;
use App\Services\Pixelforge\RemoteGeneration;
use Illuminate\Support\Facades\DB;

class SyncGeneration
{
    /**
     * Create a new class instance.
     */
    public function __construct(private Markup $markup) {}

    public function handle(Generation $generation, RemoteGeneration $remote): void
    {
        DB::transaction(function () use ($generation, $remote) {
            Generation::whereKey($generation->id)
                ->whereNull('provider_id')
                ->update(['provider_id' => $remote->id, 'rates' => json_encode($remote->rates)]);

            $changes = $this->changesFor($generation, $remote);

            $moved = Generation::whereKey($generation->id)
                ->whereIn('status', $remote->status->reachableFrom())
                ->update($changes);

            if ($moved === 1 && $remote->status->isFinished()) {
                $this->release($generation);
            }

            if ($moved === 0 && $remote->status === GenerationStatus::Succeeded && $remote->usage !== null) {
                Generation::whereKey($generation->id)
                    ->where('status', GenerationStatus::Failed)
                    ->whereNull('cost')
                    ->update([
                        ...$remote->usage->toArray(),
                        'cost' => $remote->usage->cost($remote->rates),
                        'completed_at' => $remote->completedAt,
                    ]);
            }

            if ($moved === 1 && isset($changes['price'])) {
                CreditTransaction::query()->insertOrIgnore([
                    'user_id' => $generation->user_id,
                    'generation_id' => $generation->id,
                    'type' => CreditTransactionType::Charge->value,
                    'amount' => -$changes['price'],
                    'created_at' => now(),
                ]);
            }
        });

        $generation->refresh();
    }

    public function fail(Generation $generation, string $code, ?string $message = null): void
    {
        DB::transaction(function () use ($generation, $code, $message) {
            $moved = Generation::whereKey($generation->id)
                ->where('status', GenerationStatus::Pending)
                ->update([
                    'status' => GenerationStatus::Failed,
                    'error_code' => $code,
                    'error_message' => $message,
                    'completed_at' => now(),
                ]);

            if ($moved === 1) {
                $this->release($generation);
            }
        });

        $generation->refresh();
    }

    private function release(Generation $generation): void
    {
        CreditTransaction::query()->insertOrIgnoreUsing(
            ['user_id', 'generation_id', 'type', 'amount', 'created_at'],
            CreditTransaction::query()
                ->where('generation_id', $generation->id)
                ->where('type', CreditTransactionType::Hold)
                ->selectRaw('user_id, generation_id, ?, -amount, ?', [CreditTransactionType::Release->value, now()]),
        );
    }

    /** @return array<string, mixed> */
    private function changesFor(Generation $generation, RemoteGeneration $remote): array
    {
        $changes = [
            'status' => $remote->status,
            'started_at' => $remote->startedAt,
            'completed_at' => $remote->completedAt,
        ];

        if ($remote->status === GenerationStatus::Succeeded && $remote->usage !== null) {
            $cost = $remote->usage->cost($remote->rates);

            $changes += [
                ...$remote->usage->toArray(),
                'cost' => $cost,
                'price' => min($this->markup->apply($cost), $generation->max_price),
                'image_url' => $remote->imageUrl,
                'image_url_expires_at' => $remote->imageUrlExpiresAt,
            ];
        }

        if ($remote->status === GenerationStatus::Failed) {
            $changes += [
                'error_code' => $remote->errorCode,
                'error_message' => $remote->errorMessage,
            ];
        }

        return $changes;
    }
}
