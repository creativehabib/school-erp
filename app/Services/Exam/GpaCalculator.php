<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\Exam\GradeScale;
use App\Models\Exam\GradeScaleItem;
use App\Support\Exam\GpaResult;
use App\Support\Exam\SubjectScore;

/**
 * The Bangladesh GPA rule, in one place.
 *
 * Four rules are implemented here, and all four are the kind that get hard-coded
 * badly across a dozen files in most school software:
 *
 *   1. GPA is the mean of grade points over COMPULSORY subjects only.
 *   2. The optional (4th) subject adds `grade point - 2.00` to the numerator, floored
 *      at zero, and is NOT added to the denominator. That asymmetry is the whole
 *      point of the rule - it can only ever help.
 *   3. A failing grade in any compulsory subject makes the whole result F / 0.00,
 *      regardless of how good the other subjects were.
 *   4. Failing the optional subject does not fail the student. It contributes nothing
 *      and is otherwise ignored. Getting this wrong fails students who passed, which
 *      is the most damaging bug this module can have.
 *
 * The deduction (2.00) and the ceiling (5.00) are read from the GradeScale rather than
 * being constants, because a school grading classes 1 to 5 on a different scale must
 * not be forced through SSC numbers.
 */
final class GpaCalculator
{
    /**
     * Truncate rather than round to two decimals.
     *
     * A student on 4.996 must not be printed as GPA 5.00. In Bangladesh a 5.00 is a
     * headline claim that parents, and the student, will repeat for years, and
     * arriving at it through rounding is the kind of thing that gets noticed and
     * disputed. Truncation is also what board-published results do in practice.
     */
    private const SCALE = 2;

    public function __construct(private readonly GradeScale $scale) {}

    public static function for(GradeScale $scale): self
    {
        return new self($scale->relationLoaded('items') ? $scale : $scale->load('items'));
    }

    public function gradeScale(): GradeScale
    {
        return $this->scale;
    }

    /**
     * Grade band for a raw mark out of the paper's full marks.
     *
     * Marks are normalised to a percentage before lookup because grade bands are
     * published as percentages while papers are marked out of 50, 75 or 100. Looking
     * up 45 against an 80-100 band would grade a 45-out-of-50 paper as F.
     */
    public function gradeForMarks(float $obtained, float $fullMarks): ?GradeScaleItem
    {
        if ($fullMarks <= 0) {
            return null;
        }

        return $this->scale->gradeFor(($obtained / $fullMarks) * 100);
    }

    public function gradeForGpa(float $gpa): ?GradeScaleItem
    {
        return $this->scale->gradeForGpa($gpa);
    }

    /**
     * Build a scored subject from raw marks, resolving grade and GPA from the scale.
     *
     * Absence short-circuits to the failing band. An absent student has not scored
     * zero - they have no score - but for grading purposes the board treats absence as
     * a fail, and the distinction is preserved on the Mark row for statistics.
     */
    public function score(
        int $classSubjectId,
        string $subjectName,
        float $fullMarks,
        float $obtainedMarks,
        bool $isOptional = false,
        bool $isAbsent = false,
        bool $isCountable = true,
        bool $componentFailure = false,
        ?string $subjectNameBn = null,
        ?float $cqMarks = null,
        ?float $mcqMarks = null,
        ?float $practicalMarks = null,
    ): SubjectScore {
        $failingItem = $this->scale->failingItem();

        if ($isAbsent) {
            return new SubjectScore(
                classSubjectId: $classSubjectId,
                subjectName: $subjectName,
                fullMarks: $fullMarks,
                obtainedMarks: 0.0,
                gpa: $failingItem !== null ? (float) $failingItem->gpa : 0.0,
                grade: $failingItem?->name,
                isFailing: true,
                isOptional: $isOptional,
                isAbsent: true,
                isCountable: $isCountable,
                subjectNameBn: $subjectNameBn,
            );
        }

        $item = $this->gradeForMarks($obtainedMarks, $fullMarks);

        // A component failure - passing the paper overall but failing the creative
        // questions or the practical - is a fail on the paper. The band lookup cannot
        // see components, so the caller tells us and we override downward. We never
        // override upward.
        $isFailing = $componentFailure || $item === null || $item->is_failing;

        return new SubjectScore(
            classSubjectId: $classSubjectId,
            subjectName: $subjectName,
            fullMarks: $fullMarks,
            obtainedMarks: $obtainedMarks,
            gpa: $isFailing && $failingItem !== null
                ? (float) $failingItem->gpa
                : (float) ($item?->gpa ?? 0),
            grade: $isFailing ? ($failingItem?->name ?? $item?->name) : $item?->name,
            isFailing: $isFailing,
            isOptional: $isOptional,
            isAbsent: false,
            isCountable: $isCountable,
            subjectNameBn: $subjectNameBn,
            cqMarks: $cqMarks,
            mcqMarks: $mcqMarks,
            practicalMarks: $practicalMarks,
        );
    }

    /**
     * Apply the four rules to a student's subject scores.
     *
     * @param  array<int, SubjectScore>  $scores
     */
    public function calculate(array $scores): GpaResult
    {
        if ($scores === []) {
            return GpaResult::empty();
        }

        $compulsory = array_values(array_filter(
            $scores,
            static fn (SubjectScore $score) => $score->countsTowardResult(),
        ));

        $optional = array_values(array_filter(
            $scores,
            static fn (SubjectScore $score) => $score->isOptional && $score->isCountable,
        ));

        // Totals include every subject that appears on the marksheet, optional
        // included, because the printed "total marks" line is what the student was
        // actually examined on. Only the GPA arithmetic treats the two differently.
        $totalFull = 0.0;
        $totalObtained = 0.0;

        foreach ($scores as $score) {
            $totalFull += $score->fullMarks;
            $totalObtained += $score->obtainedMarks;
        }

        $failed = array_values(array_filter(
            $compulsory,
            static fn (SubjectScore $score) => $score->isFailing,
        ));

        $average = $totalFull > 0
            ? round(($totalObtained / $totalFull) * 100, 2)
            : 0.0;

        // Rule 3. One F anywhere compulsory and the result is F, no matter what else
        // the student scored. Reported before any averaging so a strong student with
        // one failure cannot surface a passing GPA.
        if ($failed !== []) {
            $failingItem = $this->scale->failingItem();

            return new GpaResult(
                gpa: 0.0,
                grade: $failingItem?->name ?? 'F',
                isFailed: true,
                failedSubjectCount: count($failed),
                appearedSubjectCount: count($compulsory),
                totalFullMarks: round($totalFull, 2),
                totalObtainedMarks: round($totalObtained, 2),
                averageMarks: $average,
                subjects: $scores,
            );
        }

        if ($compulsory === []) {
            return GpaResult::empty();
        }

        // Rule 1.
        $points = 0.0;

        foreach ($compulsory as $score) {
            $points += $score->gpa;
        }

        // Rule 2. Floored at zero, so a weak 4th subject is neutral rather than a
        // penalty, and excluded from the divisor so it can only lift the average.
        $deduction = (float) $this->scale->optional_subject_deduction;

        foreach ($optional as $score) {
            $points += max(0.0, $score->gpa - $deduction);
        }

        $gpa = $this->truncate($points / count($compulsory));
        $gpa = min($gpa, (float) $this->scale->max_gpa);

        return new GpaResult(
            gpa: $gpa,
            grade: $this->gradeForGpa($gpa)?->name,
            isFailed: false,
            failedSubjectCount: 0,
            appearedSubjectCount: count($compulsory),
            totalFullMarks: round($totalFull, 2),
            totalObtainedMarks: round($totalObtained, 2),
            averageMarks: $average,
            subjects: $scores,
        );
    }

    /**
     * Blend several exams into one result, weighted by each exam's `weight`.
     *
     * Used for the common annual formula - say 30% half-yearly plus 70% annual. The
     * blend happens on MARKS, not on GPA, which matters: averaging two GPAs gives a
     * different and less defensible answer than grading the weighted mark, because
     * grade bands are not linear. A student on 79 and 81 should not be graded A when
     * the weighted mark is 80.
     *
     * @param  array<int, array{weight: float, scores: array<int, SubjectScore>}>  $terms
     */
    public function combine(array $terms): GpaResult
    {
        $totalWeight = array_sum(array_column($terms, 'weight'));

        if ($totalWeight <= 0) {
            return GpaResult::empty();
        }

        /** @var array<int, array{score: SubjectScore, full: float, obtained: float}> $merged */
        $merged = [];

        foreach ($terms as $term) {
            $share = $term['weight'] / $totalWeight;

            foreach ($term['scores'] as $score) {
                $key = $score->classSubjectId;

                $merged[$key] ??= ['score' => $score, 'full' => 0.0, 'obtained' => 0.0];
                $merged[$key]['full'] += $score->fullMarks * $share;
                $merged[$key]['obtained'] += $score->obtainedMarks * $share;
            }
        }

        $scores = [];

        foreach ($merged as $entry) {
            /** @var SubjectScore $original */
            $original = $entry['score'];

            $scores[] = $this->score(
                classSubjectId: $original->classSubjectId,
                subjectName: $original->subjectName,
                fullMarks: round($entry['full'], 2),
                obtainedMarks: round($entry['obtained'], 2),
                isOptional: $original->isOptional,
                isCountable: $original->isCountable,
                subjectNameBn: $original->subjectNameBn,
            );
        }

        return $this->calculate($scores);
    }

    private function truncate(float $value): float
    {
        $factor = 10 ** self::SCALE;

        return floor($value * $factor) / $factor;
    }
}
