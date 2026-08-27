<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('administrators and teachers can open the marksheet generator', function (RoleName $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('documents.marksheets.index'))
        ->assertOk()
        ->assertSee('Marksheet Generator');
})->with([RoleName::SuperAdmin, RoleName::Admin, RoleName::Teacher]);

test('students and guardians cannot open the marksheet generator', function (RoleName $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('documents.marksheets.index'))
        ->assertForbidden();
})->with([RoleName::Student, RoleName::Guardian]);

test('the marksheet template renders a vanilla css progress report', function () {
    $marksheet = [
        'school' => ['name_en' => 'Example School', 'address_en' => 'Dhaka', 'eiin' => '123456', 'phone' => '01700000000', 'logo' => null],
        'student' => ['name_en' => 'Test Student', 'admission_no' => 'ADM-001'],
        'enrollment' => ['class' => 'Class Six', 'section' => 'A', 'shift' => 'Morning', 'class_roll' => '7'],
        'exam' => ['name' => 'Annual Examination', 'session' => '2026'],
        'subjects' => [[
            'subject' => 'Mathematics', 'full_marks' => 100, 'cq' => 50, 'mcq' => 30,
            'practical' => null, 'obtained_marks' => 80, 'grade' => 'A+', 'gpa' => 5,
            'is_failing' => false, 'is_optional' => false, 'is_absent' => false,
        ]],
        'result' => [
            'total_obtained_marks' => 80, 'total_full_marks' => 100, 'gpa' => '5.00',
            'grade' => 'A+', 'is_failed' => false, 'status' => 'Passed',
        ],
    ];

    $this->view('pdf.marksheet', ['marksheets' => [$marksheet]])
        ->assertSee('ACADEMIC PROGRESS REPORT')
        ->assertSee('Test Student')
        ->assertSee('Mathematics')
        ->assertSee('Final GPA')
        ->assertSee('@page', false)
        ->assertDontSee('class="grid', false);
});
