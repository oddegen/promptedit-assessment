<?php

use App\Enums\Quality;
use App\Enums\Size;
use App\Services\Pixelforge\PriceList;

$prices = PriceList::fromArray(json_decode(file_get_contents(__DIR__.'/../../../Fixtures/pixelforge/pricing.json'), true));

it('puts the documented worst case at $0.373875', function () use ($prices) {
    $estimate = $prices->estimate(str_repeat('a', 400), Size::Landscape, Quality::High, 2);

    expect($estimate->maximum->toArray())->toBe([
        'input_text_tokens' => 115,
        'input_image_tokens' => 1530,
        'output_tokens' => 6450,
        'reasoning_tokens' => 8000,
    ])->and($estimate->maximumCost())->toBe(373_875_000);
});

it('expects a quarter of the prompt length in tokens and the middle of the reasoning range', function () use ($prices) {
    $estimate = $prices->estimate('a small red boat', Size::Square, Quality::Medium, 0);

    expect($estimate->expected->toArray())->toBe([
        'input_text_tokens' => 4,
        'input_image_tokens' => 0,
        'output_tokens' => 1100,
        'reasoning_tokens' => 1100,
    ])->and($estimate->expectedCost())->toBe(57_770_000);
});

it('counts the prompt in characters rather than bytes', function () use ($prices) {
    $estimate = $prices->estimate('éééééééé', Size::Square, Quality::Low, 0);

    expect($estimate->expected->inputText)->toBe(2);
});

it('rounds partial text tokens up for the upper bound', function () use ($prices) {
    $estimate = $prices->estimate('abcde', Size::Portrait, Quality::Low, 0);

    expect($estimate->expected->inputText)->toBe(2)
        ->and($estimate->maximum->inputText)->toBe(3);
});
