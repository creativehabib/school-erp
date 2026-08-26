<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Lifecycle of a loan.
 *
 * Overdue is NOT stored as a status that a nightly job has to maintain - it is
 * derived from due_date vs today by the model. Storing it would mean every loan in
 * the system silently becomes wrong at midnight if the scheduler is not running,
 * which on shared hosting it frequently is not.
 */
enum BookIssueStatus: string
{
    use HasOptions;

    case Issued = 'issued';
    case Returned = 'returned';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Returned => 'Returned',
            self::Lost => 'Reported Lost',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'blue',
            self::Returned => 'lime',
            self::Lost => 'red',
            self::Cancelled => 'zinc',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Issued;
    }
}
