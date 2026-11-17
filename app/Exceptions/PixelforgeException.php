<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PixelforgeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            (string) ($response->json('error.message') ?? $response->reason()),
            (string) ($response->json('error.code') ?? 'unknown'),
            $response->status(),
            $retryAfter === '' ? null : (int) $retryAfter,
        );
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'], true);
    }

    public function isRejection(): bool
    {
        return in_array($this->errorCode, ['content_policy_violation', 'validation_failed', 'daily_limit_exceeded'], true);
    }
}
