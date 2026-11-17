<?php

namespace App\Http\Controllers;

use App\Actions\SyncGeneration;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\RemoteGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** @phpstan-import-type GenerationObject from RemoteGeneration */
class PixelforgeWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SyncGeneration $sync): Response
    {
        DB::transaction(function () use ($request, $sync) {
            $remote = $this->remoteGeneration($request);
            $generation = $remote === null ? null : $this->findGeneration($remote);

            $recorded = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $request->string('id')->toString(),
                'type' => $request->string('type')->toString(),
                'generation_id' => $generation?->id,
                'attempt' => (int) $request->header('Pixelforge-Delivery-Attempt', '1'),
                'payload' => $request->getContent(),
                'created_at' => now(),
            ]);

            if ($recorded === 1 && $remote !== null && $generation !== null) {
                $sync->handle($generation, $remote);
            }
        });

        return response()->noContent();
    }

    private function remoteGeneration(Request $request): ?RemoteGeneration
    {
        if ($request->input('data.object.object') !== 'generation') {
            return null;
        }

        /** @var GenerationObject $object */
        $object = $request->input('data.object');

        return RemoteGeneration::fromArray($object);
    }

    private function findGeneration(RemoteGeneration $remote): ?Generation
    {
        return Generation::find($remote->generationId) ?? Generation::firstWhere('provider_id', $remote->id);
    }
}
