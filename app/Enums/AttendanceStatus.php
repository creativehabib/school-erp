<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Shared by staff and (later) student attendance.
 */
enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::HalfDay => 'Half Day',
            self::Leave => 'On Leave',
            self::Holiday => 'Holiday',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Present => 'lime',
            self::Absent => 'red',
            self::Late => 'amber',
            self::HalfDay => 'amber',
            self::Leave => 'blue',
            self::Holiday => 'zinc',
        };
    }
}
