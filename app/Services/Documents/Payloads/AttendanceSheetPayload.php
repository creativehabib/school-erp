<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Enums\AttendanceStatus;
use App\Models\Academic\Section;
use App\Models\Academic\StudentEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The monthly attendance register for one section.
 *
 * Two documents in one, and the second is the point. Filled in, it is the record a school
 * inspector asks for. BLANK - which is what `include_marks = false` produces - it is the
 * printed grid a teacher carries to class and ticks by hand, because a great many
 * Bangladeshi classrooms have no device at the front and attendance is taken on paper and
 * entered later. Software that only prints filled registers quietly forces every teacher
 * to rule their own grid in a notebook.
 *
 * One query for the whole month, pivoted in PHP. A day-by-day query would be 30 round
 * trips per section and 900 for a school.
 */
final class AttendanceSheetPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Section::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Section $section */
        $section = $subject;

        $section->loadMissing(['schoolClass', 'shift']);

        $month = (int) ($context['month'] ?? now()->month);
        $year = (int) ($context['year'] ?? now()->year);
        $includeMarks = (bool) ($context['include_marks'] ?? true);

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $enrollments = StudentEnrollment::query()
            ->where('section_id', $section->getKey())
            ->when(
                filled($context['academic_session_id'] ?? null),
                fn ($q) => $q->where('academic_session_id', $context['academic_session_id']),
            )
            ->current()
            ->with('student:id,name_en,name_bn,admission_no')
            ->orderByRaw('CAST(class_roll AS UNSIGNED), class_roll')
            ->get();

        $records = $includeMarks
            ? $this->records($enrollments->modelKeys(), $start, $end)
            : [];

        $days = $this->days($start, $end, (array) ($context['holidays'] ?? []));

        $rows = $enrollments->map(function (StudentEnrollment $enrollment) use ($days, $records) {
            $marks = [];
            $present = 0;
            $absent = 0;
            $late = 0;
            $leave = 0;

            foreach ($days as $day) {
                $status = $records[$enrollment->getKey()][$day['date']] ?? null;

                $marks[] = [
                    'date' => $day['date'],
                    'day' => $day['label'],
                    'is_holiday' => $day['is_holiday'],
                    'status' => $status,
                    'symbol' => $this->symbol($status, $day['is_holiday']),
                ];

                // A late student was present. Counting late as its own bucket and not as
                // present would understate attendance and, on a register a parent sees,
                // make a punctuality problem look like an absence problem.
                if ($status === AttendanceStatus::Present->value) {
                    $present++;
                } elseif ($status === AttendanceStatus::Late->value) {
                    $present++;
                    $late++;
                } elseif ($status === AttendanceStatus::Absent->value) {
                    $absent++;
                } elseif ($status === AttendanceStatus::Leave->value) {
                    $leave++;
                }
            }

            $working = count(array_filter($days, static fn (array $d) => ! $d['is_holiday']));

            return [
                'class_roll' => $enrollment->class_roll,
                'admission_no' => $enrollment->student?->admission_no,
                'name_en' => $enrollment->student?->name_en,
                'name_bn' => $enrollment->student?->name_bn,
                'marks' => $marks,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'leave' => $leave,
                'working_days' => $working,
                'percentage' => $working > 0 ? round(($present / $working) * 100, 1) : 0.0,
            ];
        })->values()->all();

        return [
            'school' => $this->school(),
            'section' => [
                'id' => $section->getKey(),
                'name' => $section->name,
                'class' => $section->schoolClass?->name,
                'class_bn' => $section->schoolClass?->name_bn,
                'shift' => $section->shift?->name,
                'room_no' => $section->room_no,
                'full_name' => $section->fullName(),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => $start->format('F Y'),
                'from' => $this->date($start),
                'to' => $this->date($end),
            ],
            'days' => $days,
            'day_count' => count($days),
            'rows' => $rows,
            'student_count' => count($rows),
            'include_marks' => $includeMarks,
            'legend' => [
                'P' => 'Present',
                'A' => 'Absent',
                'L' => 'Late',
                'V' => 'Leave',
                'HD' => 'Half day',
                'H' => 'Holiday',
            ],
            'issued' => $this->issuance(),
        ];
    }

    /**
     * enrollment_id => [Y-m-d => status]
     *
     * @param  array<int, int|string>  $enrollmentIds
     * @return array<int, array<string, string>>
     */
    private function records(array $enrollmentIds, Carbon $start, Carbon $end): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $rows = DB::table('student_attendances')
            ->select('student_enrollment_id', 'attendance_date', 'status')
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row->attendance_date)->toDateString();
            $map[(int) $row->student_enrollment_id][$date] = (string) $row->status;
        }

        return $map;
    }

    /**
     * Every date in the month, flagged as a holiday or not.
     *
     * Friday is the weekly holiday and Saturday is a working day - the Bangladeshi school
     * week, not the Western one. Getting this backwards makes every register wrong and
     * every attendance percentage indefensible. Additional dates can be passed in
     * `holidays` for Eid, Pohela Boishakh and school-specific closures.
     *
     * @param  array<int, string>  $holidays
     * @return array<int, array{date: string, day: int, label: string, weekday: string, is_holiday: bool}>
     */
    private function days(Carbon $start, Carbon $end, array $holidays): array
    {
        $extra = array_map(
            static fn (string $date) => Carbon::parse($date)->toDateString(),
            $holidays,
        );

        $weekend = (array) config('school.weekend_days', [Carbon::FRIDAY]);
        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $days[] = [
                'date' => $date->toDateString(),
                'day' => $date->day,
                'label' => (string) $date->day,
                'weekday' => $date->format('D'),
                'is_holiday' => in_array($date->dayOfWeek, $weekend, true)
                    || in_array($date->toDateString(), $extra, true),
            ];
        }

        return $days;
    }

    private function symbol(?string $status, bool $isHoliday): string
    {
        if ($isHoliday) {
            return 'H';
        }

        return match ($status) {
            AttendanceStatus::Present->value => 'P',
            AttendanceStatus::Absent->value => 'A',
            AttendanceStatus::Late->value => 'L',
            AttendanceStatus::Leave->value => 'V',
            AttendanceStatus::HalfDay->value => 'HD',
            AttendanceStatus::Holiday->value => 'H',
            default => '',
        };
    }
}
