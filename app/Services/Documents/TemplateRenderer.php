<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A deliberately small, sandboxed template language for administrator-authored markup.
 *
 * THIS IS A SECURITY BOUNDARY, and it is the reason this class exists at all.
 *
 * The obvious implementation of "customisable HTML templates" is
 * `Blade::render($template->body, $data)`. Do not do that. Blade compiles to PHP and
 * evaluates it. Any user who can save a template can save `{{ system('cat .env') }}` or
 * `@php ... @endphp`, and the ID-card designer becomes remote code execution against the
 * whole school database. In an ERP where "office staff can edit certificate wording" is a
 * routine permission, that is a very short path from a low-privilege role to total
 * compromise.
 *
 * So this renderer never compiles anything. It walks the markup and substitutes values
 * from a fixed payload array. Supported syntax, and nothing else:
 *
 *   {{ student.name_en }}          escaped value, dot path into the payload
 *   {{{ qr.img }}}                 raw value - only for markup WE generated
 *   {{ total | number }}           a filter from a fixed whitelist
 *   {{#items}} ... {{/items}}      section: loops arrays, shows once for truthy scalars
 *   {{^items}} ... {{/items}}      inverted section: shows when empty or false
 *   {{ . }}                        the current item inside a section
 *
 * There is no expression evaluation, no method calls, no arbitrary PHP. The worst a
 * malicious template can do is print data the operator was already allowed to see, or
 * produce ugly HTML.
 */
final class TemplateRenderer
{
    /** Filters an administrator may apply. Anything else renders the raw value. */
    private const FILTERS = [
        'upper', 'lower', 'title', 'number', 'money', 'date', 'time',
        'datetime', 'bn', 'ordinal', 'trim', 'nl2br',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $template, array $data): string
    {
        $output = $this->renderSections($template, $data);

        return $this->renderVariables($output, $data);
    }

    /**
     * Sections first, so a loop body's placeholders resolve against the item and not the
     * outer payload. Doing variables first would replace {{ name }} inside a loop with
     * the top-level name for every row.
     *
     * @param  array<string, mixed>  $data
     */
    private function renderSections(string $template, array $data): string
    {
        $pattern = '/\{\{([#^])\s*([a-zA-Z0-9_.]+)\s*\}\}(.*?)\{\{\/\s*\2\s*\}\}/s';

        // Bounded loop rather than recursion so a template with nested sections resolves
        // outer-to-inner, and a pathological template cannot spin forever.
        for ($depth = 0; $depth < 6; $depth++) {
            $before = $template;

            $template = preg_replace_callback(
                $pattern,
                function (array $m) use ($data): string {
                    [$all, $marker, $path, $body] = $m;
                    $value = Arr::get($data, $path);
                    $inverted = $marker === '^';

                    if ($inverted) {
                        return $this->isEmptyish($value) ? $body : '';
                    }

                    if ($this->isEmptyish($value)) {
                        return '';
                    }

                    if (! is_array($value)) {
                        return $body;
                    }

                    // Associative array: treat as a single context, not a list of values.
                    if (! array_is_list($value)) {
                        return $this->renderVariables($body, $value + $data);
                    }

                    $rendered = '';
                    $index = 0;

                    foreach ($value as $item) {
                        $index++;
                        $context = is_array($item) ? $item : ['.' => $item];
                        $context['_index'] = $index;
                        $context['_odd'] = $index % 2 === 1;

                        $rendered .= $this->renderVariables($body, $context + $data);
                    }

                    return $rendered;
                },
                $template,
            ) ?? $template;

            if ($template === $before) {
                break;
            }
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderVariables(string $template, array $data): string
    {
        // Raw first. {{{ x }}} would otherwise be seen by the escaped pattern as
        // {{ {x} }} and emit stray braces.
        $template = preg_replace_callback(
            '/\{\{\{\s*([a-zA-Z0-9_.\s|]+?)\s*\}\}\}/',
            fn (array $m) => $this->resolve($m[1], $data, escape: false),
            $template,
        ) ?? $template;

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.\s|]+?)\s*\}\}/',
            fn (array $m) => $this->resolve($m[1], $data, escape: true),
            $template,
        ) ?? $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolve(string $expression, array $data, bool $escape): string
    {
        $parts = array_map('trim', explode('|', $expression));
        $path = array_shift($parts) ?? '';

        $value = $path === '.' ? Arr::get($data, '.') : Arr::get($data, $path);

        foreach ($parts as $filter) {
            if (in_array($filter, self::FILTERS, true)) {
                $value = $this->applyFilter($filter, $value);
            }
        }

        if ($value === null || is_array($value)) {
            return '';
        }

        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }

        $value = (string) $value;

        return $escape ? e($value) : $value;
    }

    private function applyFilter(string $filter, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($filter) {
            'upper' => Str::upper((string) $value),
            'lower' => Str::lower((string) $value),
            'title' => Str::title((string) $value),
            'number' => is_numeric($value) ? number_format((float) $value) : $value,
            'money' => is_numeric($value) ? number_format((float) $value, 2) : $value,
            'date' => $this->formatDate($value, 'd/m/Y'),
            'time' => $this->formatDate($value, 'h:i A'),
            'datetime' => $this->formatDate($value, 'd/m/Y h:i A'),
            'bn' => $this->toBengaliDigits((string) $value),
            'ordinal' => $this->ordinal($value),
            'trim' => trim((string) $value),
            'nl2br' => nl2br(e((string) $value)),
            default => $value,
        };
    }

    private function formatDate(mixed $value, string $format): string
    {
        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Western digits to Bengali digits.
     *
     * Needed because a Bangla certificate that reads "জন্ম তারিখ: 12/03/2011" looks
     * half-finished to a parent, and because the education board's own forms use
     * Bengali numerals throughout.
     */
    public function toBengaliDigits(string $value): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'],
            $value,
        );
    }

    private function ordinal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $number = (int) $value;
        $suffix = match (true) {
            $number % 100 >= 11 && $number % 100 <= 13 => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }

    private function isEmptyish(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || $value === false || $value === '' || $value === 0;
    }

    /**
     * Placeholders available for a payload, for the template editor's help panel.
     *
     * An administrator cannot guess "{{ enrollment.class_roll }}", and a customisable
     * template nobody knows the variable names for is not customisable. Generated from
     * the real payload so the list can never drift from what actually resolves.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function availablePlaceholders(array $payload, string $prefix = ''): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (is_int($key)) {
                continue;
            }

            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && ! array_is_list($value) && $value !== []) {
                $keys = array_merge($keys, $this->availablePlaceholders($value, $path));

                continue;
            }

            $keys[] = array_is_list($value ?? []) && is_array($value)
                ? '#'.$path
                : $path;
        }

        sort($keys);

        return $keys;
    }
}
