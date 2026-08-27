<?php

use App\Enums\RoleName;
use App\Livewire\Academic\ManageExams;
use App\Models\Academic\AcademicSession;
use App\Models\Exam\Exam;
use App\Models\Exam\GradeScale;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('administrators can open exam management', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.academic.exams'))
        ->assertOk()
        ->assertSee('Exam Management');
});

test('teachers cannot open exam management', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleName::Teacher->value);

    $this->actingAs($teacher)
        ->get(route('admin.academic.exams'))
        ->assertForbidden();
});

test('an administrator can create and update an exam', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $session = AcademicSession::query()->create([
        'name' => '2026', 'year' => 2026, 'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31', 'is_current' => true,
    ]);
    $scale = GradeScale::query()->create(['name' => 'National GPA 5', 'is_default' => true]);

    $component = Livewire::actingAs($admin)
        ->test(ManageExams::class)
        ->set('academicSessionId', $session->id)
        ->set('gradeScaleId', $scale->id)
        ->set('name', 'Annual Examination')
        ->set('code', 'ANNUAL')
        ->set('type', 'annual')
        ->set('term', 3)
        ->set('startsOn', '2026-11-01')
        ->set('endsOn', '2026-11-20')
        ->call('save')
        ->assertHasNoErrors();

    $exam = Exam::query()->where('code', 'ANNUAL')->firstOrFail();

    $component
        ->call('edit', $exam->id)
        ->set('name', 'Final Examination')
        ->set('weight', '75.00')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('exams', [
        'id' => $exam->id,
        'name' => 'Final Examination',
        'weight' => 75,
        'created_by' => $admin->id,
    ]);
});

test('exam code must be unique within an academic year', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    $session = AcademicSession::query()->create([
        'name' => '2026', 'year' => 2026, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
    ]);
    $scale = GradeScale::query()->create(['name' => 'National GPA 5']);
    Exam::query()->create([
        'academic_session_id' => $session->id, 'grade_scale_id' => $scale->id,
        'name' => 'Annual Examination', 'code' => 'ANNUAL', 'type' => 'annual',
    ]);

    Livewire::actingAs($admin)
        ->test(ManageExams::class)
        ->set('academicSessionId', $session->id)
        ->set('gradeScaleId', $scale->id)
        ->set('name', 'Duplicate Examination')
        ->set('code', 'ANNUAL')
        ->set('type', 'annual')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});
