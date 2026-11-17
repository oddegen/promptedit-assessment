<?php

namespace App\Services\Pixelforge;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class TokenUsage
{
    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {}

    /** @param  array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}  $usage */
    public static function fromArray(array $usage): self
    {
        return new self(
            $usage['input_text_tokens'],
            $usage['input_image_tokens'],
            $usage['output_tokens'],
            $usage['reasoning_tokens'],
        );
    }

    /** @param  array<string, string>  $rates */
    public function cost(array $rates): int
    {
        $total = BigDecimal::zero();

        foreach ($this->toArray() as $type => $tokens) {
            $total = $total->plus(BigDecimal::of($rates[$type])->multipliedBy($tokens));
        }

        return $total->withPointMovedRight(3)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /** @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int} */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputText,
            'input_image_tokens' => $this->inputImage,
            'output_tokens' => $this->output,
            'reasoning_tokens' => $this->reasoning,
        ];
    }
}
