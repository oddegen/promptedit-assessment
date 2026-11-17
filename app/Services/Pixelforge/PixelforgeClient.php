<?php

namespace App\Services\Pixelforge;

use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
        $pricing = Cache::flexible('pixelforge.pricing', [600, 3600], fn () => $this->send($this->request()->get('pricing')));

        return PriceList::fromArray($pricing);
    }

    /** @return array<string, mixed> */
    public function createGeneration(Generation $generation): array
    {
        return $this->send($this->request()
            ->withHeaders(['Idempotency-Key' => $generation->id])
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ]));
    }

    /** @return array<string, mixed> */
    public function generation(string $id): array
    {
        return $this->send($this->request()->get("generations/{$id}"));
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->when($this->key, fn (PendingRequest $request, string $key) => $request->withToken($key))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
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
