<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Weekdays, backed by the same integers Carbon uses (0 = Sunday), so
 * `DayOfWeek::from($date->dayOfWeek)` needs no translation layer.
 *
 * Ordering here starts at Saturday because that is where the Bangladeshi school
 * week starts; the backing integers keep Carbon compatibility regardless.
 */
enum DayOfWeek: int
{
    use HasOptions;

    case Saturday = 6;
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;

    public function label(): string
    {
        return match ($this) {
            self::Saturday => 'Saturday',
            self::Sunday => 'Sunday',
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::Saturday => 'শনিবার',
            self::Sunday => 'রবিবার',
            self::Monday => 'সোমবার',
            self::Tuesday => 'মঙ্গলবার',
            self::Wednesday => 'বুধবার',
            self::Thursday => 'বৃহস্পতিবার',
            self::Friday => 'শুক্রবার',
        };
    }

    /**
     * Days the routine grid should render, in Bangladeshi week order.
     *
     * @return array<int, self>
     */
    public static function schoolWeek(): array
    {
        return [self::Saturday, self::Sunday, self::Monday, self::Tuesday, self::Wednesday, self::Thursday];
    }

    public function isWeekend(): bool
    {
        return $this === self::Friday;
    }
}
