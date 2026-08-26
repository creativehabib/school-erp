<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\TransactionDirection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * The unified cash book. Every Payment (in), Expense (out) and disbursed
 * Payslip (out) writes one row here.
 *
 * This is the table the financial dashboard reads, which is why the income vs
 * expense chart stays a single grouped query instead of a three-way UNION.
 */
class Transaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'direction' => TransactionDirection::class,
            'amount' => 'decimal:2',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('direction', TransactionDirection::In);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('direction', TransactionDirection::Out);
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('transaction_date', [$from, $to]);
    }

    /**
     * Month-by-month income vs expense for the dashboard chart.
     *
     * Returns rows of { period: 'YYYY-MM', income: string, expense: string }.
     * One query, index-backed by transactions_date_direction_index.
     */
    public static function monthlySummary(\DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return static::query()
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as period")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount END), 0) as expense")
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /** Expense breakdown by category for a doughnut chart. */
    public static function categoryBreakdown(
        TransactionDirection $direction,
        \DateTimeInterface $from,
        \DateTimeInterface $to
    ): Collection {
        return static::query()
            ->selectRaw('category, COALESCE(SUM(amount), 0) as total')
            ->where('direction', $direction)
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }
}
