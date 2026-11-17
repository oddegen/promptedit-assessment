<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type GenerationObject array{id: string, object: string, status: string, metadata?: array<string, string>, rates: array<string, string>, usage?: array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}|null, image_url?: string|null, image_url_expires_at?: string|null, started_at?: string|null, completed_at?: string|null, error?: array{code: string, message: string}|null}
 */
class RemoteGeneration
{
    /**
     * Create a new class instance.
     *
     * @param  array<string, string>  $rates
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public ?string $generationId,
        public array $rates,
        public ?TokenUsage $usage = null,
        public ?string $imageUrl = null,
        public ?CarbonImmutable $imageUrlExpiresAt = null,
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $completedAt = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}

    /** @param GenerationObject $object */
    public static function fromArray(array $object): self
    {
        return new self(
            id: $object['id'],
            status: GenerationStatus::from($object['status']),
            generationId: $object['metadata']['generation_id'] ?? null,
            rates: $object['rates'],
            usage: isset($object['usage']) ? TokenUsage::fromArray($object['usage']) : null,
            imageUrl: $object['image_url'] ?? null,
            imageUrlExpiresAt: self::date($object['image_url_expires_at'] ?? null),
            startedAt: self::date($object['started_at'] ?? null),
            completedAt: self::date($object['completed_at'] ?? null),
            errorCode: $object['error']['code'] ?? null,
            errorMessage: $object['error']['message'] ?? null,
        );
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
