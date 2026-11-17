<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
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

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Prices are unavailable right now.'], 503);
        }

        return Inertia::flash('toast', ['type' => 'error', 'message' => "Couldn't start that image. Try again in a moment."])->back();
    }
}
