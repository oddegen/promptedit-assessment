<?php

namespace App\Console\Commands;

use App\Actions\SyncGeneration;
use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('generations:poll')]
#[Description('Check on generations whose webhooks are overdue')]
class PollGenerationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PixelforgeClient $pixelforge, SyncGeneration $sync): void
    {
        Generation::atProvider()
            ->where('updated_at', '<', now()->subMinutes(2))
            ->oldest('updated_at')
            ->limit(10)
            ->get()
            ->each(function (Generation $generation) use ($pixelforge, $sync) {
                try {
                    $sync->handle($generation, $pixelforge->generation((string) $generation->provider_id));
                } catch (PixelforgeException $exception) {
                    report($exception);
                }
            });
    }
}
