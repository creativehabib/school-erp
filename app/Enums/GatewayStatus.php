<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GatewayStatus: string
{
    use HasOptions;

    case Initiated = 'initiated';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Initiated',
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Initiated => 'zinc',
            self::Pending => 'amber',
            self::Completed => 'lime',
            self::Failed => 'red',
            self::Cancelled => 'zinc',
            self::Refunded => 'blue',
        };
    }
}
