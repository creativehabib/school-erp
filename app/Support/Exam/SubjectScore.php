<?php

declare(strict_types=1);

namespace App\Support\Exam;

/**
 * One subject's outcome for one student, as the GPA calculator sees it.
 *
 * A value object rather than an Eloquent row because the calculator must be testable
 * without a database, and because the same shape is produced from three different
 * sources: live marks, a stored result snapshot, and a weighted blend of several
 * terms.
 */
final readonly class SubjectScore
{
    public function __construct(
        public int $classSubjectId,
        public string $subjectName,
        public float $fullMarks,
        public float $obtainedMarks,
        public float $gpa,
        public ?string $grade,
        public bool $isFailing,
        public bool $isOptional = false,
        public bool $isAbsent = false,
        public bool $isCountable = true,
        public ?string $subjectNameBn = null,
        public ?float $cqMarks = null,
        public ?float $mcqMarks = null,
        public ?float $practicalMarks = null,
    ) {}

    public function percentage(): float
    {
        if ($this->fullMarks <= 0) {
            return 0.0;
        }

        return round(($this->obtainedMarks / $this->fullMarks) * 100, 2);
    }

    /**
     * Whether this subject participates in the pass/fail decision.
     *
     * The optional subject is excluded on purpose. Under the Bangladeshi rule a
     * student who fails their 4th subject has not failed the exam - the subject simply
     * contributes nothing. Treating it as a normal failure is the single most common
     * bug in school software here, and it fails students who actually passed.
     */
    public function countsTowardResult(): bool
    {
        return $this->isCountable && ! $this->isOptional;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'class_subject_id' => $this->classSubjectId,
            'subject' => $this->subjectName,
            'subject_bn' => $this->subjectNameBn,
            'full_marks' => $this->fullMarks,
            'obtained_marks' => $this->obtainedMarks,
            'cq' => $this->cqMarks,
            'mcq' => $this->mcqMarks,
            'practical' => $this->practicalMarks,
            'gpa' => $this->gpa,
            'grade' => $this->grade,
            'is_failing' => $this->isFailing,
            'is_optional' => $this->isOptional,
            'is_absent' => $this->isAbsent,
            'is_countable' => $this->isCountable,
        ];
    }
}
