<?php

namespace App\Http\Controllers;

use App\Enums\Quality;
use App\Enums\Size;
use App\Exceptions\PixelforgeException;
use App\Services\Markup;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const SAMPLE_PROMPT = 'A lighthouse on a cliff at dawn, oil painting';

    /**
     * Handle the incoming request.
     */
    public function __invoke(PixelforgeClient $pixelforge, Markup $markup): Response
    {
        return Inertia::render('Welcome', [
            'prices' => $this->prices($pixelforge, $markup),
            'bonus' => Config::integer('credits.welcome_bonus'),
        ]);
    }

    /** @return array<string, array<string, array{min: int, max: int}>>|null */
    private function prices(PixelforgeClient $pixelforge, Markup $markup): ?array
    {
        try {
            $prices = $pixelforge->prices();
        } catch (PixelforgeException) {
            return null;
        }

        $table = [];

        foreach (Quality::cases() as $quality) {
            foreach (Size::cases() as $size) {
                $estimate = $prices->estimate(self::SAMPLE_PROMPT, $size, $quality, 0);

                $table[$quality->value][$size->value] = [
                    'min' => $markup->apply($estimate->minimumCost()),
                    'max' => $markup->apply($estimate->maximumCost()),
                ];
            }
        }

        return $table;
    }
}
