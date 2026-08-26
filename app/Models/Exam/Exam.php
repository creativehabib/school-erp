<?php

declare(strict_types=1);

namespace App\Models\Exam;

use App\Enums\ExamType;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An exam event within a session.
 *
 * `is_locked` is the column that matters operationally. Once results are published,
 * mark entry must stop: a teacher quietly "fixing" one number after publication
 * invalidates every printed marksheet and silently reshuffles merit positions that
 * parents have already been told about.
 */
class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id', 'grade_scale_id', 'name', 'name_bn', 'code',
        'type', 'term', 'weight', 'starts_on', 'ends_on', 'mark_entry_deadline',
        'is_locked', 'publish_marksheet', 'result_published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'term' => 'integer',
            'weight' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'mark_entry_deadline' => 'date',
            'is_locked' => 'boolean',
            'publish_marksheet' => 'boolean',
            'result_published_at' => 'datetime',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('result_published_at')->where('publish_marksheet', true);
    }

    public function scopeOpenForEntry(Builder $query): Builder
    {
        return $query->where('is_locked', false);
    }

    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    public function isPublished(): bool
    {
        return $this->result_published_at !== null;
    }

    /**
     * Whether a teacher may still submit marks.
     *
     * The deadline is advisory for administrators and binding for teachers; the
     * calling policy decides which. Lock always wins over deadline.
     */
    public function isMarkEntryOpen(): bool
    {
        if ($this->is_locked) {
            return false;
        }

        return $this->mark_entry_deadline === null
            || $this->mark_entry_deadline->endOfDay()->isFuture();
    }

    /**
     * Classes this exam covers, derived from its papers.
     *
     * Derived rather than stored in a pivot: the set of classes is already implied
     * by which papers were created, and a second source would drift out of step the
     * first time someone deleted a paper.
     *
     * @return Collection<int, SchoolClass>
     */
    public function classes(): Collection
    {
        return SchoolClass::query()
            ->whereIn('id', $this->examSubjects()
                ->join('class_subjects', 'class_subjects.id', '=', 'exam_subjects.class_subject_id')
                ->distinct()
                ->pluck('class_subjects.school_class_id'))
            ->orderBy('level')
            ->get();
    }

    public function periodLabel(): string
    {
        if (! $this->starts_on || ! $this->ends_on) {
            return $this->name;
        }

        return sprintf(
            '%s (%s - %s)',
            $this->name,
            $this->starts_on->format('d M'),
            $this->ends_on->format('d M Y'),
        );
    }
}
