<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Enums\BookCopyStatus;
use App\Enums\BookIssueStatus;
use App\Enums\BorrowerType;
use App\Models\Academic\AcademicSession;
use App\Models\Hrm\Employee;
use App\Models\Library\BookCopy;
use App\Models\Library\BookIssue;
use App\Models\Library\LibraryRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lend one copy to one borrower.
 *
 * The copy row is locked for the duration. Two librarians scanning the same barcode at
 * two counters is not hypothetical in a school library at the start of term, and without
 * the lock both reads see "available" and both writes succeed, leaving one physical book
 * on two loan records.
 *
 * Four refusals, all of them things a librarian would otherwise have to remember:
 * the copy must be lendable, the title must not be reference-only, the borrower must be
 * under their book limit, and the borrower must not be holding an unpaid fine. The last
 * one is the whole reason fines work at all - a fine with no consequence is a suggestion.
 */
final class IssueBook
{
    public function handle(
        BookCopy $copy,
        Model $borrower,
        ?User $issuedBy = null,
        ?Carbon $issuedOn = null,
        ?int $loanDays = null,
    ): BookIssue {
        $issuedOn = ($issuedOn ?? now())->startOfDay();
        $type = $this->borrowerType($borrower);
        $rule = LibraryRule::forBorrower($type, $issuedOn);

        if ($rule === null) {
            throw ValidationException::withMessages([
                'copy' => "No library rule is configured for {$type->label()} borrowers.",
            ]);
        }

        return DB::transaction(function () use ($copy, $borrower, $issuedBy, $issuedOn, $loanDays, $rule, $type) {
            /** @var BookCopy $locked */
            $locked = BookCopy::query()
                ->with('book')
                ->whereKey($copy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isLendable()) {
                throw ValidationException::withMessages([
                    'copy' => "Copy {$locked->accession_no} is {$locked->status->label()} and cannot be issued.",
                ]);
            }

            if ($locked->book?->is_reference_only) {
                throw ValidationException::withMessages([
                    'copy' => "\"{$locked->book->title}\" is reference-only and cannot leave the library.",
                ]);
            }

            $this->assertBorrowerEligible($borrower, $rule->max_books);

            $days = $loanDays ?? (int) $rule->loan_days;

            $issue = BookIssue::create([
                'book_copy_id' => $locked->getKey(),
                'borrower_type' => $borrower->getMorphClass(),
                'borrower_id' => $borrower->getKey(),
                // Queried rather than AcademicSession::current(), which throws when no
                // session is marked current. A library should keep lending during the
                // week between sessions, not 500.
                'academic_session_id' => AcademicSession::query()
                    ->where('is_current', true)
                    ->value('id'),
                'status' => BookIssueStatus::Issued,
                'issued_on' => $issuedOn,
                'due_date' => $issuedOn->copy()->addDays($days),
                // Snapshot the policy. Deleting or editing the rule later must not change
                // what this borrower was told they owed per day.
                'fine_per_day' => $rule->fine_per_day,
                'grace_days' => $rule->grace_days,
                'max_fine' => $rule->max_fine,
                'issued_by' => $issuedBy?->getKey() ?? auth()->id(),
            ]);

            $locked->forceFill(['status' => BookCopyStatus::Issued])->save();

            return $issue;
        });
    }

    private function borrowerType(Model $borrower): BorrowerType
    {
        return $borrower instanceof Employee
            ? BorrowerType::Employee
            : BorrowerType::Student;
    }

    private function assertBorrowerEligible(Model $borrower, int $maxBooks): void
    {
        $open = BookIssue::query()->forBorrower($borrower)->open()->count();

        if ($open >= $maxBooks) {
            throw ValidationException::withMessages([
                'borrower' => "This borrower already has {$open} book(s) out; the limit is {$maxBooks}.",
            ]);
        }

        $unpaid = BookIssue::query()
            ->forBorrower($borrower)
            ->unpaidFine()
            ->sum(DB::raw('fine_amount - fine_waived - fine_collected'));

        if ((float) $unpaid > 0) {
            throw ValidationException::withMessages([
                'borrower' => sprintf(
                    'This borrower owes %s in unpaid library fines. Settle or waive it before issuing.',
                    number_format((float) $unpaid, 2),
                ),
            ]);
        }
    }
}
