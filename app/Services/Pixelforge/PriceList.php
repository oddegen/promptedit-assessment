<?php

namespace App\Services\Pixelforge;

use App\Enums\Quality;
use App\Enums\Size;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** @phpstan-type TokenTable array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>} */
class PriceList
{
    /**
     * @param  array<string, string>  $rates
     * @param  TokenTable  $tokens
     */
    public function __construct(
        public array $rates,
        public array $tokens,
    ) {}

    /** @param  array{rates: array<string, string>, tokens: TokenTable}  $pricing */
    public static function fromArray(array $pricing): self
    {
        return new self($pricing['rates'], $pricing['tokens']);
    }

    public function estimate(string $prompt, Size $size, Quality $quality, int $referenceImages): CostEstimate
    {
        $charactersPerToken = $this->tokens['characters_per_text_token'];
        $textTokens = intdiv(mb_strlen($prompt) + $charactersPerToken - 1, $charactersPerToken);
        $minimumTextTokens = BigDecimal::of($textTokens)
            ->multipliedBy(BigDecimal::one()->minus((string) $this->tokens['text_token_variance']))
            ->toScale(0, RoundingMode::Ceiling)
            ->toInt();
        $maximumTextTokens = BigDecimal::of($textTokens)
            ->multipliedBy(BigDecimal::one()->plus((string) $this->tokens['text_token_variance']))
            ->toScale(0, RoundingMode::Ceiling)
            ->toInt();
        $imageTokens = $referenceImages * $this->tokens['per_reference_image'];
        $outputTokens = $this->tokens['output'][$quality->value][$size->value];
        ['min' => $minimumReasoning, 'max' => $maximumReasoning] = $this->tokens['reasoning'][$quality->value];

        return new CostEstimate(
            minimum: new TokenUsage($minimumTextTokens, $imageTokens, $outputTokens, $minimumReasoning),
            expected: new TokenUsage($textTokens, $imageTokens, $outputTokens, intdiv($minimumReasoning + $maximumReasoning, 2)),
            maximum: new TokenUsage($maximumTextTokens, $imageTokens, $outputTokens, $maximumReasoning),
            rates: $this->rates,
        );
    }
}
