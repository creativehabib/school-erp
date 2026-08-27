<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassRoutine;
use App\Models\Academic\Student;
use App\Models\Academic\StudentAttendance;
use App\Models\Academic\StudentEnrollment;
use App\Models\Accounts\Expense;
use App\Models\Accounts\Invoice;
use App\Models\Accounts\Payment;
use App\Models\Exam\ExamResult;
use App\Models\Hrm\Employee;
use App\Models\Hrm\LeaveApplication;
use App\Models\Hrm\StaffAttendance;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class MainDashboard extends Component
{
    public string $role = '';

    /** @var array<string, int|float|string|null> */
    public array $metrics = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentPayments = [];

    /** @var array<int, array<string, mixed>> */
    public array $todayRoutines = [];

    /** @var array<int, array<string, mixed>> */
    public array $notices = [];

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->role = (string) $user->getRoleNames()->first();
        $this->notices = Notice::query()->active()->latest('date')->limit(3)->get()
            ->map(fn (Notice $notice): array => [
                'title' => $notice->title,
                'content' => $notice->content,
                'date' => $notice->date->format('d M Y'),
            ])->all();

        match ($this->role) {
            RoleName::SuperAdmin->value, RoleName::Admin->value => $this->loadAdminMetrics(),
            RoleName::Teacher->value => $this->loadTeacherMetrics($user),
            RoleName::Student->value => $this->loadStudentMetrics($user),
            RoleName::Guardian->value => $this->loadGuardianMetrics($user),
            default => null,
        };
    }

    private function loadAdminMetrics(): void
    {
        $attendance = StudentAttendance::query()->whereDate('attendance_date', today());
        $totalAttendance = (clone $attendance)->count();
        $presentAttendance = (clone $attendance)->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])->count();
        $this->metrics = [
            'students' => Student::query()->active()->count(),
            'teachers' => Employee::query()->active()->teaching()->count(),
            'attendance' => $totalAttendance > 0 ? round(($presentAttendance / $totalAttendance) * 100, 1) : 0,
            'income' => (float) Payment::query()->completed()->whereDate('paid_at', today())->sum('amount'),
            'expenses' => (float) Expense::query()->approved()->whereDate('expense_date', today())->sum('amount'),
        ];
        $this->recentPayments = Payment::query()->completed()->with('student:id,name_en,admission_no')->latest('paid_at')->limit(5)->get()
            ->map(fn (Payment $payment): array => [
                'student' => $payment->student?->name_en ?? '—', 'receipt' => $payment->voucher_no,
                'amount' => (float) $payment->amount, 'paid_at' => $payment->paid_at?->format('d M, h:i A'),
            ])->all();
    }

    private function loadTeacherMetrics(User $user): void
    {
        $employee = $user->employee;
        if ($employee === null) {
            return;
        }
        $sessionId = AcademicSession::query()->where('is_current', true)->value('id');
        $sectionIds = $employee->sectionAssignments()->when($sessionId, fn (Builder $query) => $query->where('academic_session_id', $sessionId))->pluck('section_id');
        $attendance = StaffAttendance::query()->where('employee_id', $employee->id)->whereDate('attendance_date', today())->first();
        $this->metrics = [
            'students' => StudentEnrollment::query()->current()->whereIn('section_id', $sectionIds)->distinct()->count('student_id'),
            'attendance_status' => $attendance?->status->label() ?? __('Not recorded'),
            'pending_leaves' => LeaveApplication::query()->where('employee_id', $employee->id)->pending()->count(),
        ];
        $this->todayRoutines = ClassRoutine::query()->when($sessionId, fn (Builder $query) => $query->where('academic_session_id', $sessionId))
            ->where('employee_id', $employee->id)->where('day_of_week', today()->dayOfWeek)
            ->with(['period:id,name,starts_at,ends_at', 'section:id,name', 'classSubject.subject:id,name'])->get()
            ->map(fn (ClassRoutine $routine): array => [
                'period' => $routine->period?->name, 'time' => $routine->period?->timeRange(),
                'section' => $routine->section?->name, 'subject' => $routine->subjectName(),
            ])->all();
    }

    private function loadStudentMetrics(User $user): void
    {
        $student = $user->student;
        $this->metrics = $student ? $this->studentSummary([$student->id]) : [];
    }

    private function loadGuardianMetrics(User $user): void
    {
        $studentIds = $user->guardian?->students()->pluck('students.id')->all() ?? [];
        $this->metrics = $this->studentSummary($studentIds);
    }

    /** @param array<int, int> $studentIds @return array<string, int|float|string|null> */
    private function studentSummary(array $studentIds): array
    {
        $enrollmentIds = StudentEnrollment::query()->current()->whereIn('student_id', $studentIds)->pluck('id');
        $attendance = StudentAttendance::query()->whereIn('student_enrollment_id', $enrollmentIds);
        $total = (clone $attendance)->count();
        $present = (clone $attendance)->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])->count();
        $result = ExamResult::query()->published()->whereIn('student_enrollment_id', $enrollmentIds)->latest('published_at')->first();

        return [
            'attendance' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            'fee_due' => (float) Invoice::query()->outstanding()->whereIn('student_id', $studentIds)->sum('due_total'),
            'gpa' => $result?->gpa !== null ? number_format((float) $result->gpa, 2) : '—',
            'grade' => $result?->grade ?? '—',
        ];
    }
}
