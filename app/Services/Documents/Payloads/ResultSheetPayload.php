<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Exam\Exam;
use App\Models\Exam\ExamResult;
use App\Models\Exam\ExamSubject;
use Illuminate\Database\Eloquent\Model;

/**
 * The tabulation sheet: one row per student, one column per subject, for a whole section.
 *
 * This is the document the head teacher and the exam committee actually work from, and it
 * is landscape for a reason - a Class 9 Science section is 11 papers wide, which does not
 * fit portrait at a legible size.
 *
 * Subject columns are taken from the exam's papers for the class, and each student's marks
 * are read from their stored result snapshot. A student who elected a different 4th
 * subject shows a blank in the column they did not sit, rather than a zero: a zero on a
 * tabulation sheet reads as "failed that paper" to anyone scanning the page, and would
 * have the committee investigating a subject the student never took.
 */
final class ResultSheetPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Exam::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Exam $exam */
        $exam = $subject;

        $exam->loadMissing(['academicSession', 'gradeScale.items']);

        $schoolClassId = $context['school_class_id'] ?? null;
        $sectionId = $context['section_id'] ?? null;

        $papers = $exam->examSubjects()
            ->with('classSubject.subject')
            ->when(
                $schoolClassId !== null,
                fn ($q) => $q->whereHas('classSubject', fn ($sub) => $sub->where('school_class_id', $schoolClassId)),
            )
            ->ordered()
            ->get();

        $columns = $papers->map(fn (ExamSubject $paper) => [
            'class_subject_id' => (int) $paper->class_subject_id,
            'subject' => $paper->subjectName(),
            'subject_bn' => $paper->classSubject?->subject?->name_bn,
            'short' => $this->shorten($paper->subjectName()),
            'full_marks' => (float) $paper->full_marks,
        ])->values()->all();

        $results = ExamResult::query()
            ->forExam($exam->getKey())
            ->with([
                'enrollment.student:id,name_en,name_bn,admission_no',
                'enrollment.schoolClass',
                'enrollment.section',
            ])
            ->whereHas('enrollment', function ($q) use ($schoolClassId, $sectionId): void {
                if ($schoolClassId !== null) {
                    $q->where('school_class_id', $schoolClassId);
                }

                if ($sectionId !== null) {
                    $q->where('section_id', $sectionId);
                }
            })
            ->orderByRaw('is_failed asc')
            ->orderByDesc('gpa')
            ->orderByDesc('total_obtained_marks')
            ->get();

        $rows = $results->map(function (ExamResult $result) use ($columns) {
            $byClassSubject = [];

            foreach ((array) ($result->subject_snapshot ?? []) as $row) {
                if (isset($row['class_subject_id'])) {
                    $byClassSubject[(int) $row['class_subject_id']] = $row;
                }
            }

            $cells = [];

            foreach ($columns as $column) {
                $row = $byClassSubject[$column['class_subject_id']] ?? null;

                $cells[] = [
                    'subject' => $column['short'],
                    // Null, not zero, when the student did not sit this paper.
                    'obtained' => $row !== null ? (float) ($row['obtained_marks'] ?? 0) : null,
                    'grade' => $row['grade'] ?? null,
                    'gpa' => $row !== null ? (float) ($row['gpa'] ?? 0) : null,
                    'is_failing' => (bool) ($row['is_failing'] ?? false),
                    'is_absent' => (bool) ($row['is_absent'] ?? false),
                    'is_optional' => (bool) ($row['is_optional'] ?? false),
                    'sat' => $row !== null,
                ];
            }

            return [
                'class_roll' => $result->enrollment?->class_roll,
                'admission_no' => $result->enrollment?->student?->admission_no,
                'name_en' => $result->enrollment?->student?->name_en,
                'name_bn' => $result->enrollment?->student?->name_bn,
                'class' => $result->enrollment?->schoolClass?->name,
                'section' => $result->enrollment?->section?->name,
                'group' => $result->enrollment?->group,
                'cells' => $cells,
                'total' => (float) $result->total_obtained_marks,
                'full_marks' => (float) $result->total_full_marks,
                'average' => (float) $result->average_marks,
                'gpa' => $result->gpaLabel(),
                'grade' => $result->grade,
                'is_failed' => (bool) $result->is_failed,
                'status' => $result->resultLabel(),
                'position' => $result->section_position ?? $result->class_position,
                'failed_subject_count' => (int) $result->failed_subject_count,
            ];
        })->values()->all();

        return [
            'school' => $this->school(),
            'exam' => [
                'name' => $exam->name,
                'name_bn' => $exam->name_bn,
                'session' => $exam->academicSession?->name,
                'period' => $exam->periodLabel(),
                'published_at' => $this->date($exam->result_published_at),
            ],
            'scope' => [
                'class' => $results->first()?->enrollment?->schoolClass?->name,
                'section' => $sectionId !== null ? $results->first()?->enrollment?->section?->name : null,
            ],
            'columns' => $columns,
            'column_count' => count($columns),
            'rows' => $rows,
            'summary' => $this->summary($results),
            'issued' => $this->issuance(),
        ];
    }

    /**
     * The block the committee reads first: how many passed, and the grade spread.
     *
     * @param  \Illuminate\Support\Collection<int, ExamResult>  $results
     * @return array<string, mixed>
     */
    private function summary($results): array
    {
        $total = $results->count();
        $passed = $results->where('is_failed', false)->count();

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0.0,
            'highest_gpa' => $total > 0 ? number_format((float) $results->max('gpa'), 2) : '0.00',
            'average_gpa' => $total > 0 ? number_format((float) $results->avg('gpa'), 2) : '0.00',
            'grade_counts' => $results
                ->groupBy('grade')
                ->map->count()
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * Column headers must fit. "Information & Communication Technology" in a 9-subject
     * table pushes the sheet to three pages; "ICT" fits.
     */
    private function shorten(string $name): string
    {
        $known = [
            'Information and Communication Technology' => 'ICT',
            'Information & Communication Technology' => 'ICT',
            'Bangladesh and Global Studies' => 'BGS',
            'Religion and Moral Education' => 'Religion',
            'Physical Education and Health' => 'PE',
            'Career Education' => 'Career',
            'Agriculture Studies' => 'Agri',
            'Higher Mathematics' => 'H. Math',
            'General Science' => 'Science',
        ];

        if (isset($known[$name])) {
            return $known[$name];
        }

        return \Illuminate\Support\Str::limit($name, 12, '.');
    }
}
