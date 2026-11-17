<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Money
{
    public static function format(int $nanodollars, bool $roundUp = false): string
    {
        $amount = abs($nanodollars);
        $scale = $amount === 0 ? 2 : max(2, min(9, 11 - strlen((string) $amount)));
        $dollars = BigDecimal::ofUnscaledValue($amount, 9)->toScale($scale, $roundUp ? RoundingMode::Ceiling : RoundingMode::HalfUp);

        return ($nanodollars < 0 ? '-' : '').'$'.$dollars;
    }
}
