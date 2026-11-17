<?php

namespace App\Services\Pixelforge;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** @phpstan-import-type GenerationObject from RemoteGeneration */
class PixelforgeClient
{
    public function __construct(
        #[Config('services.pixelforge.base_url')]
        private string $baseUrl,
        #[Config('services.pixelforge.key')]
        private ?string $key,
    ) {}

    public function prices(): PriceList
    {
        /** @var array{rates: array<string, string>, tokens: array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $pricing */
        $pricing = Cache::flexible('pixelforge.pricing', [600, 3600], function () {
            try {
                return $this->send($this->request()->get('pricing'));
            } catch (ConnectionException $exception) {
                throw new PixelforgeException($exception->getMessage(), 'unreachable', 503);
            }
        });

        return PriceList::fromArray($pricing);
    }

    public function createGeneration(Generation $generation): RemoteGeneration
    {
        return $this->remoteGeneration($this->request()
            ->withHeaders(['Idempotency-Key' => $generation->id])
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ]));
    }

    public function generation(string $id): RemoteGeneration
    {
        return $this->remoteGeneration($this->request()->get("generations/{$id}"));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->when($this->key, fn (PendingRequest $request, string $key) => $request->withToken($key))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    private function remoteGeneration(Response $response): RemoteGeneration
    {
        /** @var GenerationObject $object */
        $object = $this->send($response);

        return RemoteGeneration::fromArray($object);
    }

    /** @return array<string, mixed> */
    private function send(Response $response): array
    {
        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        /** @var array<string, mixed> */
        return $response->json();
    }
}
