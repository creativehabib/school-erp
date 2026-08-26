<?php

use App\Enums\RoleName;
use App\Livewire\Academic\StudentAdmission;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    config(['school.admission_default_password' => 'Admission!2026']);
    $this->seed(RolePermissionSeeder::class);
});

function admissionAdmin(RoleName $role = RoleName::Admin): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** @return array{session: AcademicSession, class: SchoolClass, shift: Shift, section: Section} */
function admissionPlacement(string $sectionName = 'A'): array
{
    $session = AcademicSession::query()->create([
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'is_current' => true,
    ]);
    $schoolClass = SchoolClass::query()->create(['name' => 'Class Six', 'code' => 'C6', 'level' => 6]);
    $shift = Shift::query()->create(['name' => 'Morning']);
    $section = Section::query()->create([
        'school_class_id' => $schoolClass->id,
        'shift_id' => $shift->id,
        'name' => $sectionName,
    ]);

    return ['session' => $session, 'class' => $schoolClass, 'shift' => $shift, 'section' => $section];
}

test('administrators can open student admission', function () {
    $admin = admissionAdmin();
    admissionPlacement();

    $this->actingAs($admin)
        ->get(route('admin.students.admit'))
        ->assertOk()
        ->assertSee('Student Admission');
});

test('non administrative roles cannot open student admission', function (RoleName $role) {
    $user = admissionAdmin($role);

    $this->actingAs($user)
        ->get(route('admin.students.admit'))
        ->assertForbidden();
})->with([RoleName::Teacher, RoleName::Student, RoleName::Guardian]);

test('an administrator can admit a student and create the father account atomically', function () {
    $admin = admissionAdmin();
    $placement = admissionPlacement();

    Livewire::actingAs($admin)
        ->test(StudentAdmission::class)
        ->set('studentName', 'Nusrat Jahan')
        ->set('studentEmail', 'nusrat@example.test')
        ->set('studentPhone', '01710000001')
        ->set('dateOfBirth', '2014-05-10')
        ->set('gender', 'female')
        ->set('bloodGroup', 'B+')
        ->set('academicSessionId', $placement['session']->id)
        ->set('schoolClassId', $placement['class']->id)
        ->set('shiftId', $placement['shift']->id)
        ->set('sectionId', $placement['section']->id)
        ->set('classRoll', '12')
        ->set('fatherName', 'Md Rahman')
        ->set('fatherPhone', '01710000002')
        ->set('fatherEmail', 'rahman@example.test')
        ->call('admit')
        ->assertHasNoErrors();

    $studentUser = User::query()->where('email', 'nusrat@example.test')->firstOrFail();
    $fatherUser = User::query()->where('email', 'rahman@example.test')->firstOrFail();

    expect($studentUser->hasRole(RoleName::Student->value))->toBeTrue()
        ->and($fatherUser->hasRole(RoleName::Guardian->value))->toBeTrue()
        ->and($studentUser->must_change_password)->toBeTrue()
        ->and(Hash::check('Admission!2026', $studentUser->password))->toBeTrue();

    $this->assertDatabaseHas('students', ['user_id' => $studentUser->id, 'father_name' => 'Md Rahman']);
    $this->assertDatabaseHas('student_enrollments', [
        'academic_session_id' => $placement['session']->id,
        'school_class_id' => $placement['class']->id,
        'section_id' => $placement['section']->id,
        'shift_id' => $placement['shift']->id,
        'class_roll' => '12',
    ]);
    $this->assertDatabaseHas('guardian_student', ['is_primary' => true]);
});

test('an existing father account is reused for another student', function () {
    $admin = admissionAdmin();
    $placement = admissionPlacement();
    $father = User::factory()->create(['name' => 'Existing Father', 'phone' => '01710000003', 'email' => 'father@example.test']);
    $father->assignRole(RoleName::Guardian->value);
    Guardian::query()->create([
        'user_id' => $father->id,
        'name_en' => $father->name,
        'relation' => 'father',
        'phone' => $father->phone,
        'email' => $father->email,
    ]);

    Livewire::actingAs($admin)
        ->test(StudentAdmission::class)
        ->set('studentName', 'Second Child')
        ->set('studentEmail', 'child@example.test')
        ->set('dateOfBirth', '2015-01-01')
        ->set('gender', 'male')
        ->set('academicSessionId', $placement['session']->id)
        ->set('schoolClassId', $placement['class']->id)
        ->set('shiftId', $placement['shift']->id)
        ->set('sectionId', $placement['section']->id)
        ->set('classRoll', '13')
        ->set('fatherName', $father->name)
        ->set('fatherPhone', $father->phone)
        ->set('fatherEmail', $father->email)
        ->call('admit')
        ->assertHasNoErrors();

    expect(User::query()->where('phone', '01710000003')->count())->toBe(1);
    $this->assertDatabaseHas('guardian_student', ['guardian_id' => $father->guardian->id, 'is_primary' => true]);
});

test('a section must belong to the selected class and shift', function () {
    $admin = admissionAdmin();
    $placement = admissionPlacement();
    $otherClass = SchoolClass::query()->create(['name' => 'Class Seven', 'code' => 'C7', 'level' => 7]);

    Livewire::actingAs($admin)
        ->test(StudentAdmission::class)
        ->set('studentName', 'Invalid Placement')
        ->set('studentEmail', 'invalid@example.test')
        ->set('dateOfBirth', '2014-01-01')
        ->set('gender', 'male')
        ->set('academicSessionId', $placement['session']->id)
        ->set('schoolClassId', $otherClass->id)
        ->set('shiftId', $placement['shift']->id)
        ->set('sectionId', $placement['section']->id)
        ->set('classRoll', '14')
        ->set('fatherName', 'Test Father')
        ->set('fatherPhone', '01710000004')
        ->call('admit')
        ->assertHasErrors(['sectionId' => 'exists']);

    $this->assertDatabaseMissing('users', ['email' => 'invalid@example.test']);
});
