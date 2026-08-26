<?php

declare(strict_types=1);

namespace App\Support\Exam;

/**
 * The computed outcome for one student in one exam.
 *
 * Immutable, and deliberately holds the subject breakdown it was derived from, so the
 * marksheet renderer and the ExamResult snapshot both read from one object instead of
 * recomputing.
 */
final readonly class GpaResult
{
    /**
     * @param  array<int, SubjectScore>  $subjects
     */
    public function __construct(
        public float $gpa,
        public ?string $grade,
        public bool $isFailed,
        public int $failedSubjectCount,
        public int $appearedSubjectCount,
        public float $totalFullMarks,
        public float $totalObtainedMarks,
        public float $averageMarks,
        public array $subjects = [],
    ) {}

    public static function empty(): self
    {
        return new self(
            gpa: 0.0,
            grade: null,
            isFailed: true,
            failedSubjectCount: 0,
            appearedSubjectCount: 0,
            totalFullMarks: 0.0,
            totalObtainedMarks: 0.0,
            averageMarks: 0.0,
        );
    }

    public function gpaLabel(): string
    {
        return number_format($this->gpa, 2);
    }

    public function resultLabel(): string
    {
        return $this->isFailed ? 'Failed' : 'Passed';
    }

    /** @return array<int, SubjectScore> */
    public function failedSubjects(): array
    {
        return array_values(array_filter(
            $this->subjects,
            static fn (SubjectScore $score) => $score->isFailing && $score->countsTowardResult(),
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function subjectSnapshot(): array
    {
        return array_map(static fn (SubjectScore $score) => $score->toArray(), $this->subjects);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'gpa' => $this->gpa,
            'grade' => $this->grade,
            'is_failed' => $this->isFailed,
            'failed_subject_count' => $this->failedSubjectCount,
            'appeared_subject_count' => $this->appearedSubjectCount,
            'total_full_marks' => $this->totalFullMarks,
            'total_obtained_marks' => $this->totalObtainedMarks,
            'average_marks' => $this->averageMarks,
            'subjects' => $this->subjectSnapshot(),
        ];
    }
}
