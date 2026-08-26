<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CalculationType: string
{
    use HasOptions;

    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Amount',
            self::PercentOfBasic => '% of Basic',
        };
    }
}
