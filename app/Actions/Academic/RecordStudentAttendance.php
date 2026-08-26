<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Enums\AttendanceStatus;
use App\Models\Academic\StudentAttendance;
use App\Models\Academic\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Take the roll for one section on one day.
 *
 * Idempotent by design. A teacher will save, notice a mistake, and save again; and on a
 * flaky mobile connection the same form may post twice. The unique index on
 * (enrollment, date) plus an upsert makes both harmless.
 *
 * Future dates are rejected. Marking next Tuesday's attendance today is always a
 * mistake - usually a mistyped date - and it corrupts the working-days count that the
 * marksheet prints.
 */
final class RecordStudentAttendance
{
    /**
     * @param  array<int, string>  $statuses  enrollment id => AttendanceStatus value
     * @param  array<int, string>  $remarks   enrollment id => note
     * @return array{saved: int, present: int, absent: int}
     */
    public function handle(
        int $sectionId,
        Carbon $date,
        array $statuses,
        array $remarks = [],
        ?User $recordedBy = null,
    ): array {
        if ($date->isFuture()) {
            throw ValidationException::withMessages([
                'date' => 'Attendance cannot be recorded for a future date.',
            ]);
        }

        // Only accept enrollments that actually belong to this section. Without this, a
        // crafted or stale form could write attendance for another class's students.
        $valid = StudentEnrollment::query()
            ->current()
            ->where('section_id', $sectionId)
            ->whereIn('id', array_keys($statuses))
            ->pluck('id')
            ->all();

        $userId = $recordedBy?->getKey() ?? auth()->id();
        $payload = [];
        $present = 0;
        $absent = 0;

        foreach ($valid as $enrollmentId) {
            $raw = $statuses[$enrollmentId] ?? null;
            $status = AttendanceStatus::tryFrom((string) $raw);

            if ($status === null) {
                continue;
            }

            $status === AttendanceStatus::Absent ? $absent++ : $present++;

            $payload[] = [
                'student_enrollment_id' => $enrollmentId,
                'attendance_date' => $date->toDateString(),
                'status' => $status->value,
                'remarks' => $remarks[$enrollmentId] ?? null,
                'recorded_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload === []) {
            return ['saved' => 0, 'present' => 0, 'absent' => 0];
        }

        DB::transaction(function () use ($payload): void {
            foreach (array_chunk($payload, 500) as $chunk) {
                StudentAttendance::query()->upsert(
                    $chunk,
                    ['student_enrollment_id', 'attendance_date'],
                    ['status', 'remarks', 'recorded_by', 'updated_at'],
                );
            }
        });

        return ['saved' => count($payload), 'present' => $present, 'absent' => $absent];
    }
}
