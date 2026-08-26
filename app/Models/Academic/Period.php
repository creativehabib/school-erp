<?php

declare(strict_types=1);

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A slot in the bell schedule.
 *
 * Kept separate from the routine grid so that moving second period five minutes
 * later is one UPDATE rather than one per section per weekday.
 */
class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id', 'name', 'name_bn', 'starts_at', 'ends_at',
        'is_break', 'serial', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_break' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(ClassRoutine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Teaching periods only - assembly and tiffin get no subject. */
    public function scopeTeaching(Builder $query): Builder
    {
        return $query->where('is_break', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('serial')->orderBy('starts_at');
    }

    public function timeRange(): string
    {
        return sprintf(
            '%s - %s',
            substr((string) $this->starts_at, 0, 5),
            substr((string) $this->ends_at, 0, 5),
        );
    }

    public function durationMinutes(): int
    {
        $start = strtotime((string) $this->starts_at);
        $end = strtotime((string) $this->ends_at);

        return (int) max(0, ($end - $start) / 60);
    }
}
