<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Models\Library\BookIssue;
use App\Models\Library\LibraryRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Extend a loan.
 *
 * Renewal is refused once the book is already overdue. Allowing it would let a borrower
 * renew away a fine they have already incurred, which turns the fine into a formality and
 * teaches everyone that due dates are negotiable.
 */
final class RenewBookLoan
{
    public function handle(BookIssue $issue, ?User $renewedBy = null, ?int $days = null): BookIssue
    {
        if (! $issue->isOpen()) {
            throw ValidationException::withMessages([
                'issue' => 'Only an open loan can be renewed.',
            ]);
        }

        if ($issue->isOverdue()) {
            throw ValidationException::withMessages([
                'issue' => 'This book is already overdue. Return it and settle the fine first.',
            ]);
        }

        $rule = LibraryRule::forBorrower(
            $issue->borrower_type === \App\Models\Hrm\Employee::class
                ? \App\Enums\BorrowerType::Employee
                : \App\Enums\BorrowerType::Student,
            $issue->issued_on,
        );

        $maxRenewals = (int) ($rule?->max_renewals ?? 0);

        if ($issue->renewal_count >= $maxRenewals) {
            throw ValidationException::withMessages([
                'issue' => "This loan has already been renewed {$issue->renewal_count} time(s); the limit is {$maxRenewals}.",
            ]);
        }

        return DB::transaction(function () use ($issue, $rule, $days, $renewedBy) {
            $extension = $days ?? (int) ($rule?->loan_days ?? 7);

            $issue->forceFill([
                'due_date' => $issue->due_date->copy()->addDays($extension),
                'renewal_count' => $issue->renewal_count + 1,
                'issued_by' => $renewedBy?->getKey() ?? $issue->issued_by,
            ])->save();

            return $issue->fresh();
        });
    }
}
