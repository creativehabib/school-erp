<?php

use App\Enums\RoleName;
use App\Livewire\Documents\IDCardGenerator;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\Academic\Student;
use App\Models\Academic\StudentEnrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function idCardUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** @return array{session: AcademicSession, class: SchoolClass, shift: Shift, section: Section} */
function idCardPlacement(string $classCode = 'C6', string $sectionName = 'A'): array
{
    $session = AcademicSession::query()->firstOrCreate(
        ['name' => '2026'],
        ['year' => 2026, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true],
    );
    $schoolClass = SchoolClass::query()->create(['name' => 'Class '.$classCode, 'code' => $classCode, 'level' => (int) filter_var($classCode, FILTER_SANITIZE_NUMBER_INT)]);
    $shift = Shift::query()->firstOrCreate(['name' => 'Morning']);
    $section = Section::query()->create([
        'school_class_id' => $schoolClass->id,
        'shift_id' => $shift->id,
        'name' => $sectionName,
    ]);

    return ['session' => $session, 'class' => $schoolClass, 'shift' => $shift, 'section' => $section];
}

function enrolledIDCardStudent(array $placement, string $admissionNo, string $name, string $roll): Student
{
    $student = Student::query()->create([
        'admission_no' => $admissionNo,
        'name_en' => $name,
        'date_of_birth' => '2014-01-01',
        'gender' => 'male',
        'admission_date' => '2026-01-01',
        'status' => 'active',
    ]);
    StudentEnrollment::query()->create([
        'student_id' => $student->id,
        'academic_session_id' => $placement['session']->id,
        'school_class_id' => $placement['class']->id,
        'section_id' => $placement['section']->id,
        'shift_id' => $placement['shift']->id,
        'class_roll' => $roll,
        'status' => 'running',
        'is_current' => true,
        'enrolled_on' => '2026-01-01',
    ]);

    return $student;
}

test('administrators can open the id card generator', function () {
    $admin = idCardUser(RoleName::Admin);

    $this->actingAs($admin)
        ->get(route('admin.documents.id_cards.index'))
        ->assertOk()
        ->assertSee('Student ID Cards');
});

test('non administrative roles cannot open the id card generator', function (RoleName $role) {
    $user = idCardUser($role);

    $this->actingAs($user)
        ->get(route('admin.documents.id_cards.index'))
        ->assertForbidden();
})->with([RoleName::Teacher, RoleName::Student, RoleName::Guardian]);

test('students are filtered by their current class and section', function () {
    $admin = idCardUser(RoleName::Admin);
    $selectedPlacement = idCardPlacement('C6', 'A');
    $otherPlacement = idCardPlacement('C7', 'B');
    enrolledIDCardStudent($selectedPlacement, 'ADM-001', 'Selected Student', '1');
    enrolledIDCardStudent($otherPlacement, 'ADM-002', 'Other Student', '2');

    Livewire::actingAs($admin)
        ->test(IDCardGenerator::class)
        ->set('schoolClassId', $selectedPlacement['class']->id)
        ->set('sectionId', $selectedPlacement['section']->id)
        ->assertSee('Selected Student')
        ->assertDontSee('Other Student');
});

test('select all chooses the filtered students and creates a signed download redirect', function () {
    $admin = idCardUser(RoleName::Admin);
    $placement = idCardPlacement();
    $first = enrolledIDCardStudent($placement, 'ADM-001', 'First Student', '1');
    $second = enrolledIDCardStudent($placement, 'ADM-002', 'Second Student', '2');

    Livewire::actingAs($admin)
        ->test(IDCardGenerator::class)
        ->set('schoolClassId', $placement['class']->id)
        ->set('sectionId', $placement['section']->id)
        ->set('selectAll', true)
        ->assertSet('selectedStudents', [$first->id, $second->id])
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect();
});

test('students outside the selected section cannot be included', function () {
    $admin = idCardUser(RoleName::Admin);
    $selectedPlacement = idCardPlacement('C6', 'A');
    $otherPlacement = idCardPlacement('C7', 'B');
    $selected = enrolledIDCardStudent($selectedPlacement, 'ADM-001', 'Selected Student', '1');
    $other = enrolledIDCardStudent($otherPlacement, 'ADM-002', 'Other Student', '2');

    Livewire::actingAs($admin)
        ->test(IDCardGenerator::class)
        ->set('schoolClassId', $selectedPlacement['class']->id)
        ->set('sectionId', $selectedPlacement['section']->id)
        ->set('selectedStudents', [$selected->id, $other->id])
        ->call('generate')
        ->assertHasErrors(['selectedStudents']);
});

test('the pdf download requires a valid signed url', function () {
    $admin = idCardUser(RoleName::Admin);

    $this->actingAs($admin)
        ->get(route('admin.documents.id_cards.download', ['ids' => '1']))
        ->assertForbidden();
});

test('the id card pdf template renders a printable card grid with vanilla css', function () {
    $placement = idCardPlacement();
    $student = enrolledIDCardStudent($placement, 'ADM-001', 'Template Student', '7');
    $student->load(['currentEnrollment.schoolClass', 'currentEnrollment.section']);

    $response = $this->view('pdf.id-card', [
        'cards' => [['student' => $student, 'qr' => 'data:image/png;base64,cXI=', 'photo' => null]],
        'school' => null,
        'schoolLogo' => null,
    ]);

    $response->assertSee('Template Student')
        ->assertSee('ADM-001')
        ->assertSee('@page', false)
        ->assertDontSee('class="grid', false);
});
