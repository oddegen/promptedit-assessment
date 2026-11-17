<?php

namespace App\Services\Pixelforge;

class CostEstimate
{
    /** @param  array<string, string>  $rates */
    public function __construct(
        public TokenUsage $expected,
        public TokenUsage $maximum,
        public array $rates,
    ) {}

    public function expectedCost(): int
    {
        return $this->expected->cost($this->rates);
    }

    public function maximumCost(): int
    {
        return $this->maximum->cost($this->rates);
    }
}
