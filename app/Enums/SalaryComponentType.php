<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SalaryComponentType: string
{
    use HasOptions;

    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Earning',
            self::Deduction => 'Deduction',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Earning => 'lime',
            self::Deduction => 'red',
        };
    }
}
