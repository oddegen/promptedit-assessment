<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditBalance;
use App\Services\Markup;
use App\Services\Money;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateGeneration
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private PixelforgeClient $pixelforge,
        private Markup $markup,
    ) {}

    /** @param list<string> $referenceImages */
    public function handle(User $user, string $prompt, Size $size, Quality $quality, array $referenceImages = [], ?Generation $versionOf = null): Generation
    {
        $estimate = $this->pixelforge->prices()->estimate($prompt, $size, $quality, count($referenceImages));
        $maxPrice = $this->markup->apply($estimate->maximumCost());

        return DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $versionOf, $estimate, $maxPrice) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $this->ensureAffordable(CreditBalance::of($user), $maxPrice);

            $generation = $user->generations()->create([
                'parent_id' => $versionOf->parent_id ?? $versionOf?->id,
                'status' => GenerationStatus::Pending,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'min_price' => $this->markup->apply($estimate->minimumCost()),
                'estimated_price' => $this->markup->apply($estimate->expectedCost()),
                'max_price' => $maxPrice,
            ]);

            SubmitGeneration::dispatch($generation)->afterCommit();

            return $generation;
        });
    }

    private function ensureAffordable(CreditBalance $credits, int $maxPrice): void
    {
        if ($maxPrice <= $credits->available()) {
            return;
        }

        $message = $credits->held > 0 && $maxPrice <= $credits->balance
            ? sprintf(
                '%s is set aside for %d %s in progress. This one needs up to %s and you have %s free. It frees up as they finish.',
                Money::format($credits->held),
                $credits->running,
                Str::plural('image', $credits->running),
                Money::format($maxPrice, roundUp: true),
                Money::format(max(0, $credits->available())),
            )
            : sprintf(
                'This needs up to %s, but your balance is %s. Add credits to keep going.',
                Money::format($maxPrice, roundUp: true),
                Money::format($credits->balance),
            );

        throw ValidationException::withMessages(['credits' => $message]);
    }
}
