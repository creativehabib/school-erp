<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Exam\ExamResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Marksheet, built from the stored result snapshot rather than from live marks.
 *
 * This is the whole reason exam_results.subject_snapshot exists. A marksheet handed to a
 * parent is a published statement. Rebuilding it from the marks table would mean a
 * corrected mark, a revised grade scale, or a subject renamed next year silently changes
 * a document already in circulation - and the version the school can produce would no
 * longer match the version the parent is holding.
 *
 * So the snapshot is authoritative, and live marks are only consulted when a result
 * predates snapshotting.
 */
final class MarksheetPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [ExamResult::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var ExamResult $result */
        $result = $subject;

        $result->loadMissing([
            'exam.academicSession',
            'exam.gradeScale.items',
            'enrollment.student',
            'enrollment.schoolClass',
            'enrollment.section',
            'enrollment.shift',
        ]);

        $enrollment = $result->enrollment;
        $student = $enrollment?->student;
        $subjects = $this->subjects($result);

        return [
            'school' => $this->school(),
            'exam' => [
                'name' => $result->exam?->name,
                'name_bn' => $result->exam?->name_bn,
                'session' => $result->exam?->academicSession?->name,
                'period' => $result->exam?->periodLabel(),
                'published_at' => $this->date($result->published_at),
            ],
            'student' => [
                'name_en' => $student?->name_en,
                'name_bn' => $student?->name_bn,
                'admission_no' => $student?->admission_no,
                'father_name' => $student?->father_name,
                'mother_name' => $student?->mother_name,
                'date_of_birth' => $this->date($student?->date_of_birth),
                'photo' => $this->imagePath($student?->photo_path),
            ],
            'enrollment' => [
                'class' => $enrollment?->schoolClass?->name,
                'class_bn' => $enrollment?->schoolClass?->name_bn,
                'section' => $enrollment?->section?->name,
                'shift' => $enrollment?->shift?->name,
                'group' => $enrollment?->group,
                'class_roll' => $enrollment?->class_roll,
            ],
            'subjects' => $subjects,
            'result' => [
                'total_full_marks' => (float) $result->total_full_marks,
                'total_obtained_marks' => (float) $result->total_obtained_marks,
                'average_marks' => (float) $result->average_marks,
                'gpa' => $result->gpaLabel(),
                'grade' => $result->grade,
                'is_failed' => (bool) $result->is_failed,
                'status' => $result->resultLabel(),
                'failed_subject_count' => (int) $result->failed_subject_count,
                'appeared_subject_count' => (int) $result->appeared_subject_count,
                'class_position' => $result->class_position,
                'section_position' => $result->section_position,
                'attendance' => $result->attendanceLabel(),
                'present_days' => $result->present_days,
                'working_days' => $result->working_days,
                'remarks' => $context['remarks'] ?? $result->remarks,
            ],
            // Printed on the reverse of every board-style marksheet, and parents do read
            // it. Taken from the exam's own scale so an old marksheet reprints with the
            // bands that were in force when it was issued.
            'grade_scale' => $this->gradeScale($result),
            'qr' => $enrollment !== null
                ? $this->qrFor($enrollment, 'marksheet:'.$result->exam_id, $enrollment->academic_session_id)
                : ['token' => null, 'url' => null, 'src' => null],
            'issued' => $this->issuance(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subjects(ExamResult $result): array
    {
        $snapshot = $result->subject_snapshot;

        if (is_array($snapshot) && $snapshot !== []) {
            return array_values(array_map(static fn (array $row) => [
                'subject' => $row['subject'] ?? null,
                'subject_bn' => $row['subject_bn'] ?? null,
                'full_marks' => (float) ($row['full_marks'] ?? 0),
                'obtained_marks' => (float) ($row['obtained_marks'] ?? 0),
                'cq' => $row['cq'] ?? null,
                'mcq' => $row['mcq'] ?? null,
                'practical' => $row['practical'] ?? null,
                'grade' => $row['grade'] ?? null,
                'gpa' => (float) ($row['gpa'] ?? 0),
                'is_failing' => (bool) ($row['is_failing'] ?? false),
                'is_optional' => (bool) ($row['is_optional'] ?? false),
                'is_absent' => (bool) ($row['is_absent'] ?? false),
            ], $snapshot));
        }

        // Legacy fallback for results processed before snapshots were stored.
        return $result->enrollment
            ?->marks()
            ->forExam((int) $result->exam_id)
            ->with('examSubject.classSubject.subject')
            ->get()
            ->map(fn ($mark) => [
                'subject' => $mark->examSubject?->subjectName(),
                'subject_bn' => $mark->examSubject?->classSubject?->subject?->name_bn,
                'full_marks' => (float) ($mark->examSubject?->full_marks ?? 0),
                'obtained_marks' => (float) $mark->obtained_marks,
                'cq' => $mark->cq_marks,
                'mcq' => $mark->mcq_marks,
                'practical' => $mark->practical_marks,
                'grade' => $mark->grade,
                'gpa' => (float) $mark->gpa,
                'is_failing' => (bool) $mark->is_failing,
                'is_optional' => false,
                'is_absent' => (bool) $mark->is_absent,
            ])
            ->values()
            ->all() ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    private function gradeScale(ExamResult $result): array
    {
        return $result->exam?->gradeScale?->items
            ->sortByDesc('min_marks')
            ->map(static fn ($item) => [
                'name' => $item->name,
                'min_marks' => (float) $item->min_marks,
                'max_marks' => (float) $item->max_marks,
                'gpa' => (float) $item->gpa,
                'is_failing' => (bool) $item->is_failing,
            ])
            ->values()
            ->all() ?? [];
    }
}
