<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Enums\AttendanceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One student, one day.
 *
 * Keyed to the enrollment rather than the student so that a 2026 attendance row
 * keeps pointing at the class and section the student actually sat in during 2026,
 * even after promotion.
 */
class StudentAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_enrollment_id', 'attendance_date', 'status',
        'in_time', 'remarks', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    public function scopeForSection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas(
            'enrollment',
            fn (Builder $q) => $q->where('section_id', $sectionId)
        );
    }

    /**
     * Present / absent / late counts for one enrollment over a date range.
     *
     * Used on the marksheet ("present 182 of 210 days") and in the guardian
     * portal. Half days count as half present, which is how schools report them.
     *
     * @return array{present: float, absent: float, late: int, leave: int, total: int}
     */
    public static function summaryFor(int $enrollmentId, string $from, string $to): array
    {
        $rows = static::query()
            ->where('student_enrollment_id', $enrollmentId)
            ->between($from, $to)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $count = static fn (AttendanceStatus $status): int => (int) ($rows[$status->value] ?? 0);

        $halfDays = $count(AttendanceStatus::HalfDay);

        return [
            'present' => $count(AttendanceStatus::Present) + $count(AttendanceStatus::Late) + ($halfDays * 0.5),
            'absent' => $count(AttendanceStatus::Absent) + ($halfDays * 0.5),
            'late' => $count(AttendanceStatus::Late),
            'leave' => $count(AttendanceStatus::Leave),
            'total' => array_sum($rows),
        ];
    }

    /**
     * Working days recorded for a section in a range.
     *
     * Derived from distinct dates that have any attendance row rather than from a
     * calendar, because a school's real working days include cancelled classes and
     * unplanned closures that no calendar table will ever be kept current with.
     */
    public static function workingDaysForSection(int $sectionId, string $from, string $to): int
    {
        return static::query()
            ->forSection($sectionId)
            ->between($from, $to)
            ->distinct()
            ->count('attendance_date');
    }
}
