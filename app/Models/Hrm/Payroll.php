<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\PayrollStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monthly payroll run. Once `locked_at` is set nothing may be recalculated —
 * corrections go through a fresh adjustment rather than editing history.
 */
class Payroll extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'payment_date' => 'date',
            'status' => PayrollStatus::class,
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isEditable(): bool
    {
        return $this->status === PayrollStatus::Draft && ! $this->isLocked();
    }

    public function periodLabel(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    /** Recalculate header totals from the child payslips. */
    public function recalculateTotals(): void
    {
        $totals = $this->payslips()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(gross_earnings),0) as earnings, COALESCE(SUM(total_deductions),0) as deductions, COALESCE(SUM(net_payable),0) as net')
            ->first();

        $this->forceFill([
            'employee_count' => (int) $totals->cnt,
            'total_earnings' => $totals->earnings,
            'total_deductions' => $totals->deductions,
            'total_net' => $totals->net,
        ])->save();
    }
}
