<?php

use App\Enums\RoleName;
use App\Livewire\Academic\ManageAcademicYears;
use App\Models\Academic\AcademicSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function academicYearUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('administrators can open academic year management', function () {
    $admin = academicYearUser(RoleName::Admin);

    $this->actingAs($admin)
        ->get(route('admin.academic.years'))
        ->assertOk()
        ->assertSee('Academic Years');
});

test('teachers cannot manage academic years', function () {
    $teacher = academicYearUser(RoleName::Teacher);

    $this->actingAs($teacher)
        ->get(route('admin.academic.years'))
        ->assertForbidden();
});

test('an administrator can create the current academic year', function () {
    $admin = academicYearUser(RoleName::Admin);

    Livewire::actingAs($admin)
        ->test(ManageAcademicYears::class)
        ->set('name', '2026')
        ->set('year', 2026)
        ->set('startsOn', '2026-01-01')
        ->set('endsOn', '2026-12-31')
        ->set('isCurrent', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('academic_sessions', [
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'is_current' => true,
    ]);
});

test('making another academic year current clears the previous one', function () {
    $admin = academicYearUser(RoleName::Admin);
    $previous = AcademicSession::query()->create([
        'name' => '2025',
        'year' => 2025,
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
        'is_current' => true,
    ]);
    $next = AcademicSession::query()->create([
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);

    Livewire::actingAs($admin)
        ->test(ManageAcademicYears::class)
        ->call('makeCurrent', $next->id)
        ->assertHasNoErrors();

    expect($previous->refresh()->is_current)->toBeFalse()
        ->and($next->refresh()->is_current)->toBeTrue()
        ->and(AcademicSession::query()->where('is_current', true)->count())->toBe(1);
});

test('the current academic year cannot be unset directly', function () {
    $admin = academicYearUser(RoleName::Admin);
    $current = AcademicSession::query()->create([
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'is_current' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ManageAcademicYears::class)
        ->call('edit', $current->id)
        ->set('isCurrent', false)
        ->call('save')
        ->assertHasErrors(['isCurrent']);

    expect($current->refresh()->is_current)->toBeTrue();
});

test('the end date must follow the start date and year must be unique', function () {
    $admin = academicYearUser(RoleName::Admin);
    AcademicSession::query()->create([
        'name' => '2025',
        'year' => 2025,
        'starts_on' => '2025-01-01',
        'ends_on' => '2025-12-31',
    ]);

    Livewire::actingAs($admin)
        ->test(ManageAcademicYears::class)
        ->set('name', 'Duplicate year')
        ->set('year', 2025)
        ->set('startsOn', '2026-12-31')
        ->set('endsOn', '2026-01-01')
        ->call('save')
        ->assertHasErrors(['year' => 'unique', 'endsOn' => 'after']);
});

test('only a super admin can lock an academic year', function () {
    $admin = academicYearUser(RoleName::Admin);
    $superAdmin = academicYearUser(RoleName::SuperAdmin);
    $academicYear = AcademicSession::query()->create([
        'name' => '2026',
        'year' => 2026,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);

    Livewire::actingAs($admin)
        ->test(ManageAcademicYears::class)
        ->call('toggleLock', $academicYear->id)
        ->assertForbidden();

    Livewire::actingAs($superAdmin)
        ->test(ManageAcademicYears::class)
        ->call('toggleLock', $academicYear->id)
        ->assertHasNoErrors();

    expect($academicYear->refresh()->is_locked)->toBeTrue();
});
