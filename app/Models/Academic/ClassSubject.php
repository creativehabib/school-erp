<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Models\Exam\ExamSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a class studies, and on what terms.
 *
 * A NULL `group` means every student in the class takes the subject. A value means
 * it only applies to that stream. This single nullable column is what lets classes
 * 9 and 10 split into Science / Business Studies / Humanities without branching the
 * schema, and it is why scopeForGroup() must always include the NULL rows.
 */
class ClassSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_class_id', 'subject_id', 'group', 'is_optional_candidate',
        'has_practical', 'full_marks', 'pass_marks', 'serial', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_optional_candidate' => 'boolean',
            'has_practical' => 'boolean',
            'full_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function studentSubjects(): HasMany
    {
        return $this->hasMany(StudentSubject::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(ClassRoutine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Subjects applicable to a student in the given stream.
     *
     * The NULL branch is not optional: drop it and a Science student loses Bangla
     * and English, because those rows carry no group.
     */
    public function scopeForGroup(Builder $query, ?string $group): Builder
    {
        return $query->where(function (Builder $q) use ($group): void {
            $q->whereNull('group');

            if (filled($group)) {
                $q->orWhere('group', $group);
            }
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('serial')->orderBy('id');
    }

    public function name(): string
    {
        return $this->subject?->name ?? '-';
    }
}
