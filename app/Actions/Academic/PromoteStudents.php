<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Enums\EnrollmentStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudentEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Roll a cohort into the next session.
 *
 * This action is the payoff for keeping placement in student_enrollments. Promotion
 * creates NEW enrollment rows for the next session and closes the old ones; it never
 * edits a class on a student. Last year's invoices, marks, attendance and marksheets
 * keep pointing at last year's enrollment and stay correct forever.
 *
 * Idempotent: a student who already has an enrollment in the target session is skipped,
 * so running promotion twice - or resuming it after a timeout halfway through a
 * 900-student school - does nothing harmful.
 */
final class PromoteStudents
{
    /**
     * @param  array<int, int>  $enrollmentIds  Empty means "everyone in the source class".
     * @return array{promoted: int, skipped: int, retained: int}
     */
    public function handle(
        AcademicSession $fromSession,
        AcademicSession $toSession,
        SchoolClass $fromClass,
        ?SchoolClass $toClass = null,
        array $enrollmentIds = [],
        ?int $toSectionId = null,
        bool $keepRoll = false,
    ): array {
        if ($fromSession->is($toSession)) {
            throw ValidationException::withMessages([
                'session' => 'Source and target sessions must be different.',
            ]);
        }

        if ($toSession->is_locked) {
            throw ValidationException::withMessages([
                'session' => "Session {$toSession->name} is locked.",
            ]);
        }

        $toClass ??= $fromClass->nextClass();

        if ($toClass === null) {
            throw ValidationException::withMessages([
                'class' => "There is no class above {$fromClass->name} to promote into.",
            ]);
        }

        $promoted = 0;
        $skipped = 0;
        $retained = 0;

        StudentEnrollment::query()
            ->where('academic_session_id', $fromSession->getKey())
            ->where('school_class_id', $fromClass->getKey())
            ->when($enrollmentIds !== [], fn ($q) => $q->whereIn('id', $enrollmentIds))
            ->chunkById(200, function (Collection $enrollments) use (
                $toSession, $toClass, $toSectionId, $keepRoll, &$promoted, &$skipped, &$retained
            ): void {
                foreach ($enrollments as $enrollment) {
                    $exists = StudentEnrollment::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('academic_session_id', $toSession->getKey())
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    DB::transaction(function () use (
                        $enrollment, $toSession, $toClass, $toSectionId, $keepRoll, &$promoted
                    ): void {
                        StudentEnrollment::create([
                            'student_id' => $enrollment->student_id,
                            'academic_session_id' => $toSession->getKey(),
                            'school_class_id' => $toClass->getKey(),
                            'section_id' => $toSectionId,
                            'shift_id' => $enrollment->shift_id,
                            // Rolls are usually reassigned after promotion because the class
                            // composition changes; carrying them over is opt-in.
                            'class_roll' => $keepRoll ? $enrollment->class_roll : null,
                            'group' => $enrollment->group,
                            'status' => EnrollmentStatus::Running,
                            'is_current' => true,
                            'enrolled_on' => $toSession->starts_on,
                        ]);

                        $enrollment->forceFill([
                            'status' => EnrollmentStatus::Promoted,
                            'is_current' => false,
                        ])->save();

                        $promoted++;
                    });
                }
            });

        return ['promoted' => $promoted, 'skipped' => $skipped, 'retained' => $retained];
    }
}
