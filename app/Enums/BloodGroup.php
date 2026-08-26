<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BloodGroup: string
{
    use HasOptions;

    case APos = 'A+';
    case ANeg = 'A-';
    case BPos = 'B+';
    case BNeg = 'B-';
    case ABPos = 'AB+';
    case ABNeg = 'AB-';
    case OPos = 'O+';
    case ONeg = 'O-';

    public function label(): string
    {
        return match ($this) {
            self::APos => 'A+',
            self::ANeg => 'A-',
            self::BPos => 'B+',
            self::BNeg => 'B-',
            self::ABPos => 'AB+',
            self::ABNeg => 'AB-',
            self::OPos => 'O+',
            self::ONeg => 'O-',
        };
    }
}
