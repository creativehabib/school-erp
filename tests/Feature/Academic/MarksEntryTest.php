<?php

use App\Enums\RoleName;
use App\Livewire\Academic\MarksEntry;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\SectionTeacherAssignment;
use App\Models\Academic\Shift;
use App\Models\Academic\Student;
use App\Models\Academic\StudentEnrollment;
use App\Models\Academic\Subject;
use App\Models\Exam\Exam;
use App\Models\Exam\ExamSubject;
use App\Models\Exam\GradeScale;
use App\Models\Exam\Mark;
use App\Models\Hrm\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function marksUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** @return array{session: AcademicSession, class: SchoolClass, section: Section, exam: Exam, paper: ExamSubject} */
function marksSetup(string $classCode = 'C6', string $sectionName = 'A'): array
{
    $year = (int) now()->year;
    $session = AcademicSession::query()->firstOrCreate(
        ['name' => (string) $year],
        ['year' => $year, 'starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31", 'is_current' => true],
    );
    $scale = GradeScale::query()->create(['name' => 'Bangladesh GPA', 'max_gpa' => 5, 'is_default' => true]);
    $schoolClass = SchoolClass::query()->create([
        'name' => 'Class '.$classCode,
        'code' => $classCode,
        'level' => (int) filter_var($classCode, FILTER_SANITIZE_NUMBER_INT),
    ]);
    $shift = Shift::query()->firstOrCreate(['name' => 'Morning']);
    $section = Section::query()->create(['school_class_id' => $schoolClass->id, 'shift_id' => $shift->id, 'name' => $sectionName]);
    $subject = Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH-'.$classCode]);
    $classSubject = ClassSubject::query()->create([
        'school_class_id' => $schoolClass->id,
        'subject_id' => $subject->id,
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $exam = Exam::query()->create([
        'academic_session_id' => $session->id,
        'grade_scale_id' => $scale->id,
        'name' => 'Annual Examination',
        'code' => 'ANNUAL-'.$classCode,
        'type' => 'annual',
        'term' => 3,
    ]);
    $paper = ExamSubject::query()->create([
        'exam_id' => $exam->id,
        'class_subject_id' => $classSubject->id,
        'full_marks' => 100,
        'pass_marks' => 33,
        'cq_full_marks' => 70,
        'cq_pass_marks' => 23,
        'mcq_full_marks' => 30,
        'mcq_pass_marks' => 10,
    ]);

    return ['session' => $session, 'class' => $schoolClass, 'section' => $section, 'exam' => $exam, 'paper' => $paper];
}

function marksEnrollment(array $setup, string $admissionNo, string $name, string $roll): StudentEnrollment
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
        'academic_session_id' => $setup['session']->id,
        'school_class_id' => $setup['class']->id,
        'section_id' => $setup['section']->id,
        'class_roll' => $roll,
        'status' => 'running',
        'is_current' => true,
        'enrolled_on' => now()->toDateString(),
    ]);
}

function assignMarksTeacher(User $teacher, array $setup): void
{
    $employee = Employee::query()->create([
        'user_id' => $teacher->id,
        'employee_code' => 'T-'.$teacher->id,
        'name_en' => $teacher->name,
        'joining_date' => now()->subYear()->toDateString(),
    ]);
    SectionTeacherAssignment::query()->create([
        'section_id' => $setup['section']->id,
        'academic_session_id' => $setup['session']->id,
        'employee_id' => $employee->id,
        'role' => 'class_teacher',
    ]);
}

test('teachers and administrators can open marks entry while students cannot', function () {
    $teacher = marksUser(RoleName::Teacher);
    $admin = marksUser(RoleName::Admin);
    $student = marksUser(RoleName::Student);

    $this->actingAs($teacher)->get(route('exams.marks'))->assertOk();
    $this->actingAs($admin)->get(route('exams.marks'))->assertOk();
    $this->actingAs($student)->get(route('exams.marks'))->assertForbidden();
});

test('a teacher can only choose an assigned section', function () {
    $teacher = marksUser(RoleName::Teacher);
    $assigned = marksSetup('C6', 'A');
    $unassigned = marksSetup('C7', 'B');
    assignMarksTeacher($teacher, $assigned);

    Livewire::actingAs($teacher)
        ->test(MarksEntry::class)
        ->set('examId', $assigned['exam']->id)
        ->set('schoolClassId', $assigned['class']->id)
        ->assertSee('A')
        ->assertDontSee('B')
        ->set('schoolClassId', $unassigned['class']->id)
        ->set('sectionId', $unassigned['section']->id)
        ->assertNotFound();
});

test('marks are totaled graded and saved without duplicates', function () {
    $admin = marksUser(RoleName::Admin);
    $setup = marksSetup();
    $enrollment = marksEnrollment($setup, 'ADM-001', 'Student One', '1');

    $component = Livewire::actingAs($admin)
        ->test(MarksEntry::class)
        ->set('examId', $setup['exam']->id)
        ->set('schoolClassId', $setup['class']->id)
        ->set('sectionId', $setup['section']->id)
        ->set('examSubjectId', $setup['paper']->id)
        ->set("marksData.{$enrollment->id}.written", '65')
        ->set("marksData.{$enrollment->id}.mcq", '20')
        ->call('saveMarks')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('marks', [
        'exam_subject_id' => $setup['paper']->id,
        'student_enrollment_id' => $enrollment->id,
        'obtained_marks' => 85,
        'grade' => 'A+',
        'gpa' => 5,
        'is_failing' => false,
        'entered_by' => $admin->id,
    ]);

    $component
        ->set("marksData.{$enrollment->id}.written", '55')
        ->set("marksData.{$enrollment->id}.mcq", '20')
        ->call('saveMarks')
        ->assertHasNoErrors();

    expect(Mark::query()->count())->toBe(1);
    $this->assertDatabaseHas('marks', ['student_enrollment_id' => $enrollment->id, 'obtained_marks' => 75, 'grade' => 'A', 'gpa' => 4]);
});

test('failing a required component produces an F despite a passing total', function () {
    $admin = marksUser(RoleName::Admin);
    $setup = marksSetup();
    $enrollment = marksEnrollment($setup, 'ADM-001', 'Student One', '1');

    Livewire::actingAs($admin)
        ->test(MarksEntry::class)
        ->set('examId', $setup['exam']->id)
        ->set('schoolClassId', $setup['class']->id)
        ->set('sectionId', $setup['section']->id)
        ->set('examSubjectId', $setup['paper']->id)
        ->set("marksData.{$enrollment->id}.written", '20')
        ->set("marksData.{$enrollment->id}.mcq", '30')
        ->call('saveMarks')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('marks', [
        'student_enrollment_id' => $enrollment->id,
        'obtained_marks' => 50,
        'grade' => 'F',
        'gpa' => 0,
        'is_failing' => true,
    ]);
});

test('marks cannot exceed their configured component ceilings', function () {
    $admin = marksUser(RoleName::Admin);
    $setup = marksSetup();
    $enrollment = marksEnrollment($setup, 'ADM-001', 'Student One', '1');

    Livewire::actingAs($admin)
        ->test(MarksEntry::class)
        ->set('examId', $setup['exam']->id)
        ->set('schoolClassId', $setup['class']->id)
        ->set('sectionId', $setup['section']->id)
        ->set('examSubjectId', $setup['paper']->id)
        ->set("marksData.{$enrollment->id}.written", '71')
        ->set("marksData.{$enrollment->id}.mcq", '20')
        ->call('saveMarks')
        ->assertHasErrors(["marksData.{$enrollment->id}.written" => 'max']);

    expect(Mark::query()->count())->toBe(0);
});
