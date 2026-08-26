<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Enums\DayOfWeek;
use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of the timetable: this section, this weekday, this period.
 *
 * Teacher double-booking is prevented by a unique index in the migration rather
 * than by application validation, because a routine is usually built by several
 * people at once and the last writer would otherwise win silently.
 */
class ClassRoutine extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'section_id', 'period_id', 'day_of_week',
        'class_subject_id', 'employee_id', 'room_no',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function scopeForSection(Builder $query, int $sectionId): Builder
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeForTeacher(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function subjectName(): string
    {
        return $this->classSubject?->subject?->name ?? '-';
    }

    public function teacherName(): string
    {
        return $this->employee?->user?->name ?? '-';
    }
}
