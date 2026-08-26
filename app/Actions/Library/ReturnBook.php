<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Actions\Accounts\RecordCashBookEntry;
use App\Enums\BookCopyStatus;
use App\Enums\BookIssueStatus;
use App\Enums\TransactionDirection;
use App\Models\Library\BookIssue;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Take a copy back, settle the fine, put the money in the cash book.
 *
 * The fine is computed from the rule snapshotted onto the loan, never from today's rule.
 *
 * Collected fines go through RecordCashBookEntry like every other rupee in the system.
 * That is the point of having a single cash book: library income shows up in the
 * income-versus-expense chart automatically, instead of sitting in a silo that someone
 * has to remember to add to the monthly report.
 */
final class ReturnBook
{
    public function __construct(private readonly RecordCashBookEntry $cashBook) {}

    public function handle(
        BookIssue $issue,
        ?User $receivedBy = null,
        ?Carbon $returnedOn = null,
        float $waiveAmount = 0.0,
        ?string $waiverReason = null,
        float $collectAmount = 0.0,
        ?int $financialAccountId = null,
        ?BookCopyStatus $copyCondition = null,
    ): BookIssue {
        if (! $issue->isOpen()) {
            throw ValidationException::withMessages([
                'issue' => 'This loan is already closed.',
            ]);
        }

        $returnedOn = ($returnedOn ?? now())->startOfDay();

        if ($returnedOn->lt($issue->issued_on)) {
            throw ValidationException::withMessages([
                'returned_on' => 'A book cannot be returned before it was issued.',
            ]);
        }

        return DB::transaction(function () use (
            $issue, $receivedBy, $returnedOn, $waiveAmount, $waiverReason,
            $collectAmount, $financialAccountId, $copyCondition
        ) {
            /** @var BookIssue $locked */
            $locked = BookIssue::query()
                ->with('bookCopy')
                ->whereKey($issue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->returned_on = $returnedOn;
            $fine = $locked->calculateFine($returnedOn);

            if ($waiveAmount < 0 || $collectAmount < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Waived and collected amounts cannot be negative.',
                ]);
            }

            if (round($waiveAmount + $collectAmount, 2) > round($fine, 2)) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Waived plus collected (%s) exceeds the fine of %s.',
                        number_format($waiveAmount + $collectAmount, 2),
                        number_format($fine, 2),
                    ),
                ]);
            }

            $locked->forceFill([
                'status' => BookIssueStatus::Returned,
                'returned_on' => $returnedOn,
                'fine_amount' => $fine,
                'fine_waived' => $waiveAmount,
                'fine_collected' => $collectAmount,
                'waiver_reason' => $waiveAmount > 0 ? $waiverReason : null,
                'received_by' => $receivedBy?->getKey() ?? auth()->id(),
            ])->save();

            if ($collectAmount > 0) {
                $transaction = $this->cashBook->handle(
                    source: $locked,
                    direction: TransactionDirection::In,
                    amount: $collectAmount,
                    date: $returnedOn,
                    financialAccountId: $financialAccountId,
                    category: 'library_fine',
                    description: sprintf(
                        'Library fine - %s (%s)',
                        $locked->bookCopy?->book?->title ?? 'book',
                        $locked->bookCopy?->accession_no ?? '-',
                    ),
                    recordedBy: $receivedBy?->getKey() ?? auth()->id(),
                );

                $locked->forceFill(['transaction_id' => $transaction->getKey()])->save();
            }

            // A copy returned water-damaged goes back to the shelf as Damaged, not
            // Available, or the next student is issued a ruined book and the library has
            // no record of when it happened.
            $locked->bookCopy?->forceFill([
                'status' => $copyCondition ?? BookCopyStatus::Available,
            ])->save();

            return $locked->fresh(['bookCopy.book', 'transaction']);
        });
    }
}
