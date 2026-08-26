<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmploymentType: string
{
    use HasOptions;

    case Permanent = 'permanent';
    case Contractual = 'contractual';
    case PartTime = 'part_time';
    case Probation = 'probation';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Contractual => 'Contractual',
            self::PartTime => 'Part Time',
            self::Probation => 'Probation',
        };
    }
}
