<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $parent_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property Size $size
 * @property Quality $quality
 * @property list<string> $reference_images
 * @property int $min_price
 * @property int $estimated_price
 * @property int $max_price
 * @property array<string, string>|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $cost
 * @property int|null $price
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['parent_id', 'status', 'prompt', 'size', 'quality', 'reference_images', 'min_price', 'estimated_price', 'max_price'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'parent_id');
    }

    /** @return HasMany<Generation, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(Generation::class, 'parent_id');
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function latestVersions(Builder $query): void
    {
        $query->whereNotExists(fn (QueryBuilder $newer) => $newer
            ->from('generations as newer')
            ->whereRaw('newer.parent_id = coalesce(generations.parent_id, generations.id)')
            ->whereColumn('newer.id', '>', 'generations.id'));
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withVersionCount(Builder $query): void
    {
        $query->addSelect(['version_count' => DB::table('generations as versions')
            ->selectRaw('count(*) + 1')
            ->whereColumn('versions.parent_id', 'generations.parent_id')]);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function atProvider(Builder $query): void
    {
        $query->whereNotNull('provider_id')->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => Size::class,
            'quality' => Quality::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'min_price' => 'integer',
            'estimated_price' => 'integer',
            'max_price' => 'integer',
            'input_text_tokens' => 'integer',
            'input_image_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cost' => 'integer',
            'price' => 'integer',
            'version_count' => 'integer',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
