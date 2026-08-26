<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EnrollmentStatus: string
{
    use HasOptions;

    case Running = 'running';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Promoted => 'Promoted',
            self::Repeated => 'Repeated',
            self::Left => 'Left',
        };
    }

    /** Flux badge / callout colour. */
    public function color(): string
    {
        return match ($this) {
            self::Running => 'lime',
            self::Promoted => 'blue',
            self::Repeated => 'amber',
            self::Left => 'zinc',
        };
    }
}
