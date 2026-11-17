<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
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

            Generation::whereKey($generation->id)
                ->whereIn('status', $remote->status->reachableFrom())
                ->update($this->changesFor($remote));
        });

        $generation->refresh();
    }

    public function fail(Generation $generation, string $code, ?string $message = null): void
    {
        Generation::whereKey($generation->id)
            ->where('status', GenerationStatus::Pending)
            ->update([
                'status' => GenerationStatus::Failed,
                'error_code' => $code,
                'error_message' => $message,
                'completed_at' => now(),
            ]);

        $generation->refresh();
    }

    /** @return array<string, mixed> */
    private function changesFor(RemoteGeneration $remote): array
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
                'price' => $this->markup->apply($cost),
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
