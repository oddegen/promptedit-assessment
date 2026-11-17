<?php

namespace App\Services\Pixelforge;

class CostEstimate
{
    /** @param  array<string, string>  $rates */
    public function __construct(
        public TokenUsage $minimum,
        public TokenUsage $expected,
        public TokenUsage $maximum,
        public array $rates,
    ) {}

    public function minimumCost(): int
    {
        return $this->minimum->cost($this->rates);
    }

    public function expectedCost(): int
    {
        return $this->expected->cost($this->rates);
    }

    public function maximumCost(): int
    {
        return $this->maximum->cost($this->rates);
    }
}
