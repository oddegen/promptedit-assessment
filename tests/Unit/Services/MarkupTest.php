<?php

use App\Services\Markup;

it('charges the provider cost plus 25 percent, rounded half up to the nanodollar', function (int $cost, int $charge) {
    expect((new Markup(25))->apply($cost))->toBe($charge);
})->with([
    'the documented generation' => [305_510_000, 381_887_500],
    'a quarter nanodollar over' => [1, 1],
    'exactly half a nanodollar over' => [2, 3],
    'three quarters over' => [3, 4],
    'nothing to charge' => [0, 0],
]);
