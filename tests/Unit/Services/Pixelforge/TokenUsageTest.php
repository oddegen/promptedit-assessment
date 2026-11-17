<?php

use App\Services\Pixelforge\TokenUsage;

$rates = [
    'input_text_tokens' => '5.00',
    'input_image_tokens' => '10.00',
    'output_tokens' => '40.00',
    'reasoning_tokens' => '12.50',
];

it('prices the documented succeeded generation at $0.305510', function () use ($rates) {
    $usage = new TokenUsage(inputText: 12, inputImage: 765, output: 6450, reasoning: 3184);

    expect($usage->cost($rates))->toBe(305_510_000);
});

it('matches the cost Pixelforge reports for the documented usage day', function () use ($rates) {
    $usage = new TokenUsage(inputText: 41, inputImage: 0, output: 3300, reasoning: 2950);

    expect($usage->cost($rates))->toBe(169_080_000);
});

it('rounds a fraction of a nanodollar half up', function (string $rate, int $tokens, int $nanodollars) {
    $rates = ['input_text_tokens' => '0', 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => $rate];

    expect((new TokenUsage(0, 0, 0, $tokens))->cost($rates))->toBe($nanodollars);
})->with([
    'less than half a nanodollar' => ['0.0004', 1, 0],
    'exactly half a nanodollar' => ['0.0005', 1, 1],
    'a rate with four decimals' => ['12.3456', 3, 37_037],
]);

it('reads the token counts from a Pixelforge usage object', function () {
    $usage = TokenUsage::fromArray([
        'input_text_tokens' => 12,
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
        'total_tokens' => 10411,
    ]);

    expect($usage->toArray())->toBe([
        'input_text_tokens' => 12,
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
    ]);
});
