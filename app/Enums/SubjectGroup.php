<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Streams available from class 9 onward.
 *
 * `General` is the value used for classes that do not stream at all, which keeps
 * the group column non-null where a school prefers explicitness.
 */
enum SubjectGroup: string
{
    use HasOptions;

    case General = 'general';
    case Science = 'science';
    case BusinessStudies = 'business_studies';
    case Humanities = 'humanities';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Science => 'Science',
            self::BusinessStudies => 'Business Studies',
            self::Humanities => 'Humanities',
        };
    }

    public function labelBn(): string
    {
        return match ($this) {
            self::General => 'সাধারণ',
            self::Science => 'বিজ্ঞান',
            self::BusinessStudies => 'ব্যবসায় শিক্ষা',
            self::Humanities => 'মানবিক',
        };
    }
}
