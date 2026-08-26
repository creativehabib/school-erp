<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeCategory: string
{
    use HasOptions;

    case Tuition = 'tuition';
    case Exam = 'exam';
    case Admission = 'admission';
    case Session = 'session';
    case Transport = 'transport';
    case Library = 'library';
    case Hostel = 'hostel';
    case Development = 'development';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tuition => 'Tuition Fee',
            self::Exam => 'Exam Fee',
            self::Admission => 'Admission Fee',
            self::Session => 'Session Charge',
            self::Transport => 'Transport Fee',
            self::Library => 'Library Fee',
            self::Hostel => 'Hostel Fee',
            self::Development => 'Development Fee',
            self::Other => 'Other',
        };
    }
}
