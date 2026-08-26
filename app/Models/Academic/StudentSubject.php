<?php

declare(strict_types=1);

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The subjects one student sits, and which single one is their 4th subject.
 *
 * This table is what makes the Bangladeshi optional-subject GPA rule expressible.
 * Two classmates in the same section can elect different 4th subjects, so "is this
 * the optional subject" is a property of the student's election, not of the class
 * curriculum. GpaCalculator reads is_optional from here and nowhere else.
 */
class StudentSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_enrollment_id', 'class_subject_id', 'is_optional',
    ];

    protected function casts(): array
    {
        return [
            'is_optional' => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_optional', true);
    }

    public function scopeCompulsory(Builder $query): Builder
    {
        return $query->where('is_optional', false);
    }
}
