<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function userWithRole(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('administrators can open user access management', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Users &amp; Access', false);
});

test('students cannot open user access management', function () {
    $student = userWithRole(RoleName::Student);

    $this->actingAs($student)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('administrators can create an active user with a role', function () {
    $admin = userWithRole(RoleName::Admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users.index')
        ->set('name', 'New Teacher')
        ->set('email', 'teacher@example.test')
        ->set('phone', '01700000000')
        ->set('status', UserStatus::Active->value)
        ->set('roles', [RoleName::Teacher->value])
        ->set('password', 'secure-password')
        ->set('password_confirmation', 'secure-password')
        ->call('save')
        ->assertHasNoErrors();

    $teacher = User::query()->where('email', 'teacher@example.test')->firstOrFail();

    expect($teacher->hasRole(RoleName::Teacher->value))->toBeTrue()
        ->and($teacher->status)->toBe(UserStatus::Active)
        ->and(Hash::check('secure-password', $teacher->password))->toBeTrue();
});

test('a user requires a role and valid unique contact details', function () {
    $admin = userWithRole(RoleName::Admin);
    User::factory()->create(['email' => 'existing@example.test']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users.index')
        ->set('name', 'Invalid User')
        ->set('email', 'existing@example.test')
        ->set('status', UserStatus::Active->value)
        ->set('password', 'secure-password')
        ->set('password_confirmation', 'secure-password')
        ->set('roles', [])
        ->call('save')
        ->assertHasErrors(['email' => 'unique', 'roles' => 'required']);
});

test('administrators cannot modify a super admin', function () {
    $admin = userWithRole(RoleName::Admin);
    $superAdmin = userWithRole(RoleName::SuperAdmin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users.index')
        ->call('edit', $superAdmin->id)
        ->assertForbidden();
});

test('administrators cannot grant the super admin role', function () {
    $admin = userWithRole(RoleName::Admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users.index')
        ->set('name', 'Escalated User')
        ->set('email', 'escalated@example.test')
        ->set('status', UserStatus::Active->value)
        ->set('password', 'secure-password')
        ->set('password_confirmation', 'secure-password')
        ->set('roles', [RoleName::SuperAdmin->value])
        ->call('save')
        ->assertHasErrors(['roles.0' => 'not_in']);

    $this->assertDatabaseMissing('users', ['email' => 'escalated@example.test']);
});

test('administrators cannot delete themselves', function () {
    $admin = userWithRole(RoleName::Admin);

    Livewire::actingAs($admin)
        ->test('pages::admin.users.index')
        ->call('delete', $admin->id)
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
});
