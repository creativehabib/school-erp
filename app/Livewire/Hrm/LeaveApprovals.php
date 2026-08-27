<?php

declare(strict_types=1);

namespace App\Livewire\Hrm;

use App\Enums\LeaveStatus;
use App\Models\Hrm\LeaveApplication;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Leave Approvals')]
class LeaveApprovals extends Component
{
    public function mount(): void
    {
        Gate::authorize('hrm.leave.approve');
    }

    /** @return Collection<int, LeaveApplication> */
    #[Computed]
    public function applications(): Collection
    {
        return LeaveApplication::query()->pending()->with(['employee:id,name_en,employee_code', 'leaveType:id,name'])
            ->oldest()->get();
    }

    public function review(int $applicationId, string $decision): void
    {
        Gate::authorize('hrm.leave.approve');
        $status = LeaveStatus::tryFrom($decision);
        if (! in_array($status, [LeaveStatus::Approved, LeaveStatus::Rejected], true)) {
            throw ValidationException::withMessages(['leaveReview' => __('Choose approve or reject.')]);
        }
        $application = LeaveApplication::query()->pending()->findOrFail($applicationId);
        $application->update(['status' => $status, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
        unset($this->applications);
        Flux::toast(variant: 'success', text: __('Leave request :status.', ['status' => $status->label()]));
    }
}
