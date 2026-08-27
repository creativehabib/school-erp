<?php

use App\Enums\RoleName;
use App\Livewire\Hrm\MyLeaves;
use App\Models\Hrm\Employee;
use App\Models\Hrm\LeaveType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('administrators can open the hrm administration pages', function (string $route, string $heading) {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    $this->actingAs($admin)->get(route($route))->assertOk()->assertSee($heading);
})->with([
    ['hrm.admin.staff', 'Staff Directory'],
    ['hrm.admin.leave_approvals', 'Leave Approvals'],
    ['hrm.admin.payroll', 'Payroll & Salary'],
]);

test('teachers cannot open hrm administration pages', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleName::Teacher->value);

    $this->actingAs($teacher)->get(route('hrm.admin.staff'))->assertForbidden();
});

test('a teacher with an employee profile can submit leave', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole(RoleName::Teacher->value);
    $employee = Employee::query()->create([
        'user_id' => $teacher->id, 'employee_code' => 'EMP-001', 'name_en' => 'Test Teacher',
        'joining_date' => '2020-01-01', 'basic_salary' => 30000,
    ]);
    $leaveType = LeaveType::query()->create(['name' => 'Casual Leave', 'code' => 'CL', 'annual_quota' => 10]);

    Livewire::actingAs($teacher)->test(MyLeaves::class)
        ->set('leaveTypeId', $leaveType->id)
        ->set('fromDate', now()->addDay()->toDateString())
        ->set('toDate', now()->addDays(2)->toDateString())
        ->set('reason', 'Family responsibility requires my presence.')
        ->call('submit')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('leave_applications', [
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'status' => 'pending', 'days' => 2,
    ]);
});

test('the salary slip uses a vanilla css printable layout', function () {
    $data = [
        'school' => ['name_en' => 'Example School', 'address_en' => 'Dhaka'],
        'employee' => ['name' => 'Test Teacher', 'code' => 'EMP-001', 'designation' => 'Assistant Teacher', 'department' => 'Academic', 'bank_account_no' => '1234'],
        'payslip' => ['period' => 'August 2026', 'slip_no' => 'PS-001', 'gross_earnings' => '30,000.00', 'total_deductions' => '1,000.00', 'net_payable' => '29,000.00', 'net_payable_words' => 'Twenty nine thousand taka'],
        'earnings' => [['name' => 'Basic Salary', 'amount' => '30,000.00']],
        'deductions' => [['name' => 'Absence Deduction', 'amount' => '1,000.00']],
        'attendance' => ['present_days' => 20, 'absent_days' => 1, 'leave_days' => 0],
    ];

    $this->view('pdf.salary-slip', compact('data'))
        ->assertSee('SALARY SLIP')
        ->assertSee('Test Teacher')
        ->assertSee('Net Payable')
        ->assertSee('@page', false)
        ->assertDontSee('class="grid', false);
});
