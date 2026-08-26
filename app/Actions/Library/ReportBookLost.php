<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Actions\Accounts\RecordCashBookEntry;
use App\Enums\BookCopyStatus;
use App\Enums\BookIssueStatus;
use App\Enums\BorrowerType;
use App\Enums\TransactionDirection;
use App\Models\Hrm\Employee;
use App\Models\Library\BookIssue;
use App\Models\Library\LibraryRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Write off a copy the borrower cannot return.
 *
 * The charge is the book's price times the rule's multiplier, not the accrued daily
 * fine. Charging 2 taka a day for a book that is gone forever is both absurdly small and
 * never-ending; a replacement charge closes the loan.
 *
 * The copy becomes Lost rather than being deleted. Deleting it would quietly shrink the
 * library's recorded stock and destroy the loan history that explains where the book went.
 */
final class ReportBookLost
{
    public function __construct(private readonly RecordCashBookEntry $cashBook) {}

    public function handle(
        BookIssue $issue,
        ?User $recordedBy = null,
        ?float $chargeAmount = null,
        float $collectAmount = 0.0,
        ?int $financialAccountId = null,
        ?string $remarks = null,
    ): BookIssue {
        if (! $issue->isOpen()) {
            throw ValidationException::withMessages([
                'issue' => 'Only an open loan can be reported lost.',
            ]);
        }

        return DB::transaction(function () use (
            $issue, $recordedBy, $chargeAmount, $collectAmount, $financialAccountId, $remarks
        ) {
            /** @var BookIssue $locked */
            $locked = BookIssue::query()
                ->with('bookCopy.book')
                ->whereKey($issue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $rule = LibraryRule::forBorrower(
                $locked->borrower_type === Employee::class ? BorrowerType::Employee : BorrowerType::Student,
                $locked->issued_on,
            );

            $charge = $chargeAmount ?? $locked->bookCopy?->book?->replacementCost(
                (float) ($rule?->lost_book_multiplier ?? 1.0)
            ) ?? 0.0;

            if ($collectAmount > $charge) {
                throw ValidationException::withMessages([
                    'amount' => 'Collected amount cannot exceed the replacement charge.',
                ]);
            }

            $locked->forceFill([
                'status' => BookIssueStatus::Lost,
                'fine_amount' => round($charge, 2),
                'fine_collected' => round($collectAmount, 2),
                'received_by' => $recordedBy?->getKey() ?? auth()->id(),
                'remarks' => $remarks,
            ])->save();

            if ($collectAmount > 0) {
                $transaction = $this->cashBook->handle(
                    source: $locked,
                    direction: TransactionDirection::In,
                    amount: $collectAmount,
                    date: now(),
                    financialAccountId: $financialAccountId,
                    category: 'library_lost_book',
                    description: sprintf(
                        'Lost book replacement - %s (%s)',
                        $locked->bookCopy?->book?->title ?? 'book',
                        $locked->bookCopy?->accession_no ?? '-',
                    ),
                    recordedBy: $recordedBy?->getKey() ?? auth()->id(),
                );

                $locked->forceFill(['transaction_id' => $transaction->getKey()])->save();
            }

            $locked->bookCopy?->forceFill(['status' => BookCopyStatus::Lost])->save();

            return $locked->fresh(['bookCopy.book']);
        });
    }
}
