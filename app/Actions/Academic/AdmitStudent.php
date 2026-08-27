<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelation;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use App\Models\Academic\StudentEnrollment;
use App\Models\User;
use App\Services\Accounts\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmitStudent
{
    public function __construct(private DocumentNumberService $documentNumbers)
    {
    }

    /**
     * @param  array{
     *     student_name: string, student_email: string, student_phone: string|null,
     *     date_of_birth: string, gender: string, blood_group: string|null,
     *     school_class_id: int, section_id: int, shift_id: int,
     *     class_roll: string, academic_session_id: int,
     *     father_name: string, father_phone: string, father_email: string|null
     * }  $data
     */
    public function handle(array $data, ?string $photoPath = null): Student
    {
        return DB::transaction(function () use ($data, $photoPath): Student {
            $studentUser = User::query()->create([
                'name' => $data['student_name'],
                'email' => $data['student_email'],
                'phone' => $data['student_phone'],
                'password' => $this->defaultPassword(),
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ]);
            $studentUser->assignRole(RoleName::Student->value);

            $guardianUser = $this->resolveGuardianUser($data);
            $guardianUser->assignRole(RoleName::Guardian->value);

            $guardian = Guardian::query()->firstOrCreate(
                ['user_id' => $guardianUser->id],
                [
                    'name_en' => $data['father_name'],
                    'relation' => GuardianRelation::Father,
                    'phone' => $data['father_phone'],
                    'email' => $data['father_email'],
                ],
            );

            $admissionNo = $this->documentNumbers->next(
                DocumentNumberService::ADMISSION,
                (string) now()->year,
                'ADM-'.now()->year.'-',
                5,
            );

            $student = Student::query()->create([
                'user_id' => $studentUser->id,
                'admission_no' => $admissionNo,
                'name_en' => $data['student_name'],
                'father_name' => $data['father_name'],
                'date_of_birth' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'blood_group' => $data['blood_group'],
                'admission_date' => now()->toDateString(),
                'admission_session_id' => $data['academic_session_id'],
                'admission_class_id' => $data['school_class_id'],
                'phone' => $data['student_phone'],
                'email' => $data['student_email'],
                'photo_path' => $photoPath,
                'status' => StudentStatus::Active,
            ]);

            StudentEnrollment::query()->create([
                'student_id' => $student->id,
                'academic_session_id' => $data['academic_session_id'],
                'school_class_id' => $data['school_class_id'],
                'section_id' => $data['section_id'],
                'shift_id' => $data['shift_id'],
                'class_roll' => $data['class_roll'],
                'status' => EnrollmentStatus::Running,
                'is_current' => true,
                'enrolled_on' => now()->toDateString(),
            ]);

            $student->guardians()->attach($guardian->id, [
                'is_primary' => true,
                'receives_sms' => true,
                'can_collect_student' => true,
            ]);

            return $student->load(['user', 'currentEnrollment', 'guardians.user']);
        });
    }

    /** @param array{father_name: string, father_phone: string, father_email: string|null} $data */
    private function resolveGuardianUser(array $data): User
    {
        $phoneUser = User::withTrashed()->where('phone', $data['father_phone'])->first();
        $emailUser = filled($data['father_email'])
            ? User::withTrashed()->where('email', $data['father_email'])->first()
            : null;

        if ($phoneUser !== null && $emailUser !== null && $phoneUser->isNot($emailUser)) {
            throw ValidationException::withMessages([
                'fatherEmail' => __('The father phone and email belong to different accounts.'),
            ]);
        }

        $guardianUser = $phoneUser ?? $emailUser;

        if ($guardianUser !== null) {
            if ($guardianUser->trashed()) {
                throw ValidationException::withMessages([
                    'fatherPhone' => __('The matching father account is inactive. Restore it before admission.'),
                ]);
            }

            if (filled($data['father_email']) && filled($guardianUser->email) && $guardianUser->email !== $data['father_email']) {
                throw ValidationException::withMessages([
                    'fatherEmail' => __('The father email does not match the existing phone account.'),
                ]);
            }

            if (filled($guardianUser->phone) && $guardianUser->phone !== $data['father_phone']) {
                throw ValidationException::withMessages([
                    'fatherPhone' => __('The father phone does not match the existing email account.'),
                ]);
            }

            $guardianUser->forceFill([
                'phone' => $guardianUser->phone ?? $data['father_phone'],
                'email' => $guardianUser->email ?? $data['father_email'],
            ])->save();

            return $guardianUser;
        }

        return User::query()->create([
            'name' => $data['father_name'],
            'phone' => $data['father_phone'],
            'email' => $data['father_email'],
            'password' => $this->defaultPassword(),
            'status' => UserStatus::Active,
            'must_change_password' => true,
        ]);
    }

    private function defaultPassword(): string
    {
        $password = (string) config('school.admission_default_password');

        if ($password === '') {
            throw ValidationException::withMessages([
                'studentEmail' => __('The admission default password is not configured.'),
            ]);
        }

        return $password;
    }
}
