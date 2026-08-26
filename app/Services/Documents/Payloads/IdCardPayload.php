<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Models\Academic\Student;
use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * ID cards for students and staff, from one builder.
 *
 * One class rather than two because the card is the same object with different fields
 * filled: a photo, a name, an identifying number, a QR, a validity date. Splitting it
 * would duplicate the QR and photo handling, which is where the bugs live.
 *
 * Blood group is included and placed prominently, because in Bangladesh a school ID is
 * routinely the document an ambulance or hospital reads first.
 */
final class IdCardPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Student::class, Employee::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        return $subject instanceof Student
            ? $this->forStudent($subject, $context)
            : $this->forEmployee($subject, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function forStudent(Student $student, array $context): array
    {
        $student->loadMissing([
            'currentEnrollment.schoolClass',
            'currentEnrollment.section',
            'currentEnrollment.shift',
            'currentEnrollment.academicSession',
            'guardians',
        ]);

        $enrollment = $student->currentEnrollment;
        $guardian = $student->primaryGuardian();

        // Cards expire with the session, not on a rolling 365 days. A card valid until
        // "one year from printing" lets a student who left in March keep a valid-looking
        // card until the following March.
        $validUntil = $enrollment?->academicSession?->ends_on;

        $qr = $this->qrFor(
            $student,
            'id_card',
            $enrollment?->academic_session_id,
            $validUntil !== null ? max(1, (int) now()->diffInDays($validUntil, false)) : null,
        );

        return [
            'school' => $this->school(),
            'holder_type' => 'student',
            'student' => [
                'id' => $student->getKey(),
                'name_en' => $student->name_en,
                'name_bn' => $student->name_bn,
                'admission_no' => $student->admission_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'date_of_birth' => $this->date($student->date_of_birth),
                'blood_group' => $student->blood_group,
                'gender' => $student->gender,
                'religion' => $student->religion,
                'phone' => $student->phone,
                'address' => $student->present_address,
                'photo' => $this->imagePath($student->photo_path),
                'board_roll' => $student->board_roll,
                'board_registration_no' => $student->board_registration_no,
            ],
            'enrollment' => [
                'session' => $enrollment?->academicSession?->name,
                'class' => $enrollment?->schoolClass?->name,
                'class_bn' => $enrollment?->schoolClass?->name_bn,
                'section' => $enrollment?->section?->name,
                'shift' => $enrollment?->shift?->name,
                'group' => $enrollment?->group,
                'class_roll' => $enrollment?->class_roll,
                'placement' => $enrollment?->placementLabel(),
            ],
            'guardian' => [
                'name' => $guardian?->name_en,
                'relation' => $guardian?->relation,
                'phone' => $guardian?->phone,
            ],
            'qr' => $qr,
            'barcode' => $this->barcodeFor($student->admission_no),
            'valid_until' => $this->date($validUntil),
            'issued' => $this->issuance(),
            'remarks' => $context['remarks'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function forEmployee(Employee $employee, array $context): array
    {
        $employee->loadMissing(['department', 'designation']);

        $qr = $this->qrFor($employee, 'staff_card', null, 365);

        return [
            'school' => $this->school(),
            'holder_type' => 'employee',
            'employee' => [
                'id' => $employee->getKey(),
                'name_en' => $employee->name_en,
                'name_bn' => $employee->name_bn,
                'employee_code' => $employee->employee_code,
                'designation' => $employee->designation?->name,
                'department' => $employee->department?->name,
                'type' => $employee->type,
                'mpo_index_no' => $employee->mpo_index_no,
                'joining_date' => $this->date($employee->joining_date),
                'date_of_birth' => $this->date($employee->date_of_birth),
                'blood_group' => $employee->blood_group,
                'nid' => $employee->nid,
                'phone' => $employee->phone,
                'email' => $employee->email,
                'address' => $employee->present_address,
                'photo' => $this->imagePath($employee->photo_path),
                'signature' => $this->imagePath($employee->signature_path),
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
            'qr' => $qr,
            'barcode' => $this->barcodeFor($employee->employee_code),
            'valid_until' => $this->date(now()->addYear()),
            'issued' => $this->issuance(),
            'remarks' => $context['remarks'] ?? null,
        ];
    }
}
