<?php

declare(strict_types=1);

namespace App\Actions\Hrm;

use App\Enums\AttendanceStatus;
use App\Models\Hrm\Employee;
use App\Models\Hrm\StaffAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff attendance for one day.
 *
 * Separate from student attendance despite the near-identical shape, because the two
 * diverge immediately in practice: staff attendance feeds payroll deductions and
 * therefore needs in/out times and a link to an approved leave application, while
 * student attendance feeds the marksheet and needs neither.
 */
final class RecordStaffAttendance
{
    /**
     * @param  array<int, array{status: string, check_in?: string|null, check_out?: string|null, remarks?: string|null}>  $rows
     *                                                                                                                 Keyed by employee id.
     * @return array{saved: int}
     */
    public function handle(Carbon $date, array $rows, ?User $recordedBy = null): array
    {
        if ($date->isFuture()) {
            throw ValidationException::withMessages([
                'date' => 'Attendance cannot be recorded for a future date.',
            ]);
        }

        $valid = Employee::query()
            ->whereIn('id', array_keys($rows))
            ->pluck('id')
            ->all();

        $userId = $recordedBy?->getKey() ?? auth()->id();
        $payload = [];

        foreach ($valid as $employeeId) {
            $row = $rows[$employeeId];
            $status = AttendanceStatus::tryFrom((string) ($row['status'] ?? ''));

            if ($status === null) {
                continue;
            }

            $payload[] = [
                'employee_id' => $employeeId,
                'attendance_date' => $date->toDateString(),
                'status' => $status->value,
                'check_in' => $row['check_in'] ?? null,
                'check_out' => $row['check_out'] ?? null,
                'source' => 'manual',
                'remarks' => $row['remarks'] ?? null,
                'recorded_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($payload === []) {
            return ['saved' => 0];
        }

        DB::transaction(function () use ($payload): void {
            foreach (array_chunk($payload, 500) as $chunk) {
                StaffAttendance::query()->upsert(
                    $chunk,
                    ['employee_id', 'attendance_date'],
                    ['status', 'check_in', 'check_out', 'source', 'remarks', 'recorded_by', 'updated_at'],
                );
            }
        });

        return ['saved' => count($payload)];
    }
}
