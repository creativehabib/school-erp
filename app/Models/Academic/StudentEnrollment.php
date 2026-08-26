<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Enums\EnrollmentStatus;
use App\Models\Exam\ExamResult;
use App\Models\Exam\Mark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's placement within one academic session. Source of truth for
 * "which class was this student in when that invoice / mark was recorded".
 */
class StudentEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'academic_session_id', 'school_class_id', 'section_id',
        'shift_id', 'class_roll', 'group', 'status', 'is_current', 'enrolled_on',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date',
            'is_current' => 'boolean',
            'status' => EnrollmentStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function studentSubjects(): HasMany
    {
        return $this->hasMany(StudentSubject::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopeForSession(Builder $query, int $academicSessionId): Builder
    {
        return $query->where('academic_session_id', $academicSessionId);
    }

    /**
     * The student's elected 4th subject, if any.
     *
     * Nullable by design: most classes have none, and the GPA calculator treats absence
     * of an optional subject as the normal case rather than an error.
     */
    public function optionalSubject(): ?StudentSubject
    {
        return $this->studentSubjects()->optional()->with('classSubject.subject')->first();
    }

    /** "Class 9 - A (Science)" for headers and dropdowns. */
    public function placementLabel(): string
    {
        $parts = array_filter([
            $this->schoolClass?->name,
            $this->section?->name,
            $this->group,
        ]);

        return implode(' - ', $parts) ?: '-';
    }
}
