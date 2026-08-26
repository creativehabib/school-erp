<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Who may borrow. Drives which library_rules row applies, since teachers are
 * normally allowed more books for longer than students.
 */
enum BorrowerType: string
{
    use HasOptions;

    case Student = 'student';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Employee => 'Teacher / Staff',
        };
    }
}
