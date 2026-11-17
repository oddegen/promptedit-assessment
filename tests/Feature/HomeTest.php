<?php

use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('the landing page prices a short prompt for every quality and shape', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->where('prices.low.1024x1024.min', 14_075_000)
        ->has('prices.high.1536x1024.max')
        ->where('bonus', 1_000_000_000));
});

test('the landing page still opens when prices cannot be fetched', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::failedConnection()]);

    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('prices', null));
});
