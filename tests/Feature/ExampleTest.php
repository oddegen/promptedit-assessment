<?php

use Illuminate\Support\Facades\Http;

test('returns a successful response', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);

    $response = $this->get(route('home'));

    $response->assertOk();
});
