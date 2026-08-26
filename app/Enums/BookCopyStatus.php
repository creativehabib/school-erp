<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status of one physical copy on the shelf.
 *
 * Status lives on the copy, not the title. A library with six copies of the same
 * book needs to know that copy #3 is lost while #1, #2 and #4 are lendable, and a
 * single available_quantity integer on the title cannot express that - nor can it
 * tell you WHICH copy a student walked off with.
 */
enum BookCopyStatus: string
{
    use HasOptions;

    case Available = 'available';
    case Issued = 'issued';
    case Reserved = 'reserved';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Issued => 'Issued',
            self::Reserved => 'Reserved',
            self::Damaged => 'Damaged',
            self::Lost => 'Lost',
            self::Discarded => 'Discarded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'lime',
            self::Issued => 'blue',
            self::Reserved => 'amber',
            self::Damaged => 'orange',
            self::Lost, self::Discarded => 'red',
        };
    }

    public function isLendable(): bool
    {
        return $this === self::Available;
    }
}
