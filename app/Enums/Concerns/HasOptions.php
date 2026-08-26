<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for every string-backed enum in the application.
 *
 * `options()` is shaped for a Flux select:
 *   <flux:select.option :value="$value" :label="$label" />
 */
trait HasOptions
{
    /** @return array<int|string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return array<int, int|string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function is(self $other): bool
    {
        return $this === $other;
    }

    public function isAnyOf(self ...$others): bool
    {
        return in_array($this, $others, true);
    }
}
