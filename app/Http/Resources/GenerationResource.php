<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            'reference_images' => $this->reference_images,
            'min_price' => $this->min_price,
            'estimated_price' => $this->estimated_price,
            'max_price' => $this->max_price,
            'price' => $this->price,
            'error_code' => $this->error_code,
            'image_url' => $this->image_url,
            'image_url_expires_at' => $this->image_url_expires_at,
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'version_count' => $this->whenHas('version_count'),
            'version_number' => $this->whenHas('version_number'),
        ];
    }
}
