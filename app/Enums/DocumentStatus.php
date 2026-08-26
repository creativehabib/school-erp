<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Lifecycle of a single generated document inside a batch.
 */
enum DocumentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Generated = 'generated';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Generated => 'Generated',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Processing => 'blue',
            self::Generated => 'lime',
            self::Failed => 'red',
        };
    }
}
