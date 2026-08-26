<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\PaymentMethod;
use App\Enums\PayslipPaymentStatus;
use App\Enums\SalaryComponentType;
use App\Models\Accounts\FinancialAccount;
use App\Models\Accounts\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Employee name / designation / department are snapshots taken at generation
 * time. A payslip is a legal document and must not change retroactively when
 * the employee is later promoted or transferred.
 */
class Payslip extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'gross_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'payable_days' => 'decimal:1',
            'present_days' => 'decimal:1',
            'absent_days' => 'decimal:1',
            'leave_days' => 'decimal:1',
            'payment_status' => PayslipPaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort_order');
    }

    public function earnings(): HasMany
    {
        return $this->items()->where('type', SalaryComponentType::Earning);
    }

    public function deductions(): HasMany
    {
        return $this->items()->where('type', SalaryComponentType::Deduction);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    /** Cash-book rows generated when this payslip was disbursed. */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'source');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('payment_status', '!=', PayslipPaymentStatus::Paid);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PayslipPaymentStatus::Paid;
    }
}
