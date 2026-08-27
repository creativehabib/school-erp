<?php

declare(strict_types=1);

namespace App\Livewire\Hrm;

use App\Enums\LeaveStatus;
use App\Models\Hrm\Employee;
use App\Models\Hrm\LeaveApplication;
use App\Models\Hrm\LeaveType;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Leaves')]
class MyLeaves extends Component
{
    public ?int $leaveTypeId = null;
    public string $fromDate = '';
    public string $toDate = '';
    public string $reason = '';

    public function mount(): void
    {
        Gate::authorize('hrm.leave.apply');
        $this->employee();
    }

    /** @return Collection<int, LeaveType> */
    #[Computed]
    public function leaveTypes(): Collection
    {
        return LeaveType::query()->active()->orderBy('name')->get();
    }

    /** @return Collection<int, LeaveApplication> */
    #[Computed]
    public function applications(): Collection
    {
        return $this->employee()->leaveApplications()->with('leaveType:id,name')->latest()->get();
    }

    public function submit(): void
    {
        Gate::authorize('hrm.leave.apply');
        $validated = $this->validate([
            'leaveTypeId' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')->where('is_active', true)],
            'fromDate' => ['required', 'date', 'after_or_equal:today'],
            'toDate' => ['required', 'date', 'after_or_equal:fromDate'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $days = CarbonImmutable::parse($validated['fromDate'])->diffInDays(CarbonImmutable::parse($validated['toDate'])) + 1;
        $this->employee()->leaveApplications()->create([
            'leave_type_id' => $validated['leaveTypeId'], 'from_date' => $validated['fromDate'],
            'to_date' => $validated['toDate'], 'days' => $days, 'reason' => $validated['reason'],
            'status' => LeaveStatus::Pending,
        ]);
        $this->reset(['leaveTypeId', 'fromDate', 'toDate', 'reason']);
        unset($this->applications);
        Flux::toast(variant: 'success', text: __('Leave request submitted.'));
    }

    private function employee(): Employee
    {
        return Employee::query()->where('user_id', Auth::id())->firstOrFail();
    }
}
