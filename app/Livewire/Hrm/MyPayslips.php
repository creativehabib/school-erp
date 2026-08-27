<?php

declare(strict_types=1);

namespace App\Livewire\Hrm;

use App\Models\Hrm\Employee;
use App\Models\Hrm\Payslip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My Payslips')]
class MyPayslips extends Component
{
    public function mount(): void
    {
        Gate::authorize('hrm.payslip.view');
        $this->employee();
    }

    /** @return Collection<int, Payslip> */
    #[Computed]
    public function payslips(): Collection
    {
        return $this->employee()->payslips()->with('payroll:id,year,month,title')->latest()->get();
    }

    public function download(int $payslipId): void
    {
        Gate::authorize('hrm.payslip.print');
        $payslip = $this->employee()->payslips()->findOrFail($payslipId);
        $this->redirect(URL::temporarySignedRoute('hrm.self.payslips.download', now()->addMinutes(5), ['payslip' => $payslip]));
    }

    private function employee(): Employee
    {
        return Employee::query()->where('user_id', Auth::id())->firstOrFail();
    }
}
