<?php

declare(strict_types=1);

namespace App\Models\Exam;

use App\Models\Academic\ClassSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * One paper: this exam, this class-subject, these marks.
 *
 * Component columns exist because Bangladeshi pass rules are per component, not per
 * paper. A student scoring 60 out of 100 overall but 8 out of 40 in the creative
 * questions has failed, and a single obtained_marks column cannot express that.
 * NULL component full marks mean "this paper has no such component", which keeps
 * primary-school papers a single field.
 */
class ExamSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'class_subject_id', 'full_marks', 'pass_marks',
        'cq_full_marks', 'cq_pass_marks', 'mcq_full_marks', 'mcq_pass_marks',
        'practical_full_marks', 'practical_pass_marks', 'is_countable',
        'exam_date', 'starts_at', 'duration_minutes', 'room_no', 'serial',
    ];

    protected function casts(): array
    {
        return [
            'full_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'cq_full_marks' => 'decimal:2',
            'cq_pass_marks' => 'decimal:2',
            'mcq_full_marks' => 'decimal:2',
            'mcq_pass_marks' => 'decimal:2',
            'practical_full_marks' => 'decimal:2',
            'practical_pass_marks' => 'decimal:2',
            'is_countable' => 'boolean',
            'exam_date' => 'date',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function scopeCountable(Builder $query): Builder
    {
        return $query->where('is_countable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('serial')->orderBy('id');
    }

    public function subjectName(): string
    {
        return $this->classSubject?->subject?->name ?? '-';
    }

    /**
     * Which mark components this paper actually uses.
     *
     * The marks-entry screen builds its columns from this, so a primary paper shows
     * one input and an SSC science paper shows three.
     *
     * @return array<int, string>
     */
    public function activeComponents(): array
    {
        $components = [];

        if ($this->cq_full_marks !== null) {
            $components[] = 'cq';
        }

        if ($this->mcq_full_marks !== null) {
            $components[] = 'mcq';
        }

        if ($this->practical_full_marks !== null) {
            $components[] = 'practical';
        }

        return $components;
    }

    public function hasComponents(): bool
    {
        return $this->activeComponents() !== [];
    }

    /**
     * Component ceilings, for client-side max attributes and server-side validation.
     *
     * @return array<string, float>
     */
    public function componentCeilings(): array
    {
        return array_filter([
            'cq' => $this->cq_full_marks !== null ? (float) $this->cq_full_marks : null,
            'mcq' => $this->mcq_full_marks !== null ? (float) $this->mcq_full_marks : null,
            'practical' => $this->practical_full_marks !== null ? (float) $this->practical_full_marks : null,
        ], static fn (?float $value) => $value !== null);
    }

    /**
     * Reject marks that cannot exist before they reach the database.
     *
     * Worth doing centrally rather than in the Livewire rules array, because marks
     * arrive from three places - the entry grid, the CSV import and the seeder - and
     * a mark above full marks corrupts the whole cohort's merit positions rather
     * than just one row.
     *
     * @param  array<string, float|null>  $components
     *
     * @throws ValidationException
     */
    public function assertMarksValid(array $components, float $total): void
    {
        foreach ($this->componentCeilings() as $key => $ceiling) {
            $value = (float) ($components[$key] ?? 0);

            if ($value < 0) {
                throw ValidationException::withMessages([
                    $key => "{$this->subjectName()}: marks cannot be negative.",
                ]);
            }

            if ($value > $ceiling) {
                throw ValidationException::withMessages([
                    $key => sprintf(
                        '%s: %s marks cannot exceed %s.',
                        $this->subjectName(),
                        strtoupper($key),
                        (int) $ceiling,
                    ),
                ]);
            }
        }

        if ($total > (float) $this->full_marks) {
            throw ValidationException::withMessages([
                'total' => sprintf(
                    '%s: total %s exceeds full marks %s.',
                    $this->subjectName(),
                    $total,
                    (int) $this->full_marks,
                ),
            ]);
        }
    }

    /**
     * Whether the student passed this paper.
     *
     * Component thresholds are checked independently of the total, which is the
     * whole point: passing overall while failing the creative-question component is
     * still a fail on a Bangladeshi board paper.
     */
    public function isPassing(Mark $mark): bool
    {
        if ($mark->is_absent) {
            return false;
        }

        if ((float) $mark->obtained_marks < (float) $this->pass_marks) {
            return false;
        }

        $checks = [
            [$this->cq_pass_marks, $mark->cq_marks],
            [$this->mcq_pass_marks, $mark->mcq_marks],
            [$this->practical_pass_marks, $mark->practical_marks],
        ];

        foreach ($checks as [$required, $scored]) {
            if ($required !== null && (float) $scored < (float) $required) {
                return false;
            }
        }

        return true;
    }
}
