<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmployeeType: string
{
    use HasOptions;

    case Teaching = 'teaching';
    case NonTeaching = 'non_teaching';

    public function label(): string
    {
        return match ($this) {
            self::Teaching => 'Teaching',
            self::NonTeaching => 'Non-Teaching',
        };
    }
}
