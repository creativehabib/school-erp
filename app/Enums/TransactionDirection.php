<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Direction of money movement in the unified cash book.
 */
enum TransactionDirection: string
{
    use HasOptions;

    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Income',
            self::Out => 'Expense',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::In => 'lime',
            self::Out => 'red',
        };
    }
}
