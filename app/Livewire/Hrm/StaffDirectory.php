<?php

declare(strict_types=1);

namespace App\Livewire\Hrm;

use App\Models\Hrm\Designation;
use App\Models\Hrm\Employee;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Staff Directory')]
class StaffDirectory extends Component
{
    public ?int $editingEmployeeId = null;
    public ?int $designationId = null;
    public string $basicSalary = '';

    public function mount(): void
    {
        Gate::authorize('hrm.employee.view');
    }

    /** @return Collection<int, Employee> */
    #[Computed]
    public function employees(): Collection
    {
        return Employee::query()->with(['designation:id,name', 'department:id,name', 'user:id,email'])
            ->active()->orderBy('name_en')->get();
    }

    /** @return Collection<int, Designation> */
    #[Computed]
    public function designations(): Collection
    {
        return Designation::query()->where('is_active', true)->bySeniority()->get(['id', 'name']);
    }

    public function edit(int $employeeId): void
    {
        Gate::authorize('hrm.employee.update');
        $employee = Employee::query()->findOrFail($employeeId);
        $this->editingEmployeeId = $employee->id;
        $this->designationId = $employee->designation_id;
        $this->basicSalary = (string) $employee->basic_salary;
        $this->resetValidation();
        Flux::modal('staff-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('hrm.employee.update');
        $validated = $this->validate([
            'editingEmployeeId' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'designationId' => ['required', 'integer', Rule::exists(Designation::class, 'id')->where('is_active', true)],
            'basicSalary' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        Employee::query()->findOrFail($validated['editingEmployeeId'])->update([
            'designation_id' => $validated['designationId'],
            'basic_salary' => $validated['basicSalary'],
        ]);
        unset($this->employees);
        Flux::modal('staff-form')->close();
        Flux::toast(variant: 'success', text: __('Employee salary details updated.'));
    }
}
