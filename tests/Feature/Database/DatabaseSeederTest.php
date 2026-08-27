<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('creates roles, the configured super admin, and reference data idempotently', function () {
    config()->set('school.super_admin', [
        'name' => 'ERP Owner',
        'email' => 'owner@example.test',
        'phone' => '01711111111',
        'password' => 'A-secure-test-password',
    ]);
    config()->set('school.seed_demo_data', false);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::where('email', 'owner@example.test')->firstOrFail();

    expect($superAdmin->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and($superAdmin->must_change_password)->toBeTrue();
    $this->assertDatabaseCount('roles', count(RoleName::cases()));
    $this->assertDatabaseCount('boards', 11);
    $this->assertDatabaseCount('grade_scale_items', 7);
    $this->assertDatabaseHas('financial_accounts', ['code' => 'CASH', 'is_default' => true]);
    $this->assertDatabaseHas('document_templates', ['type' => 'id_card', 'is_system' => true, 'is_default' => true]);
});

it('creates connected mock data only when demo seeding is enabled', function () {
    config()->set('school.super_admin.password', 'A-secure-test-password');
    config()->set('school.seed_demo_data', true);
    $this->travelTo('2026-08-26 12:00:00');

    $this->seed(DatabaseSeeder::class);

    $student = User::where('email', 'student@school.test')->firstOrFail();
    $guardian = User::where('email', 'father@school.test')->firstOrFail();
    $admin = User::where('email', 'admin-demo@school.test')->firstOrFail();

    expect($student->hasRole(RoleName::Student->value))->toBeTrue()
        ->and($guardian->hasRole(RoleName::Guardian->value))->toBeTrue()
        ->and($admin->hasRole(RoleName::Admin->value))->toBeTrue();
    $this->assertDatabaseHas('student_enrollments', ['class_roll' => '1', 'is_current' => true]);
    $this->assertDatabaseHas('guardian_student', ['is_primary' => true, 'receives_sms' => true]);
    $this->assertDatabaseHas('book_copies', ['accession_no' => 'ACC-0001', 'status' => 'available']);
});
