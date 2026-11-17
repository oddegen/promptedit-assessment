<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }

    /** @return list<self> */
    public function reachableFrom(): array
    {
        return match ($this) {
            self::Pending => [],
            self::Queued => [self::Pending],
            self::Processing => [self::Pending, self::Queued],
            self::Succeeded, self::Failed => [self::Pending, self::Queued, self::Processing],
        };
    }
}
