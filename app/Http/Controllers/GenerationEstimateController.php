<?php

namespace App\Http\Controllers;

use App\Enums\Quality;
use App\Http\Requests\Generations\EstimateGenerationRequest;
use App\Services\Markup;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\JsonResponse;

class GenerationEstimateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(EstimateGenerationRequest $request, PixelforgeClient $pixelforge, Markup $markup): JsonResponse
    {
        $prices = $pixelforge->prices();
        $references = count($request->referenceImages());
        $estimate = $prices->estimate($request->prompt(), $request->size(), $request->quality(), $references);

        return response()->json([
            'minimum' => $markup->apply($estimate->minimumCost()),
            'expected' => $markup->apply($estimate->expectedCost()),
            'maximum' => $markup->apply($estimate->maximumCost()),
            'tiers' => collect(Quality::cases())->mapWithKeys(fn (Quality $quality) => [
                $quality->value => $markup->apply(
                    $prices->estimate($request->prompt(), $request->size(), $quality, $references)->maximumCost(),
                ),
            ]),
            'tokens' => [
                'minimum' => $estimate->minimum->toArray(),
                'expected' => $estimate->expected->toArray(),
                'maximum' => $estimate->maximum->toArray(),
            ],
            'fee_percent' => $markup->percent,
        ]);
    }
}
