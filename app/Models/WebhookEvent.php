<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property string|null $generation_id
 * @property int $attempt
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['event_id', 'type', 'generation_id', 'attempt', 'payload'])]
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'payload' => 'array',
        ];
    }
}
