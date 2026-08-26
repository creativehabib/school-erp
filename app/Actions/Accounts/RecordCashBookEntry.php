<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\TransactionDirection;
use App\Models\Accounts\FinancialAccount;
use App\Models\Accounts\Transaction;
use Illuminate\Database\Eloquent\Model;

/**
 * The ONLY place a cash-book row is written.
 *
 * Funnelling Payment / Expense / Payslip through one action is what keeps the
 * dashboard trustworthy: if a future module forgets to call this, the money
 * simply never appears in a chart, which is far easier to spot than a subtly
 * wrong total.
 */
class RecordCashBookEntry
{
    public function handle(
        Model $source,
        TransactionDirection $direction,
        float $amount,
        \DateTimeInterface $date,
        ?int $financialAccountId = null,
        ?string $category = null,
        ?string $description = null,
        ?int $recordedBy = null,
    ): Transaction {
        $transaction = new Transaction([
            'transaction_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'financial_account_id' => $financialAccountId,
            'category' => $category,
            'description' => $description,
            'recorded_by' => $recordedBy ?? auth()->id(),
        ]);

        $transaction->source()->associate($source);
        $transaction->save();

        if ($financialAccountId !== null) {
            $this->adjustAccountBalance($financialAccountId, $direction, $amount);
        }

        return $transaction;
    }

    private function adjustAccountBalance(int $accountId, TransactionDirection $direction, float $amount): void
    {
        $column = 'current_balance';

        FinancialAccount::query()->whereKey($accountId)->when(
            $direction === TransactionDirection::In,
            fn ($q) => $q->increment($column, $amount),
            fn ($q) => $q->decrement($column, $amount),
        );
    }
}
