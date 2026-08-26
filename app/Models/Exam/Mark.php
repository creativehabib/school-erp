<?php

declare(strict_types=1);

namespace App\Models\Exam;

use App\Models\Academic\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's marks for one paper.
 *
 * grade, gpa and is_failing are stored rather than computed on read. They snapshot
 * what the grade scale said when results were processed. Deriving them live would
 * mean a school editing its bands in March silently rewrites every marksheet it
 * printed in January.
 *
 * `is_absent` is not the same as zero. Absent students are excluded from subject
 * averages and highest-mark statistics; a genuine zero is not.
 */
class Mark extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_subject_id', 'student_enrollment_id', 'cq_marks', 'mcq_marks',
        'practical_marks', 'obtained_marks', 'is_absent', 'grade', 'gpa',
        'is_failing', 'entered_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'cq_marks' => 'decimal:2',
            'mcq_marks' => 'decimal:2',
            'practical_marks' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'is_absent' => 'boolean',
            'gpa' => 'decimal:2',
            'is_failing' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeForExam(Builder $query, int $examId): Builder
    {
        return $query->whereHas('examSubject', fn (Builder $q) => $q->where('exam_id', $examId));
    }

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('is_absent', false);
    }

    /**
     * Sum of whichever components the paper uses.
     *
     * Falls back to the stored total when the paper has no component split, so a
     * primary-school paper where the teacher types one number is not zeroed out.
     */
    public function calculateTotal(): float
    {
        $components = array_filter(
            [$this->cq_marks, $this->mcq_marks, $this->practical_marks],
            static fn ($value) => $value !== null
        );

        if ($components === []) {
            return (float) $this->obtained_marks;
        }

        return array_sum(array_map(static fn ($value) => (float) $value, $components));
    }

    public function displayMarks(): string
    {
        return $this->is_absent ? 'A' : (string) (float) $this->obtained_marks;
    }
}
