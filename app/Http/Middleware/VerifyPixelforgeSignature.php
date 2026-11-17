<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    public const TOLERANCE_SECONDS = 300;

    public function __construct(
        #[Config('services.pixelforge.webhook_secret')]
        private ?string $secret,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->hasValidSignature($request), 400, 'Invalid Pixelforge signature.');

        return $next($request);
    }

    private function hasValidSignature(Request $request): bool
    {
        if ($this->secret === null || $this->secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $request->header('Pixelforge-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(now()->getTimestamp() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $this->secret);

        return collect($signatures)->contains(fn (string $signature) => hash_equals($expected, $signature));
    }
}
