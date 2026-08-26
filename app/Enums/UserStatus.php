<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum UserStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'lime',
            self::Inactive => 'zinc',
            self::Suspended => 'red',
        };
    }
}
