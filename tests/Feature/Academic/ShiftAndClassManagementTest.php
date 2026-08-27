<?php

use App\Enums\RoleName;
use App\Livewire\Academic\ManageClasses;
use App\Livewire\Academic\ManageSections;
use App\Livewire\Academic\ManageShifts;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Shift;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function academicUser(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

test('administrators can open shift and class management', function () {
    $admin = academicUser(RoleName::Admin);

    $this->actingAs($admin)->get(route('admin.academic.shifts'))->assertOk()->assertSee('Shift Management');
    $this->actingAs($admin)->get(route('admin.academic.classes'))->assertOk()->assertSee('Class Management');
    $this->actingAs($admin)->get(route('admin.academic.sections'))->assertOk()->assertSee('Section Management');
});

test('non administrative roles cannot open academic setup pages', function (RoleName $role) {
    $user = academicUser($role);

    $this->actingAs($user)->get(route('admin.academic.shifts'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.academic.classes'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.academic.sections'))->assertForbidden();
})->with([
    RoleName::Teacher,
    RoleName::Student,
    RoleName::Guardian,
]);

test('an administrator can create and update a shift', function () {
    $admin = academicUser(RoleName::Admin);

    $component = Livewire::actingAs($admin)
        ->test(ManageShifts::class)
        ->set('name', 'Morning')
        ->set('nameBn', 'সকাল')
        ->set('startsAt', '07:30')
        ->set('endsAt', '12:30')
        ->call('save')
        ->assertHasNoErrors();

    $shift = Shift::query()->where('name', 'Morning')->firstOrFail();

    $component
        ->call('edit', $shift->id)
        ->set('name', 'Day')
        ->set('startsAt', '08:00')
        ->set('endsAt', '13:00')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Day', 'is_active' => true]);
});

test('an administrator can create and update a section', function () {
    $admin = academicUser(RoleName::Admin);
    $schoolClass = SchoolClass::query()->create(['name' => 'Class Six', 'code' => 'C6', 'level' => 6]);
    $shift = Shift::query()->create(['name' => 'Morning']);

    $component = Livewire::actingAs($admin)
        ->test(ManageSections::class)
        ->set('schoolClassId', $schoolClass->id)
        ->set('shiftId', $shift->id)
        ->set('name', 'A')
        ->set('capacity', 40)
        ->set('roomNo', '201')
        ->call('save')
        ->assertHasNoErrors();

    $section = Section::query()->where('name', 'A')->firstOrFail();

    $component
        ->call('edit', $section->id)
        ->set('name', 'B')
        ->set('capacity', 45)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('sections', [
        'id' => $section->id,
        'school_class_id' => $schoolClass->id,
        'shift_id' => $shift->id,
        'name' => 'B',
        'capacity' => 45,
        'room_no' => '201',
    ]);
});

test('a section name must be unique within its class and shift', function () {
    $admin = academicUser(RoleName::Admin);
    $schoolClass = SchoolClass::query()->create(['name' => 'Class Seven', 'code' => 'C7', 'level' => 7]);
    Section::query()->create(['school_class_id' => $schoolClass->id, 'name' => 'A']);

    Livewire::actingAs($admin)
        ->test(ManageSections::class)
        ->set('schoolClassId', $schoolClass->id)
        ->set('name', 'A')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('a shift requires a unique name and an end time after its start time', function () {
    $admin = academicUser(RoleName::Admin);
    Shift::query()->create(['name' => 'Morning']);

    Livewire::actingAs($admin)
        ->test(ManageShifts::class)
        ->set('name', 'Morning')
        ->set('startsAt', '12:00')
        ->set('endsAt', '08:00')
        ->call('save')
        ->assertHasErrors(['name' => 'unique', 'endsAt' => 'after']);
});

test('an administrator can create and update a class', function () {
    $admin = academicUser(RoleName::Admin);

    $component = Livewire::actingAs($admin)
        ->test(ManageClasses::class)
        ->set('name', 'Class Six')
        ->set('nameBn', 'ষষ্ঠ শ্রেণি')
        ->set('code', 'C6')
        ->set('level', 6)
        ->call('save')
        ->assertHasNoErrors();

    $schoolClass = SchoolClass::query()->where('code', 'C6')->firstOrFail();

    $component
        ->call('edit', $schoolClass->id)
        ->set('name', 'Class 6')
        ->set('hasGroups', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('school_classes', ['id' => $schoolClass->id, 'name' => 'Class 6', 'has_groups' => true]);
});

test('class code and numeric level must be unique', function () {
    $admin = academicUser(RoleName::Admin);
    SchoolClass::query()->create(['name' => 'Class Five', 'code' => 'C5', 'level' => 5]);

    Livewire::actingAs($admin)
        ->test(ManageClasses::class)
        ->set('name', 'Duplicate Class')
        ->set('code', 'C5')
        ->set('level', 5)
        ->call('save')
        ->assertHasErrors(['code' => 'unique', 'level' => 'unique']);
});

test('a class with sections cannot be deleted', function () {
    $admin = academicUser(RoleName::Admin);
    $schoolClass = SchoolClass::query()->create(['name' => 'Class Seven', 'code' => 'C7', 'level' => 7]);
    Section::query()->create(['school_class_id' => $schoolClass->id, 'name' => 'A']);

    Livewire::actingAs($admin)
        ->test(ManageClasses::class)
        ->call('delete', $schoolClass->id)
        ->assertHasErrors(['classDeletion']);

    $this->assertDatabaseHas('school_classes', ['id' => $schoolClass->id]);
});
