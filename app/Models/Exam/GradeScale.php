<?php

declare(strict_types=1);

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * A named grading table.
 *
 * Plural on purpose. The SSC 5.00 scale is not universal - many schools grade
 * classes 1 to 5 differently - and more importantly, a school that revises its
 * bands must not retroactively alter marksheets already issued. Exams point at a
 * scale, so historical results keep the scale they were graded under.
 *
 * @property-read Collection<int, GradeScaleItem> $items
 */
class GradeScale extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'max_gpa', 'optional_subject_deduction',
        'is_default', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'max_gpa' => 'decimal:2',
            'optional_subject_deduction' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('grade_scale.default'));
        static::deleted(fn () => Cache::forget('grade_scale.default'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(GradeScaleItem::class)->orderByDesc('min_marks');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return Cache::rememberForever(
            'grade_scale.default',
            fn () => static::with('items')->where('is_default', true)->first()
        );
    }

    /**
     * The band a mark falls into.
     *
     * Absent students must not be passed through here - an absence is not a zero,
     * and the caller decides that. Marks are matched inclusively at both ends
     * because school-published bands are written that way (80-100, 70-79) and a
     * half-open interval would leave 79.5 ungraded.
     */
    public function gradeFor(float $marks): ?GradeScaleItem
    {
        return $this->items
            ->first(fn (GradeScaleItem $item) => $marks >= (float) $item->min_marks
                && $marks <= (float) $item->max_marks);
    }

    /**
     * The band a computed GPA falls into, for the overall result letter.
     *
     * The overall grade is derived from the GPA, not from the average of marks.
     * A student can average 74% and still be graded A- if their subject spread is
     * uneven, and printing the mark-based letter would contradict the GPA printed
     * beside it.
     */
    public function gradeForGpa(float $gpa): ?GradeScaleItem
    {
        return $this->items
            ->sortByDesc(fn (GradeScaleItem $item) => (float) $item->gpa)
            ->first(fn (GradeScaleItem $item) => $gpa >= (float) $item->gpa);
    }

    public function failingItem(): ?GradeScaleItem
    {
        return $this->items->first(fn (GradeScaleItem $item) => $item->is_failing);
    }
}
