<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $generation_id
 * @property CreditTransactionType $type
 * @property int $amount
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['generation_id', 'type', 'amount'])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit transactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Credit transactions are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
        ];
    }
}
