<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmployeeStatus: string
{
    use HasOptions;

    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Retired = 'retired';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On Leave',
            self::Suspended => 'Suspended',
            self::Resigned => 'Resigned',
            self::Retired => 'Retired',
            self::Terminated => 'Terminated',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'lime',
            self::OnLeave => 'amber',
            self::Suspended => 'red',
            self::Resigned => 'zinc',
            self::Retired => 'zinc',
            self::Terminated => 'red',
        };
    }
}
