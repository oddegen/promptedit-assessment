<?php

namespace App\Services;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Container\Attributes\Config;

class Markup
{
    public function __construct(
        #[Config('services.pixelforge.markup_percent')]
        public int $percent,
    ) {}

    public function apply(int $cost): int
    {
        return BigInteger::of($cost)
            ->multipliedBy(100 + $this->percent)
            ->dividedBy(100, RoundingMode::HalfUp)
            ->toInt();
    }
}
