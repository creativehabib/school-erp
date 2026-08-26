<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Academic\StudentEnrollment;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamSubject;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Admit card: who the student is, and the paper-by-paper timetable they need.
 *
 * Built from the ENROLLMENT rather than the student, because an admit card is a statement
 * about a placement - "Rafiqul, Class 9, Section A, Roll 14, Science" - and the student
 * record alone cannot say which of those applied during the exam being sat.
 *
 * The subject list respects the student's own election, so a Science student's card shows
 * their 4th subject and not the whole class curriculum. Printing a paper the student will
 * not sit is worse than useless: it sends them to the wrong room on the wrong morning.
 *
 * A due-payment flag is exposed rather than enforced. Withholding admit cards over unpaid
 * fees is a decision for the school office, not for a payload builder - but the office
 * needs to see the number at the moment of printing.
 */
final class AdmitCardPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [StudentEnrollment::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var StudentEnrollment $enrollment */
        $enrollment = $subject;

        $exam = $context['exam'] ?? null;

        if (! $exam instanceof Exam) {
            throw new InvalidArgumentException(
                'An admit card needs an Exam in the context: build($enrollment, ["exam" => $exam]).'
            );
        }

        $enrollment->loadMissing([
            'student', 'schoolClass', 'section', 'shift', 'academicSession', 'studentSubjects',
        ]);

        $student = $enrollment->student;
        $papers = $this->papers($exam, $enrollment);

        $qr = $this->qrFor($enrollment, 'admit_card:'.$exam->getKey(), $enrollment->academic_session_id);

        return [
            'school' => $this->school(),
            'exam' => [
                'id' => $exam->getKey(),
                'name' => $exam->name,
                'name_bn' => $exam->name_bn,
                'code' => $exam->code,
                'type' => $exam->type instanceof \App\Enums\ExamType ? $exam->type->label() : (string) $exam->type,
                'session' => $exam->academicSession?->name ?? $enrollment->academicSession?->name,
                'starts_on' => $this->date($exam->starts_on),
                'ends_on' => $this->date($exam->ends_on),
                'period' => $exam->periodLabel(),
            ],
            'student' => [
                'id' => $student?->getKey(),
                'name_en' => $student?->name_en,
                'name_bn' => $student?->name_bn,
                'admission_no' => $student?->admission_no,
                'father_name' => $student?->father_name,
                'mother_name' => $student?->mother_name,
                'date_of_birth' => $this->date($student?->date_of_birth),
                'photo' => $this->imagePath($student?->photo_path),
                'board_roll' => $student?->board_roll,
                'board_registration_no' => $student?->board_registration_no,
            ],
            'enrollment' => [
                'id' => $enrollment->getKey(),
                'class' => $enrollment->schoolClass?->name,
                'class_bn' => $enrollment->schoolClass?->name_bn,
                'section' => $enrollment->section?->name,
                'shift' => $enrollment->shift?->name,
                'group' => $enrollment->group,
                'class_roll' => $enrollment->class_roll,
                'room_no' => $context['room_no'] ?? $enrollment->section?->room_no,
                'placement' => $enrollment->placementLabel(),
            ],
            'papers' => $papers,
            'paper_count' => count($papers),
            'due_amount' => $student !== null ? $this->money($student->totalDue()) : '0.00',
            'has_dues' => $student !== null && (float) $student->totalDue() > 0,
            'qr' => $qr,
            'barcode' => $this->barcodeFor($student?->admission_no),
            'instructions' => $context['instructions'] ?? $this->defaultInstructions(),
            'issued' => $this->issuance(),
        ];
    }

    /**
     * The papers this student sits, in date order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function papers(Exam $exam, StudentEnrollment $enrollment): array
    {
        $elected = $enrollment->studentSubjects->pluck('class_subject_id');
        $optional = $enrollment->studentSubjects->where('is_optional', true)->pluck('class_subject_id');

        return $exam->examSubjects()
            ->with('classSubject.subject')
            ->whereHas('classSubject', function ($q) use ($enrollment): void {
                $q->where('school_class_id', $enrollment->school_class_id);
            })
            ->when($elected->isNotEmpty(), fn ($q) => $q->whereIn('class_subject_id', $elected->all()))
            ->orderByRaw('exam_date is null')
            ->orderBy('exam_date')
            ->orderBy('serial')
            ->get()
            ->map(fn (ExamSubject $paper) => [
                'serial' => $paper->serial,
                'subject' => $paper->subjectName(),
                'subject_bn' => $paper->classSubject?->subject?->name_bn,
                'board_code' => $paper->classSubject?->subject?->board_subject_code,
                'date' => $this->date($paper->exam_date),
                'day' => $paper->exam_date !== null ? $paper->exam_date->format('l') : null,
                'starts_at' => $paper->starts_at !== null
                    ? \Illuminate\Support\Carbon::parse($paper->starts_at)->format('h:i A')
                    : null,
                'duration' => $paper->duration_minutes !== null
                    ? $this->durationLabel((int) $paper->duration_minutes)
                    : null,
                'room_no' => $paper->room_no,
                'full_marks' => (float) $paper->full_marks,
                'is_optional' => $optional->contains($paper->class_subject_id),
            ])
            ->values()
            ->all();
    }

    /** "2 hrs 30 mins" reads better on a card than "150". */
    private function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours > 0 && $rest > 0 => "{$hours} hrs {$rest} mins",
            $hours > 0 => $hours === 1 ? '1 hr' : "{$hours} hrs",
            default => "{$rest} mins",
        };
    }

    /** @return array<int, string> */
    private function defaultInstructions(): array
    {
        return [
            'Bring this admit card to every examination. Entry without it is not permitted.',
            'Be seated 20 minutes before the examination begins.',
            'Mobile phones, smart watches and any printed material are not allowed in the hall.',
            'Write your class roll clearly on every answer script.',
            'Report any error on this card to the office before the first examination.',
        ];
    }
}
