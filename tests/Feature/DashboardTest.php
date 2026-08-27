<?php

use App\Enums\RoleName;
use App\Models\Notice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without a role are sent to the access pending dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('dashboard.unassigned'));
});

test('users are sent to and can render their role dashboard', function (RoleName $role, string $route, string $heading) {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($route));

    $this->get(route($route))
        ->assertOk()
        ->assertSee($heading);
})->with([
    'super admin' => [RoleName::SuperAdmin, 'admin.dashboard', 'Welcome back'],
    'admin' => [RoleName::Admin, 'admin.dashboard', 'Welcome back'],
    'teacher' => [RoleName::Teacher, 'teacher.dashboard', 'Welcome back'],
    'student' => [RoleName::Student, 'student.dashboard', 'Welcome back'],
    'guardian' => [RoleName::Guardian, 'guardian.dashboard', 'Welcome back'],
]);

test('all role dashboards show only current active notices', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);
    Notice::factory()->create(['title' => 'Visible Notice', 'date' => today(), 'active_status' => true]);
    Notice::factory()->create(['title' => 'Inactive Notice', 'date' => today(), 'active_status' => false]);
    Notice::factory()->create(['title' => 'Future Notice', 'date' => today()->addDay(), 'active_status' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Visible Notice')
        ->assertDontSee('Inactive Notice')
        ->assertDontSee('Future Notice');
});

test('a user cannot open another role dashboard', function () {
    $this->seed(RolePermissionSeeder::class);
    $student = User::factory()->create();
    $student->assignRole(RoleName::Student->value);

    $this->actingAs($student)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('the super admin bypasses permission checks', function () {
    $this->seed(RolePermissionSeeder::class);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    expect(Gate::forUser($superAdmin)->allows('academic.student.delete'))->toBeTrue();
});
