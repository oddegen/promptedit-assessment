<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Markup;
use App\Services\Pixelforge\PixelforgeClient;

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
    public function handle(User $user, string $prompt, Size $size, Quality $quality, array $referenceImages = []): Generation
    {
        $estimate = $this->pixelforge->prices()->estimate($prompt, $size, $quality, count($referenceImages));

        $generation = $user->generations()->create([
            'status' => GenerationStatus::Pending,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'estimated_price' => $this->markup->apply($estimate->expectedCost()),
            'max_price' => $this->markup->apply($estimate->maximumCost()),
        ]);

        SubmitGeneration::dispatch($generation)->afterCommit();

        return $generation;
    }
}
