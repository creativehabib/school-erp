<?php

declare(strict_types=1);

namespace App\Livewire\Hrm;

use App\Actions\Hrm\GenerateMonthlyPayroll;
use App\Enums\PayrollStatus;
use App\Enums\PayslipPaymentStatus;
use App\Models\Hrm\Payroll;
use App\Models\Hrm\Payslip;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Payroll & Salary')]
class PayrollGenerator extends Component
{
    public int $month;
    public int $year;

    public function mount(): void
    {
        Gate::authorize('hrm.payroll.view');
        $this->month = now()->month;
        $this->year = now()->year;
    }

    /** @return Collection<int, Payroll> */
    #[Computed]
    public function payrolls(): Collection
    {
        return Payroll::query()->with('payslips')->latest('year')->latest('month')->limit(24)->get();
    }

    public function generate(GenerateMonthlyPayroll $generator): void
    {
        Gate::authorize('hrm.payroll.generate');
        $this->validate(['month' => ['required', 'integer', 'between:1,12'], 'year' => ['required', 'integer', 'between:2000,2100']]);
        $payroll = $generator->handle($this->year, $this->month);
        unset($this->payrolls);
        Flux::toast(variant: 'success', text: __('Payroll generated for :period.', ['period' => $payroll->periodLabel()]));
    }

    public function markPaid(int $payslipId): void
    {
        Gate::authorize('hrm.payroll.disburse');
        $payslip = Payslip::query()->findOrFail($payslipId);
        $payslip->update([
            'payment_status' => PayslipPaymentStatus::Paid,
            'paid_amount' => $payslip->net_payable,
            'paid_at' => now(),
        ]);
        if (! $payslip->payroll->payslips()->unpaid()->exists()) {
            $payslip->payroll->update([
                'status' => PayrollStatus::Paid,
                'payment_date' => today(),
                'locked_at' => now(),
            ]);
        }
        unset($this->payrolls);
        Flux::toast(variant: 'success', text: __('Payslip marked as paid.'));
    }
}
