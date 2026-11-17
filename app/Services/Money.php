<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Money
{
    public static function format(int $nanodollars, RoundingMode $rounding = RoundingMode::HalfUp): string
    {
        $amount = abs($nanodollars);
        $scale = $amount === 0 ? 2 : max(2, min(9, 11 - strlen((string) $amount)));
        $dollars = BigDecimal::ofUnscaledValue($amount, 9)->toScale($scale, $rounding);

        return ($nanodollars < 0 ? '-' : '').'$'.$dollars;
    }
}
