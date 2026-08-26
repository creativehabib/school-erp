<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The five fixed roles. Using an enum instead of magic strings means
 * `->hasRole(RoleName::Teacher->value)` is refactor-safe and typo-proof.
 */
enum RoleName: string
{
    use HasOptions;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
            self::Guardian => 'Guardian (Father)',
        };
    }
}
