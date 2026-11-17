<?php

namespace App\Jobs;

use App\Actions\SyncGeneration;
use App\Enums\GenerationStatus;
use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\Release;
use Throwable;

#[MaxExceptions(3)]
#[Backoff([5, 15, 30])]
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public const PROVIDER_SLOTS = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public Generation $generation) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(20);
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            Release::when(fn () => $this->providerIsFull(), 5),
            new RateLimited('pixelforge'),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(PixelforgeClient $pixelforge, SyncGeneration $sync): void
    {
        if ($this->generation->provider_id !== null || $this->generation->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $sync->handle($this->generation, $pixelforge->createGeneration($this->generation));
        } catch (PixelforgeException $exception) {
            if ($exception->isRetryable()) {
                $this->release($this->retryDelay($exception));
            } elseif ($exception->isRejection()) {
                $sync->fail($this->generation, $exception->errorCode, $exception->getMessage());
            } else {
                $this->fail($exception);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(SyncGeneration::class)->fail($this->generation, 'submission_failed', $exception?->getMessage());
    }

    private function providerIsFull(): bool
    {
        return Generation::atProvider()
            ->where('updated_at', '>', now()->subMinutes(15))
            ->count() >= self::PROVIDER_SLOTS;
    }

    private function retryDelay(PixelforgeException $exception): int
    {
        $backoff = min(60, 5 * 2 ** ($this->attempts() - 1));

        return max($exception->retryAfter ?? 0, $backoff) + random_int(0, 3);
    }
}
