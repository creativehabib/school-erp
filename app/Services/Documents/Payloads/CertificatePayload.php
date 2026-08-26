<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Enums\DocumentType;
use App\Models\Academic\Student;
use App\Models\Exam\ExamResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Testimonial, character certificate and transfer certificate.
 *
 * One builder for three documents because they say the same things about the same student
 * and differ only in wording and in which extra facts they carry - a transfer certificate
 * needs the leaving date and dues position, a testimonial needs conduct and last result.
 *
 * Two decisions worth defending.
 *
 * FEE CLEARANCE IS SURFACED, NOT ENFORCED. A transfer certificate is the document a
 * student needs to enroll anywhere else, and Bangladeshi schools do sometimes withhold it
 * over outstanding fees. Whether to do that is a policy call for the head teacher; the
 * builder's job is to make sure nobody signs one without seeing the number.
 *
 * DATES OF ATTENDANCE COME FROM ENROLLMENTS. "Studied in this institution from X to Y" is
 * derived from the first and last enrollment rather than from the admission date alone,
 * because a student who left and returned has two spans and a certificate claiming
 * unbroken attendance would be false.
 */
final class CertificatePayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Student::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Student $student */
        $student = $subject;

        $student->loadMissing([
            'enrollments.schoolClass',
            'enrollments.section',
            'enrollments.academicSession',
            'currentEnrollment.schoolClass',
            'currentEnrollment.section',
            'currentEnrollment.academicSession',
            'admissionClass',
            'board',
            'guardians',
        ]);

        $type = $context['type'] ?? DocumentType::Testimonial;
        $type = $type instanceof DocumentType ? $type : DocumentType::from((string) $type);

        $enrollments = $student->enrollments->sortBy('enrolled_on');
        $first = $enrollments->first();
        $last = $enrollments->last();
        $lastResult = $this->lastResult($student);
        $due = (float) $student->totalDue();
        $guardian = $student->primaryGuardian();

        return [
            'school' => $this->school(),
            'document' => [
                'type' => $type->value,
                'title' => $type->label(),
                'title_bn' => $this->titleBn($type),
            ],
            'student' => [
                'id' => $student->getKey(),
                'name_en' => $student->name_en,
                'name_bn' => $student->name_bn,
                'admission_no' => $student->admission_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'date_of_birth' => $this->date($student->date_of_birth),
                'date_of_birth_words' => $student->date_of_birth !== null
                    ? $student->date_of_birth->format('j F Y')
                    : null,
                'gender' => $student->gender,
                'religion' => $student->religion,
                'nationality' => $student->nationality,
                'blood_group' => $student->blood_group,
                'birth_certificate_no' => $student->birth_certificate_no,
                'photo' => $this->imagePath($student->photo_path),
                'present_address' => $student->present_address,
                'permanent_address' => $student->permanent_address,
                'board' => $student->board?->name,
                'board_roll' => $student->board_roll,
                'board_registration_no' => $student->board_registration_no,
                'status' => $student->status,
            ],
            'guardian' => [
                'name' => $guardian?->name_en,
                'relation' => $guardian?->relation,
                'phone' => $guardian?->phone,
                'occupation' => $guardian?->occupation,
            ],
            'attendance_span' => [
                'from' => $this->date($first?->enrolled_on ?? $student->admission_date),
                'to' => $this->date($student->left_on ?? $last?->academicSession?->ends_on),
                'from_class' => $first?->schoolClass?->name ?? $student->admissionClass?->name,
                'to_class' => $last?->schoolClass?->name,
                'sessions' => $enrollments
                    ->map(static fn ($e) => $e->academicSession?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'years' => $enrollments->count(),
            ],
            'current' => [
                'class' => $student->currentEnrollment?->schoolClass?->name,
                'section' => $student->currentEnrollment?->section?->name,
                'class_roll' => $student->currentEnrollment?->class_roll,
                'group' => $student->currentEnrollment?->group,
                'session' => $student->currentEnrollment?->academicSession?->name,
            ],
            'last_result' => $lastResult,
            'clearance' => [
                'due_amount' => $this->money($due),
                'has_dues' => $due > 0,
                'is_cleared' => $due <= 0,
            ],
            // Free text the office types at issue time. Snapshotted with the document, so
            // a reprint carries the wording that was actually signed.
            'narrative' => [
                'conduct' => $context['conduct'] ?? 'good',
                'reason_for_leaving' => $context['reason_for_leaving'] ?? null,
                'remarks' => $context['remarks'] ?? null,
                'extra_curricular' => $context['extra_curricular'] ?? null,
            ],
            'serial_no' => $context['serial_no'] ?? null,
            'qr' => $this->qrFor($student, 'certificate:'.$type->value),
            'issued' => $this->issuance(),
        ];
    }

    /**
     * The student's most recent published result, for the "secured GPA" line.
     *
     * Only published results are eligible. Quoting an unpublished GPA on a signed
     * certificate would put a number into the world before the school has stood behind it.
     *
     * @return array<string, mixed>|null
     */
    private function lastResult(Student $student): ?array
    {
        $result = ExamResult::query()
            ->whereIn('student_enrollment_id', $student->enrollments->modelKeys())
            ->whereNotNull('published_at')
            ->with('exam.academicSession', 'enrollment.schoolClass')
            ->orderByDesc('published_at')
            ->first();

        if ($result === null) {
            return null;
        }

        return [
            'exam' => $result->exam?->name,
            'session' => $result->exam?->academicSession?->name,
            'class' => $result->enrollment?->schoolClass?->name,
            'gpa' => $result->gpaLabel(),
            'grade' => $result->grade,
            'status' => $result->resultLabel(),
            'published_at' => $this->date($result->published_at),
        ];
    }

    private function titleBn(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Testimonial => 'প্রশংসাপত্র',
            DocumentType::TransferCertificate => 'ছাড়পত্র',
            DocumentType::CharacterCertificate => 'চারিত্রিক সনদপত্র',
            default => $type->label(),
        };
    }
}
