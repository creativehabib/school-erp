<?php

use App\Enums\RoleName;
use App\Jobs\SendSmsJob;
use App\Livewire\Academic\TakeAttendance;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\SectionTeacherAssignment;
use App\Models\Academic\Shift;
use App\Models\Academic\Student;
use App\Models\Academic\StudentAttendance;
use App\Models\Academic\StudentEnrollment;
use App\Models\Hrm\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function attendanceUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** @return array{session: AcademicSession, class: SchoolClass, section: Section} */
function attendancePlacement(string $code = 'C6', string $sectionName = 'A'): array
{
    $year = (int) now()->year;
    $session = AcademicSession::query()->firstOrCreate(
        ['name' => (string) $year],
        [
            'year' => $year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'is_current' => true,
        ],
    );
    $schoolClass = SchoolClass::query()->create([
        'name' => 'Class '.$code,
        'code' => $code,
        'level' => (int) filter_var($code, FILTER_SANITIZE_NUMBER_INT),
    ]);
    $shift = Shift::query()->firstOrCreate(['name' => 'Morning']);
    $section = Section::query()->create([
        'school_class_id' => $schoolClass->id,
        'shift_id' => $shift->id,
        'name' => $sectionName,
    ]);

    return ['session' => $session, 'class' => $schoolClass, 'section' => $section];
}

function attendanceEnrollment(array $placement, string $admissionNo, string $name, string $roll): StudentEnrollment
{
    $student = Student::query()->create([
        'admission_no' => $admissionNo,
        'name_en' => $name,
        'date_of_birth' => now()->subYears(12)->toDateString(),
        'gender' => 'male',
        'admission_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    return StudentEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_session_id' => $placement['session']->id,
        'school_class_id' => $placement['class']->id,
        'section_id' => $placement['section']->id,
        'class_roll' => $roll,
        'status' => 'running',
        'is_current' => true,
        'enrolled_on' => now()->toDateString(),
    ]);
}

function assignAttendanceTeacher(User $teacher, array $placement): Employee
{
    $employee = Employee::query()->create([
        'user_id' => $teacher->id,
        'employee_code' => 'T-'.$teacher->id,
        'name_en' => $teacher->name,
        'joining_date' => now()->subYear()->toDateString(),
    ]);
    SectionTeacherAssignment::query()->create([
        'section_id' => $placement['section']->id,
        'academic_session_id' => $placement['session']->id,
        'employee_id' => $employee->id,
        'role' => 'class_teacher',
    ]);

    return $employee;
}

test('teachers and administrators can open attendance while students cannot', function () {
    $teacher = attendanceUser(RoleName::Teacher);
    $admin = attendanceUser(RoleName::Admin);
    $student = attendanceUser(RoleName::Student);

    $this->actingAs($teacher)->get(route('attendance.take'))->assertOk();
    $this->actingAs($admin)->get(route('attendance.take'))->assertOk();
    $this->actingAs($student)->get(route('attendance.take'))->assertForbidden();
});

test('a teacher only sees an assigned section', function () {
    $teacher = attendanceUser(RoleName::Teacher);
    $assigned = attendancePlacement('C6', 'A');
    $unassigned = attendancePlacement('C7', 'B');
    assignAttendanceTeacher($teacher, $assigned);

    Livewire::actingAs($teacher)
        ->test(TakeAttendance::class)
        ->set('schoolClassId', $assigned['class']->id)
        ->assertSee('A')
        ->assertDontSee('B')
        ->set('schoolClassId', $unassigned['class']->id)
        ->set('sectionId', $unassigned['section']->id)
        ->assertNotFound();
});

test('mark all present saves attendance for every enrolled student', function () {
    $teacher = attendanceUser(RoleName::Teacher);
    $placement = attendancePlacement();
    assignAttendanceTeacher($teacher, $placement);
    $first = attendanceEnrollment($placement, 'ADM-001', 'First Student', '1');
    $second = attendanceEnrollment($placement, 'ADM-002', 'Second Student', '2');

    Livewire::actingAs($teacher)
        ->test(TakeAttendance::class)
        ->set('schoolClassId', $placement['class']->id)
        ->set('sectionId', $placement['section']->id)
        ->call('markAllPresent')
        ->assertSet('attendanceData', [$first->id => 'present', $second->id => 'present'])
        ->call('saveAttendance')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('student_attendances', [
        'student_enrollment_id' => $first->id,
        'attendance_date' => now()->toDateString(),
        'status' => 'present',
        'recorded_by' => $teacher->id,
    ]);
    expect(StudentAttendance::query()->count())->toBe(2);
});

test('saving attendance again updates the existing daily records', function () {
    $admin = attendanceUser(RoleName::Admin);
    $placement = attendancePlacement();
    $enrollment = attendanceEnrollment($placement, 'ADM-001', 'Student One', '1');

    $component = Livewire::actingAs($admin)
        ->test(TakeAttendance::class)
        ->set('schoolClassId', $placement['class']->id)
        ->set('sectionId', $placement['section']->id)
        ->set("attendanceData.{$enrollment->id}", 'present')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    $component
        ->set("attendanceData.{$enrollment->id}", 'late')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    expect(StudentAttendance::query()->count())->toBe(1);
    $this->assertDatabaseHas('student_attendances', [
        'student_enrollment_id' => $enrollment->id,
        'attendance_date' => now()->toDateString(),
        'status' => 'late',
        'recorded_by' => $admin->id,
    ]);
});

test('a newly absent student queues one SMS for the primary guardian', function () {
    Queue::fake();
    $admin = attendanceUser(RoleName::Admin);
    $placement = attendancePlacement();
    $enrollment = attendanceEnrollment($placement, 'ADM-001', 'Student One', '1');
    $guardian = Guardian::query()->create([
        'name_en' => 'Student Father', 'relation' => 'father', 'phone' => '01712345678',
    ]);
    $enrollment->student->guardians()->attach($guardian->id, [
        'is_primary' => true, 'receives_sms' => true, 'can_collect_student' => true,
    ]);

    $component = Livewire::actingAs($admin)->test(TakeAttendance::class)
        ->set('schoolClassId', $placement['class']->id)
        ->set('sectionId', $placement['section']->id)
        ->set("attendanceData.{$enrollment->id}", 'absent')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job): bool => $job->phone === '01712345678'
        && str_contains($job->message, 'Student One'));

    $component->call('saveAttendance')->assertHasNoErrors();
    Queue::assertPushed(SendSmsJob::class, 1);
});

test('every enrolled student requires a valid attendance status', function () {
    $admin = attendanceUser(RoleName::Admin);
    $placement = attendancePlacement();
    $first = attendanceEnrollment($placement, 'ADM-001', 'First Student', '1');
    $second = attendanceEnrollment($placement, 'ADM-002', 'Second Student', '2');

    Livewire::actingAs($admin)
        ->test(TakeAttendance::class)
        ->set('schoolClassId', $placement['class']->id)
        ->set('sectionId', $placement['section']->id)
        ->set("attendanceData.{$first->id}", 'present')
        ->call('saveAttendance')
        ->assertHasErrors(["attendanceData.{$second->id}" => 'required']);

    expect(StudentAttendance::query()->count())->toBe(0);
});
