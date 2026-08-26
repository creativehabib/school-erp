<?php

declare(strict_types=1);

namespace App\Services\Documents\Payloads;

use App\Enums\SalaryComponentType;
use App\Models\Hrm\Payslip;
use Illuminate\Database\Eloquent\Model;

/**
 * Salary slip.
 *
 * Reads the payslip's own denormalised columns - employee_name, designation_name,
 * bank_account_no - rather than following the relationship to the employee. That looks
 * redundant until someone is promoted: a slip for March must keep saying "Assistant
 * Teacher" after the April promotion to "Senior Teacher", because it is a record of what
 * was paid and why. Joining live would rewrite salary history every time HR edits a
 * profile.
 *
 * MPO matters in Bangladesh: most non-government schools pay a government portion plus a
 * school portion, and staff read the slip to check both. The index number is carried so
 * the slip is usable as proof of MPO enrolment.
 */
final class PayslipPayload extends BasePayloadBuilder
{
    public function accepts(): array
    {
        return [Payslip::class];
    }

    public function build(Model $subject, array $context = []): array
    {
        $this->assertAccepted($subject);

        /** @var Payslip $payslip */
        $payslip = $subject;

        $payslip->loadMissing(['payroll', 'employee', 'items.salaryComponent', 'financialAccount']);

        $earnings = $payslip->items
            ->where('type', SalaryComponentType::Earning)
            ->sortBy('sort_order')
            ->map(fn ($item) => [
                'name' => $item->name,
                'code' => $item->code,
                'amount' => $this->money($item->amount),
            ])
            ->values()
            ->all();

        $deductions = $payslip->items
            ->where('type', SalaryComponentType::Deduction)
            ->sortBy('sort_order')
            ->map(fn ($item) => [
                'name' => $item->name,
                'code' => $item->code,
                'amount' => $this->money($item->amount),
            ])
            ->values()
            ->all();

        return [
            'school' => $this->school(),
            'payslip' => [
                'slip_no' => $payslip->slip_no,
                'period' => $payslip->payroll?->periodLabel() ?? $this->periodFallback($payslip),
                'basic_salary' => $this->money($payslip->basic_salary),
                'gross_earnings' => $this->money($payslip->gross_earnings),
                'total_deductions' => $this->money($payslip->total_deductions),
                'net_payable' => $this->money($payslip->net_payable),
                'net_payable_words' => $this->amountInWords((float) $payslip->net_payable),
                'paid_amount' => $this->money($payslip->paid_amount),
                'payment_status' => $payslip->payment_status instanceof \App\Enums\PayslipPaymentStatus
                    ? $payslip->payment_status->label()
                    : (string) $payslip->payment_status,
                'payment_method' => $payslip->payment_method,
                'payment_reference' => $payslip->payment_reference,
                'paid_at' => $this->date($payslip->paid_at, 'd/m/Y h:i A'),
                'account' => $payslip->financialAccount?->name,
                'note' => $payslip->note,
            ],
            'attendance' => [
                'payable_days' => (float) $payslip->payable_days,
                'present_days' => (float) $payslip->present_days,
                'absent_days' => (float) $payslip->absent_days,
                'leave_days' => (float) $payslip->leave_days,
            ],
            'employee' => [
                'name' => $payslip->employee_name,
                'name_bn' => $payslip->employee?->name_bn,
                'code' => $payslip->employee_code,
                'designation' => $payslip->designation_name,
                'department' => $payslip->department_name,
                'bank_account_no' => $payslip->bank_account_no,
                'bank_name' => $payslip->employee?->bank_name,
                'mpo_index_no' => $payslip->employee?->mpo_index_no,
                'joining_date' => $this->date($payslip->employee?->joining_date),
                'photo' => $this->imagePath($payslip->employee?->photo_path),
                'signature' => $this->imagePath($payslip->employee?->signature_path),
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'has_deductions' => $deductions !== [],
            'barcode' => $this->barcodeFor($payslip->slip_no),
            'remarks' => $context['remarks'] ?? null,
            'issued' => $this->issuance(),
        ];
    }

    private function periodFallback(Payslip $payslip): ?string
    {
        return $this->date($payslip->created_at, 'F Y');
    }
}
