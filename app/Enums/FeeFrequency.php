<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeFrequency: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Annual = 'annual';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half Yearly',
            self::Annual => 'Annual',
            self::OneTime => 'One Time',
        };
    }
}
