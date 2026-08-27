<?php

declare(strict_types=1);

namespace App\Livewire\Academic;

use App\Actions\Academic\RecordStudentAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Jobs\SendSmsJob;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudentEnrollment;
use App\Models\Academic\Student;
use App\Models\Identity\SchoolProfile;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Take Attendance')]
class TakeAttendance extends Component
{
    public ?int $schoolClassId = null;

    public ?int $sectionId = null;

    public ?int $academicSessionId = null;

    public string $date = '';

    /** @var array<int, string> */
    public array $attendanceData = [];

    public function mount(): void
    {
        Gate::authorize('academic.attendance.record');
        $this->date = now()->toDateString();
        $this->academicSessionId = AcademicSession::query()->where('is_current', true)->value('id');
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::query()
            ->active()
            ->whereHas('sections', fn (Builder $query) => $this->constrainSectionAccess($query))
            ->ordered()
            ->get(['id', 'name', 'level']);
    }

    /** @return Collection<int, Section> */
    #[Computed]
    public function sections(): Collection
    {
        return $this->accessibleSectionsQuery()
            ->when($this->schoolClassId, fn (Builder $query) => $query->where('school_class_id', $this->schoolClassId))
            ->when(! $this->schoolClassId, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'school_class_id', 'name']);
    }

    /** @return Collection<int, StudentEnrollment> */
    #[Computed]
    public function students(): Collection
    {
        if ($this->academicSessionId === null || $this->schoolClassId === null || $this->sectionId === null) {
            return new Collection;
        }

        return StudentEnrollment::query()
            ->current()
            ->where('academic_session_id', $this->academicSessionId)
            ->where('school_class_id', $this->schoolClassId)
            ->where('section_id', $this->sectionId)
            ->with([
                'student:id,name_en,photo_path,admission_no',
                'attendances' => fn (Builder $query) => $query
                    ->whereDate('attendance_date', $this->date)
                    ->select(['id', 'student_enrollment_id', 'status']),
            ])
            ->orderBy('class_roll')
            ->get(['id', 'student_id', 'class_roll']);
    }

    public function updatedSchoolClassId(): void
    {
        $this->sectionId = null;
        $this->attendanceData = [];
        unset($this->sections, $this->students);
    }

    public function updatedSectionId(): void
    {
        unset($this->students);
        $this->loadAttendanceData();
    }

    public function updatedDate(): void
    {
        $this->validateOnly('date', [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        unset($this->students);
        $this->loadAttendanceData();
    }

    public function markAllPresent(): void
    {
        Gate::authorize('academic.attendance.record');
        $this->ensureAccessibleSection();
        $this->attendanceData = $this->students
            ->mapWithKeys(fn (StudentEnrollment $enrollment): array => [
                $enrollment->id => AttendanceStatus::Present->value,
            ])
            ->all();
        $this->resetValidation('attendanceData');
    }

    public function saveAttendance(RecordStudentAttendance $recordAttendance): void
    {
        Gate::authorize('academic.attendance.record');
        $this->ensureAccessibleSection();

        $validated = $this->validate([
            'schoolClassId' => ['required', 'integer'],
            'sectionId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'attendanceData' => ['required', 'array', 'min:1'],
            'attendanceData.*' => [
                'required',
                Rule::in([
                    AttendanceStatus::Present->value,
                    AttendanceStatus::Absent->value,
                    AttendanceStatus::Late->value,
                    AttendanceStatus::Leave->value,
                ]),
            ],
        ]);

        $enrollmentIds = $this->students->pluck('id')->sort()->values();
        $submittedIds = collect(array_keys($validated['attendanceData']))->map(fn (int|string $id): int => (int) $id)->sort()->values();

        if ($enrollmentIds->isEmpty() || $enrollmentIds->all() !== $submittedIds->all()) {
            throw ValidationException::withMessages([
                'attendanceData' => __('Record a valid status for every enrolled student.'),
            ]);
        }

        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $previouslyAbsent = $this->students->filter(
            fn (StudentEnrollment $enrollment): bool => $enrollment->attendances->first()?->status === AttendanceStatus::Absent
        )->pluck('student_id');
        $result = $recordAttendance->handle(
            $validated['sectionId'],
            Carbon::parse($validated['date'])->startOfDay(),
            $validated['attendanceData'],
            recordedBy: $user,
        );
        $newlyAbsentStudentIds = $this->students
            ->filter(fn (StudentEnrollment $enrollment): bool => ($validated['attendanceData'][$enrollment->id] ?? null) === AttendanceStatus::Absent->value)
            ->pluck('student_id')
            ->diff($previouslyAbsent)
            ->values()
            ->all();
        $this->dispatchAbsentNotifications($newlyAbsentStudentIds, Carbon::parse($validated['date']));

        unset($this->students);
        $this->loadAttendanceData();
        Flux::toast(
            variant: 'success',
            heading: __('Attendance saved.'),
            text: trans_choice(':count record saved|:count records saved', $result['saved'], ['count' => $result['saved']]),
        );
    }

    /** @param array<int, int|string> $studentIds */
    private function dispatchAbsentNotifications(array $studentIds, Carbon $date): void
    {
        if ($studentIds === []) {
            return;
        }

        $schoolName = SchoolProfile::query()->value('name_en') ?? config('app.name');
        Student::query()->whereKey($studentIds)->with(['guardians' => fn (BelongsToMany $query) => $query
            ->wherePivot('is_primary', true)
            ->wherePivot('receives_sms', true)])
            ->get(['id', 'name_en'])
            ->each(function (Student $student) use ($date, $schoolName): void {
                $phone = $student->guardians->first()?->phone;
                if (filled($phone)) {
                    SendSmsJob::dispatch(
                        $phone,
                        __('Dear Parent, your child :student was absent from school on :date. - :school', [
                            'student' => $student->name_en,
                            'date' => $date->format('d M Y'),
                            'school' => $schoolName,
                        ]),
                    );
                }
            });
    }

    private function loadAttendanceData(): void
    {
        if ($this->sectionId === null || $this->date === '') {
            $this->attendanceData = [];

            return;
        }

        $this->ensureAccessibleSection();
        $this->attendanceData = $this->students
            ->mapWithKeys(function (StudentEnrollment $enrollment): array {
                $attendance = $enrollment->attendances->first();

                return [$enrollment->id => $attendance?->status->value ?? ''];
            })
            ->all();
        $this->resetValidation();
    }

    private function ensureAccessibleSection(): Section
    {
        return $this->accessibleSectionsQuery()
            ->whereKey($this->sectionId)
            ->where('school_class_id', $this->schoolClassId)
            ->firstOrFail();
    }

    private function accessibleSectionsQuery(): Builder
    {
        return $this->constrainSectionAccess(Section::query()->active());
    }

    private function constrainSectionAccess(Builder $query): Builder
    {
        $query->where('is_active', true);

        if ($this->isAdministrator()) {
            return $query;
        }

        $user = Auth::user();
        $employeeId = $user instanceof User ? $user->employee()->value('id') : null;

        if ($employeeId === null || $this->academicSessionId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('teacherAssignments', fn (Builder $assignment) => $assignment
            ->where('employee_id', $employeeId)
            ->where('academic_session_id', $this->academicSessionId));
    }

    private function isAdministrator(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Admin->value,
        ]);
    }
}
