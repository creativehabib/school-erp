<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Lifecycle of a bulk generation run.
 *
 * `PartiallyFailed` exists on purpose: in a 1,200-card run, three students with a
 * missing photo should not present to the operator as a total failure, and should
 * not present as a success either.
 */
enum BatchStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::PartiallyFailed => 'Completed with errors',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'zinc',
            self::Processing => 'blue',
            self::Completed => 'lime',
            self::PartiallyFailed => 'amber',
            self::Failed, self::Cancelled => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::PartiallyFailed, self::Failed, self::Cancelled], true);
    }
}
