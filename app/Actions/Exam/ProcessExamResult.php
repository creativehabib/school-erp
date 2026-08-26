<?php

declare(strict_types=1);

namespace App\Actions\Exam;

use App\Models\Academic\StudentEnrollment;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamResult;
use App\Models\Exam\ExamSubject;
use App\Models\Exam\Mark;
use App\Services\Exam\GpaCalculator;
use App\Support\Exam\SubjectScore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turn raw marks into published results.
 *
 * Idempotent and re-runnable: a school will process, spot a wrong mark, fix it and
 * process again, several times, before publishing. Each run rebuilds the ExamResult
 * rows and the per-subject grade snapshots on the Mark rows from scratch.
 *
 * Two guards worth calling out.
 *
 * A locked exam cannot be reprocessed. Once results are published, reprocessing could
 * silently change a GPA that a parent has already been shown, so the lock has to be
 * lifted explicitly and visibly rather than bypassed by a background job.
 *
 * A student missing marks in any applicable subject is SKIPPED rather than graded on
 * a partial set. Grading a student on the three subjects a teacher happened to have
 * entered produces a plausible-looking GPA that is completely wrong, and it will be
 * printed before anyone notices. Refusing is the safer failure.
 */
final class ProcessExamResult
{
    /**
     * @return array{processed: int, skipped: int, incomplete: array<int, string>}
     */
    public function handle(
        Exam $exam,
        ?int $schoolClassId = null,
        ?int $sectionId = null,
        bool $publish = false,
        bool $treatMissingAsAbsent = false,
    ): array {
        if ($exam->isLocked()) {
            throw new RuntimeException(
                "Exam [{$exam->name}] is locked. Unlock it before reprocessing results."
            );
        }

        $exam->loadMissing('gradeScale.items', 'academicSession');

        if ($exam->gradeScale === null || $exam->gradeScale->items->isEmpty()) {
            throw new RuntimeException(
                "Exam [{$exam->name}] has no grade scale bands configured."
            );
        }

        $calculator = GpaCalculator::for($exam->gradeScale);

        /** @var EloquentCollection<int, ExamSubject> $examSubjects */
        $examSubjects = $exam->examSubjects()
            ->with('classSubject.subject')
            ->get();

        if ($examSubjects->isEmpty()) {
            throw new RuntimeException("Exam [{$exam->name}] has no papers configured.");
        }

        // Papers grouped by the class they belong to, so each student is only measured
        // against their own class's subjects.
        $subjectsByClass = $examSubjects->groupBy(
            fn (ExamSubject $subject) => $subject->classSubject?->school_class_id
        );

        $processed = 0;
        $skipped = 0;
        $incomplete = [];

        $query = StudentEnrollment::query()
            ->where('academic_session_id', $exam->academic_session_id)
            ->when($schoolClassId !== null, fn ($q) => $q->where('school_class_id', $schoolClassId))
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId))
            ->whereIn('school_class_id', $subjectsByClass->keys()->filter()->all())
            ->with(['student:id,name_en,name_bn,admission_no', 'studentSubjects']);

        $query->chunkById(200, function (EloquentCollection $enrollments) use (
            $exam, $calculator, $subjectsByClass, &$processed, &$skipped, &$incomplete, $treatMissingAsAbsent
        ): void {
            $marks = $this->marksFor($exam, $enrollments->modelKeys());
            $attendance = $this->attendanceFor($exam, $enrollments->modelKeys());

            foreach ($enrollments as $enrollment) {
                /** @var Collection<int, ExamSubject> $applicable */
                $applicable = $this->applicableSubjects(
                    $enrollment,
                    $subjectsByClass->get($enrollment->school_class_id, collect()),
                );

                if ($applicable->isEmpty()) {
                    $skipped++;

                    continue;
                }

                $studentMarks = $marks->get($enrollment->getKey(), collect());
                $optionalIds = $this->optionalClassSubjectIds($enrollment);

                $scores = [];
                $missing = [];

                foreach ($applicable as $examSubject) {
                    /** @var Mark|null $mark */
                    $mark = $studentMarks->get($examSubject->getKey());

                    if ($mark === null && ! $treatMissingAsAbsent) {
                        $missing[] = $examSubject->subjectName();

                        continue;
                    }

                    $scores[] = $this->scoreFor($calculator, $examSubject, $mark, $optionalIds);
                }

                if ($missing !== []) {
                    $skipped++;
                    $incomplete[] = sprintf(
                        '%s (%s): missing %s',
                        $enrollment->student?->name_en ?? "Enrollment #{$enrollment->getKey()}",
                        $enrollment->class_roll ?? '-',
                        implode(', ', $missing),
                    );

                    continue;
                }

                $result = $calculator->calculate($scores);
                $days = $attendance[$enrollment->getKey()] ?? null;

                DB::transaction(function () use ($exam, $enrollment, $result, $days, $studentMarks, $scores): void {
                    ExamResult::updateOrCreate(
                        [
                            'exam_id' => $exam->getKey(),
                            'student_enrollment_id' => $enrollment->getKey(),
                        ],
                        [
                            'total_full_marks' => $result->totalFullMarks,
                            'total_obtained_marks' => $result->totalObtainedMarks,
                            'average_marks' => $result->averageMarks,
                            'gpa' => $result->gpa,
                            'grade' => $result->grade,
                            'is_failed' => $result->isFailed,
                            'failed_subject_count' => $result->failedSubjectCount,
                            'appeared_subject_count' => $result->appearedSubjectCount,
                            'present_days' => $days['present'] ?? null,
                            'working_days' => $days['working'] ?? null,
                            'subject_snapshot' => $result->subjectSnapshot(),
                            'processed_at' => now(),
                            // Positions are assigned in a second pass; null them here so a
                            // reprocess never leaves last run's rank on a changed result.
                            'class_position' => null,
                            'section_position' => null,
                        ],
                    );

                    $this->snapshotMarkGrades($studentMarks, $scores);
                });

                $processed++;
            }
        });

        $this->assignPositions($exam);

        if ($publish) {
            $this->publish($exam);
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'incomplete' => $incomplete,
        ];
    }

    /**
     * Marks for a chunk of enrollments, keyed enrollment => exam_subject => Mark.
     *
     * One query per chunk rather than one per student: a 1,200-student exam with eight
     * papers is 9,600 mark rows, and per-student loading turns processing into a
     * multi-minute job that times out behind a web request.
     *
     * @param  array<int, int|string>  $enrollmentIds
     * @return Collection<int, Collection<int, Mark>>
     */
    private function marksFor(Exam $exam, array $enrollmentIds): Collection
    {
        return Mark::query()
            ->forExam($exam->getKey())
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->with('examSubject:id,class_subject_id')
            ->get()
            ->groupBy('student_enrollment_id')
            ->map(fn (EloquentCollection $rows) => $rows->keyBy('exam_subject_id'));
    }

    /**
     * Present and working days per enrollment across the session.
     *
     * Grouped in a single query for the same reason as marks. Half days count as half
     * present, matching how schools report them on a marksheet.
     *
     * @param  array<int, int|string>  $enrollmentIds
     * @return array<int, array{present: int, working: int}>
     */
    private function attendanceFor(Exam $exam, array $enrollmentIds): array
    {
        $session = $exam->academicSession;

        if ($session === null) {
            return [];
        }

        $rows = DB::table('student_attendances')
            ->select('student_enrollment_id', 'status', DB::raw('COUNT(*) as total'))
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [
                $session->starts_on->toDateString(),
                $session->ends_on->toDateString(),
            ])
            ->groupBy('student_enrollment_id', 'status')
            ->get();

        $summary = [];

        foreach ($rows as $row) {
            $id = (int) $row->student_enrollment_id;
            $summary[$id] ??= ['present' => 0.0, 'working' => 0];

            $summary[$id]['working'] += (int) $row->total;

            $summary[$id]['present'] += match ($row->status) {
                'present', 'late' => (int) $row->total,
                'half_day' => (int) $row->total * 0.5,
                default => 0,
            };
        }

        return array_map(
            static fn (array $entry) => [
                'present' => (int) round($entry['present']),
                'working' => $entry['working'],
            ],
            $summary,
        );
    }

    /**
     * The papers this particular student sits.
     *
     * Explicit student_subjects rows win when present, because a 4th-subject election
     * is a per-student fact. Otherwise fall back to the class curriculum filtered by
     * the student's stream, which is what a school that does not track elections
     * expects.
     *
     * @param  Collection<int, ExamSubject>  $classSubjects
     * @return Collection<int, ExamSubject>
     */
    private function applicableSubjects(StudentEnrollment $enrollment, Collection $classSubjects): Collection
    {
        $elected = $enrollment->studentSubjects->pluck('class_subject_id');

        if ($elected->isNotEmpty()) {
            return $classSubjects->filter(
                fn (ExamSubject $subject) => $elected->contains($subject->class_subject_id)
            )->values();
        }

        return $classSubjects->filter(function (ExamSubject $subject) use ($enrollment) {
            $group = $subject->classSubject?->group;

            return $group === null || $group === $enrollment->group;
        })->values();
    }

    /** @return array<int, int> */
    private function optionalClassSubjectIds(StudentEnrollment $enrollment): array
    {
        return $enrollment->studentSubjects
            ->where('is_optional', true)
            ->pluck('class_subject_id')
            ->all();
    }

    /**
     * @param  array<int, int>  $optionalIds
     */
    private function scoreFor(
        GpaCalculator $calculator,
        ExamSubject $examSubject,
        ?Mark $mark,
        array $optionalIds,
    ): SubjectScore {
        $classSubject = $examSubject->classSubject;

        // No mark row and the caller asked for lenient processing: treat as absent.
        if ($mark === null) {
            return $calculator->score(
                classSubjectId: (int) $examSubject->class_subject_id,
                subjectName: $examSubject->subjectName(),
                fullMarks: (float) $examSubject->full_marks,
                obtainedMarks: 0.0,
                isOptional: in_array((int) $examSubject->class_subject_id, $optionalIds, true),
                isAbsent: true,
                isCountable: $examSubject->is_countable,
                subjectNameBn: $classSubject?->subject?->name_bn,
            );
        }

        return $calculator->score(
            classSubjectId: (int) $examSubject->class_subject_id,
            subjectName: $examSubject->subjectName(),
            fullMarks: (float) $examSubject->full_marks,
            obtainedMarks: (float) $mark->obtained_marks,
            isOptional: in_array((int) $examSubject->class_subject_id, $optionalIds, true),
            isAbsent: $mark->is_absent,
            isCountable: $examSubject->is_countable,
            componentFailure: ! $examSubject->isPassing($mark),
            subjectNameBn: $classSubject?->subject?->name_bn,
            cqMarks: $mark->cq_marks !== null ? (float) $mark->cq_marks : null,
            mcqMarks: $mark->mcq_marks !== null ? (float) $mark->mcq_marks : null,
            practicalMarks: $mark->practical_marks !== null ? (float) $mark->practical_marks : null,
        );
    }

    /**
     * Write the resolved grade back onto each Mark row.
     *
     * The Mark carries its own grade snapshot so the tabulation sheet and subject-wise
     * statistics do not have to re-derive it, and so a later change to the grade scale
     * cannot silently alter a marksheet that has already been printed.
     *
     * @param  Collection<int, Mark>  $marks
     * @param  array<int, SubjectScore>  $scores
     */
    private function snapshotMarkGrades(Collection $marks, array $scores): void
    {
        $byClassSubject = [];

        foreach ($scores as $score) {
            $byClassSubject[$score->classSubjectId] = $score;
        }

        foreach ($marks as $mark) {
            $score = $byClassSubject[(int) ($mark->examSubject?->class_subject_id ?? 0)] ?? null;

            if ($score === null) {
                continue;
            }

            $mark->forceFill([
                'grade' => $score->grade,
                'gpa' => $score->gpa,
                'is_failing' => $score->isFailing,
            ])->save();
        }
    }

    /**
     * Assign merit positions, class-wide and section-wide.
     *
     * Standard competition ranking: equal GPA and equal total marks share a position,
     * and the next distinct score skips ahead. Failed results are left unranked -
     * boards do not place failing students in the merit list, and printing "42nd" on a
     * failed marksheet reads as a taunt.
     */
    private function assignPositions(Exam $exam): void
    {
        $results = ExamResult::query()
            ->forExam($exam->getKey())
            ->passed()
            ->join('student_enrollments', 'student_enrollments.id', '=', 'exam_results.student_enrollment_id')
            ->select([
                'exam_results.id',
                'exam_results.gpa',
                'exam_results.total_obtained_marks',
                'student_enrollments.school_class_id',
                'student_enrollments.section_id',
            ])
            ->orderByDesc('exam_results.gpa')
            ->orderByDesc('exam_results.total_obtained_marks')
            ->get();

        $classPositions = $this->rank($results->groupBy('school_class_id'));
        $sectionPositions = $this->rank($results->groupBy('section_id'));

        foreach ($results as $row) {
            ExamResult::query()
                ->whereKey($row->id)
                ->update([
                    'class_position' => $classPositions[$row->id] ?? null,
                    'section_position' => $sectionPositions[$row->id] ?? null,
                ]);
        }
    }

    /**
     * @param  Collection<int|string, Collection<int, object>>  $groups
     * @return array<int, int>
     */
    private function rank(Collection $groups): array
    {
        $positions = [];

        foreach ($groups as $key => $rows) {
            if ($key === '' || $key === null) {
                continue;
            }

            $position = 0;
            $index = 0;
            $previous = null;

            foreach ($rows as $row) {
                $index++;
                $signature = $row->gpa.'|'.$row->total_obtained_marks;

                if ($signature !== $previous) {
                    $position = $index;
                    $previous = $signature;
                }

                $positions[$row->id] = $position;
            }
        }

        return $positions;
    }

    /**
     * Publish, and lock.
     *
     * Publishing without locking would leave the door open to a teacher editing a mark
     * on a result a parent has already seen, so the two happen together.
     */
    private function publish(Exam $exam): void
    {
        DB::transaction(function () use ($exam): void {
            ExamResult::query()
                ->forExam($exam->getKey())
                ->whereNull('published_at')
                ->update(['published_at' => now()]);

            $exam->forceFill([
                'result_published_at' => now(),
                'publish_marksheet' => true,
                'is_locked' => true,
            ])->save();
        });
    }
}
