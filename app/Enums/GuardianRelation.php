<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GuardianRelation: string
{
    use HasOptions;

    case Father = 'father';
    case Mother = 'mother';
    case LegalGuardian = 'legal_guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::LegalGuardian => 'Legal Guardian',
        };
    }
}
