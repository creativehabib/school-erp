<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LateFineType: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case PerDay = 'per_day';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Amount',
            self::PerDay => 'Per Day',
            self::Percent => 'Percentage',
        };
    }
}
