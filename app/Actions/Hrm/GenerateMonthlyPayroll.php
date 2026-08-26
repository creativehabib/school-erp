<?php

declare(strict_types=1);

namespace App\Actions\Hrm;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Enums\PayslipPaymentStatus;
use App\Enums\SalaryComponentType;
use App\Models\Hrm\Employee;
use App\Models\Hrm\EmployeeSalaryComponent;
use App\Models\Hrm\Payroll;
use App\Models\Hrm\Payslip;
use App\Models\Hrm\PayslipItem;
use App\Services\Accounts\DocumentNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Build a draft payroll for one month.
 *
 * Key guarantees:
 *  - unique(year, month) on `payrolls` makes double-running impossible;
 *  - a LOCKED payroll refuses regeneration outright;
 *  - every figure is resolved from the salary structure IN FORCE on the last
 *    day of the month being paid, not from today's structure, so re-generating
 *    an old month still produces the historically correct payslip.
 *
 * Generating a payroll does NOT move money. Disbursement is a separate,
 * explicitly authorised action which is what writes to the cash book.
 */
class GenerateMonthlyPayroll
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
    ) {}

    public function handle(int $year, int $month, ?int $departmentId = null): Payroll
    {
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth();

        return DB::transaction(function () use ($year, $month, $departmentId, $periodEnd) {
            $payroll = Payroll::query()->forMonth($year, $month)->lockForUpdate()->first();

            if ($payroll?->isLocked()) {
                throw new RuntimeException(
                    "Payroll for {$payroll->periodLabel()} is locked and cannot be regenerated."
                );
            }

            $payroll ??= Payroll::create([
                'title' => 'Payroll — '.$periodEnd->format('F Y'),
                'year' => $year,
                'month' => $month,
                'status' => PayrollStatus::Draft,
                'generated_by' => auth()->id(),
            ]);

            // Draft regeneration starts clean rather than accumulating.
            $payroll->payslips()->delete();

            Employee::query()
                ->with(['designation', 'department'])
                ->active()
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->where('joining_date', '<=', $periodEnd)
                ->chunkById(100, function (Collection $employees) use ($payroll, $periodEnd, $year, $month) {
                    foreach ($employees as $employee) {
                        $this->buildPayslip($payroll, $employee, $periodEnd, $year, $month);
                    }
                });

            $payroll->recalculateTotals();

            return $payroll->fresh(['payslips']);
        });
    }

    private function buildPayslip(
        Payroll $payroll,
        Employee $employee,
        CarbonImmutable $periodEnd,
        int $year,
        int $month,
    ): Payslip {
        $basic = (float) $employee->basic_salary;
        $structure = $employee->salaryStructureOn($periodEnd);
        $attendance = $this->attendanceSummary($employee, $year, $month);

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'slip_no' => $this->numbers->payslipNumber($year, $month),

            // Snapshots — see the Payslip docblock.
            'employee_name' => $employee->name_en,
            'employee_code' => $employee->employee_code,
            'designation_name' => $employee->designation?->name,
            'department_name' => $employee->department?->name,
            'bank_account_no' => $employee->bank_account_no,

            'basic_salary' => $basic,
            'payable_days' => $periodEnd->daysInMonth,
            'present_days' => $attendance['present'],
            'absent_days' => $attendance['absent'],
            'leave_days' => $attendance['leave'],
            'payment_status' => PayslipPaymentStatus::Unpaid,
            'payment_method' => $employee->salary_payment_mode,
        ]);

        $earnings = 0.0;
        $deductions = 0.0;
        $sort = 0;

        // Basic pay is always the first earning line.
        PayslipItem::create([
            'payslip_id' => $payslip->id,
            'salary_component_id' => null,
            'name' => 'Basic Salary',
            'code' => 'BASIC',
            'type' => SalaryComponentType::Earning,
            'amount' => $basic,
            'sort_order' => $sort++,
        ]);
        $earnings += $basic;

        /** @var EmployeeSalaryComponent $assignment */
        foreach ($structure as $assignment) {
            $component = $assignment->salaryComponent;

            if (! $component || ! $component->is_active) {
                continue;
            }

            $amount = $assignment->resolve($basic);

            PayslipItem::create([
                'payslip_id' => $payslip->id,
                'salary_component_id' => $component->id,
                'name' => $component->name,
                'code' => $component->code,
                'type' => $component->type,
                'amount' => $amount,
                'sort_order' => $component->sort_order ?: $sort++,
            ]);

            $component->isEarning() ? $earnings += $amount : $deductions += $amount;
        }

        // Unpaid-absence deduction, pro-rated on basic.
        if ($attendance['absent'] > 0) {
            $perDay = $periodEnd->daysInMonth > 0 ? $basic / $periodEnd->daysInMonth : 0;
            $absenceDeduction = round($perDay * $attendance['absent'], 2);

            if ($absenceDeduction > 0) {
                PayslipItem::create([
                    'payslip_id' => $payslip->id,
                    'salary_component_id' => null,
                    'name' => 'Absence Deduction ('.$attendance['absent'].' day/s)',
                    'code' => 'ABSENT',
                    'type' => SalaryComponentType::Deduction,
                    'amount' => $absenceDeduction,
                    'sort_order' => 900,
                ]);

                $deductions += $absenceDeduction;
            }
        }

        $payslip->forceFill([
            'gross_earnings' => round($earnings, 2),
            'total_deductions' => round($deductions, 2),
            'net_payable' => round($earnings - $deductions, 2),
        ])->save();

        return $payslip;
    }

    /** @return array{present: float, absent: float, leave: float} */
    private function attendanceSummary(Employee $employee, int $year, int $month): array
    {
        $rows = $employee->attendances()
            ->forMonth($year, $month)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'present' => (float) ($rows[AttendanceStatus::Present->value] ?? 0)
                + (float) ($rows[AttendanceStatus::Late->value] ?? 0),
            'absent' => (float) ($rows[AttendanceStatus::Absent->value] ?? 0)
                + ((float) ($rows[AttendanceStatus::HalfDay->value] ?? 0) * 0.5),
            'leave' => (float) ($rows[AttendanceStatus::Leave->value] ?? 0),
        ];
    }
}
