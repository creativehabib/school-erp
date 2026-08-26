<?php

declare(strict_types=1);

namespace App\Actions\Exam;

use App\Models\Exam\ExamSubject;
use App\Models\Exam\Mark;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Save a grid of marks for one paper.
 *
 * The entry screen submits every row it displayed, including untouched ones, so this
 * has to be an upsert rather than an insert. A teacher who fixes one number and saves
 * must not create duplicate rows or wipe the other forty.
 *
 * Validation happens per row against the paper's own ceilings before anything is
 * written, and the whole grid is one transaction. Half-saving a grid is worse than
 * rejecting it: the teacher cannot tell which rows landed, and the result processor
 * will happily grade the partial set.
 */
final class SaveMarks
{
    /**
     * @param  array<int, array{cq?: float|string|null, mcq?: float|string|null, practical?: float|string|null, total?: float|string|null, is_absent?: bool}>  $rows
     *                                                                                                                                                        Keyed by student_enrollment_id.
     * @return array{saved: int}
     */
    public function handle(ExamSubject $examSubject, array $rows, ?User $enteredBy = null): array
    {
        $examSubject->loadMissing('exam', 'classSubject.subject');

        if ($examSubject->exam?->isLocked()) {
            throw new RuntimeException(
                'Results for this exam are published and locked. Unlock the exam to edit marks.'
            );
        }

        $components = $examSubject->activeComponents();
        $userId = $enteredBy?->getKey() ?? auth()->id();
        $payload = [];

        foreach ($rows as $enrollmentId => $row) {
            $isAbsent = (bool) ($row['is_absent'] ?? false);

            $cq = $this->numeric($row['cq'] ?? null);
            $mcq = $this->numeric($row['mcq'] ?? null);
            $practical = $this->numeric($row['practical'] ?? null);

            // An absent student has no marks at all. Storing the zeroes a browser
            // helpfully submitted would make them indistinguishable from a student who
            // sat the paper and scored nothing.
            if ($isAbsent) {
                $cq = $mcq = $practical = null;
                $total = 0.0;
            } else {
                $total = $components === []
                    ? (float) ($this->numeric($row['total'] ?? null) ?? 0)
                    : (float) (($cq ?? 0) + ($mcq ?? 0) + ($practical ?? 0));

                $examSubject->assertMarksValid(
                    ['cq' => $cq, 'mcq' => $mcq, 'practical' => $practical],
                    $total,
                );
            }

            $payload[] = [
                'exam_subject_id' => $examSubject->getKey(),
                'student_enrollment_id' => (int) $enrollmentId,
                'cq_marks' => $cq,
                'mcq_marks' => $mcq,
                'practical_marks' => $practical,
                'obtained_marks' => round($total, 2),
                'is_absent' => $isAbsent,
                'entered_by' => $userId,
                // Grade and GPA are deliberately left alone here. They are a snapshot
                // written by ProcessExamResult; setting them at entry time would show a
                // grade before the result has been processed and before the component
                // pass rules have been applied.
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload === []) {
            return ['saved' => 0];
        }

        DB::transaction(function () use ($payload): void {
            foreach (array_chunk($payload, 500) as $chunk) {
                Mark::query()->upsert(
                    $chunk,
                    ['exam_subject_id', 'student_enrollment_id'],
                    ['cq_marks', 'mcq_marks', 'practical_marks', 'obtained_marks',
                        'is_absent', 'entered_by', 'updated_at'],
                );
            }
        });

        return ['saved' => count($payload)];
    }

    /**
     * Treat an empty string as "not entered", not as zero.
     *
     * A blank cell in the grid means the teacher has not marked that script yet. Casting
     * it to 0.0 silently records a fail.
     */
    private function numeric(float|int|string|null $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
