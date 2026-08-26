<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton. Read it through SchoolProfile::current() so the row is cached for
 * the request/app lifetime — it is touched by every PDF header and layout.
 */
class SchoolProfile extends Model
{
    protected $table = 'school_profile';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'established_year' => 'integer',
        ];
    }

    public static function current(): self
    {
        return Cache::rememberForever(
            'school.profile',
            fn () => static::query()->firstOrFail()
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('school.profile'));
        static::deleted(fn () => Cache::forget('school.profile'));
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    /**
     * Fiscal year containing the given date. BD government fiscal year runs
     * July -> June, so 2026-08-26 falls in FY 2026-2027.
     */
    public function fiscalYearFor(\DateTimeInterface $date): array
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        $start = $month >= $this->fiscal_year_start_month ? $year : $year - 1;

        return [$start, $start + 1];
    }
}
