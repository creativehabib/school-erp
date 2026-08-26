<?php

declare(strict_types=1);

namespace App\Models\Exam;

use App\Models\Academic\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The processed result: one row per student per exam.
 *
 * A materialised summary, rebuilt by ProcessExamResult. It exists for two reasons.
 * Merit position is inherently a whole-cohort calculation - you cannot derive
 * "third in class" from one student's rows - and a marksheet has to render from a
 * stable snapshot rather than recompute GPA on every PDF request.
 *
 * `subject_snapshot` freezes the per-subject breakdown at publication, so a
 * marksheet reprinted in 2029 matches the original even if a subject has since been
 * renamed or dropped from the class.
 */
class ExamResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'student_enrollment_id', 'total_full_marks', 'total_obtained_marks',
        'average_marks', 'gpa', 'grade', 'is_failed', 'failed_subject_count',
        'appeared_subject_count', 'class_position', 'section_position',
        'present_days', 'working_days', 'subject_snapshot', 'remarks',
        'processed_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'total_full_marks' => 'decimal:2',
            'total_obtained_marks' => 'decimal:2',
            'average_marks' => 'decimal:2',
            'gpa' => 'decimal:2',
            'is_failed' => 'boolean',
            'subject_snapshot' => 'array',
            'processed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function scopeForExam(Builder $query, int $examId): Builder
    {
        return $query->where('exam_id', $examId);
    }

    public function scopePassed(Builder $query): Builder
    {
        return $query->where('is_failed', false);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('is_failed', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /** Merit order: GPA first, then raw marks to break ties. */
    public function scopeByMerit(Builder $query): Builder
    {
        return $query->orderByDesc('gpa')->orderByDesc('total_obtained_marks');
    }

    public function resultLabel(): string
    {
        return $this->is_failed ? 'Failed' : 'Passed';
    }

    public function gpaLabel(): string
    {
        return number_format((float) $this->gpa, 2);
    }

    public function attendanceLabel(): string
    {
        if ($this->working_days === null) {
            return '-';
        }

        return sprintf('%d / %d', (int) $this->present_days, (int) $this->working_days);
    }
}
