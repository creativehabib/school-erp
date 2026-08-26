<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StudentStatus: string
{
    use HasOptions;

    case Active = 'active';
    case PassedOut = 'passed_out';
    case Transferred = 'transferred';
    case Dropped = 'dropped';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PassedOut => 'Passed Out',
            self::Transferred => 'Transferred',
            self::Dropped => 'Dropped Out',
            self::Suspended => 'Suspended',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'lime',
            self::PassedOut => 'blue',
            self::Transferred => 'amber',
            self::Dropped => 'red',
            self::Suspended => 'red',
        };
    }
}
