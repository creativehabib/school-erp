<?php

declare(strict_types=1);

namespace App\Models\Library;

use App\Enums\BookIssueStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Accounts\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A loan.
 *
 * `borrower` is polymorphic over students and employees rather than pointing at
 * users, because a library card belongs to a student record: young children commonly
 * have no user account and must still be able to borrow.
 *
 * Overdue is derived, never stored. Storing it would mean every loan in the system
 * becomes silently wrong at midnight whenever the scheduler is not running - which on
 * shared cPanel hosting is most of the time.
 *
 * The fine columns are a snapshot of the LibraryRule at issue time, for the same
 * reason payslips snapshot the salary structure.
 */
class BookIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_copy_id', 'borrower_type', 'borrower_id', 'academic_session_id',
        'status', 'issued_on', 'due_date', 'returned_on', 'renewal_count',
        'fine_per_day', 'grace_days', 'max_fine', 'fine_amount', 'fine_waived',
        'fine_collected', 'waiver_reason', 'transaction_id',
        'issued_by', 'received_by', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookIssueStatus::class,
            'issued_on' => 'date',
            'due_date' => 'date',
            'returned_on' => 'date',
            'fine_per_day' => 'decimal:2',
            'max_fine' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'fine_waived' => 'decimal:2',
            'fine_collected' => 'decimal:2',
        ];
    }

    public function borrower(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', BookIssueStatus::Issued);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeForBorrower(Builder $query, Model $borrower): Builder
    {
        return $query->where('borrower_type', $borrower->getMorphClass())
            ->where('borrower_id', $borrower->getKey());
    }

    public function scopeUnpaidFine(Builder $query): Builder
    {
        return $query->whereRaw('fine_amount - fine_waived - fine_collected > 0');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isOverdue(?Carbon $asOf = null): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        return ($asOf ?? now())->startOfDay()->gt($this->due_date);
    }

    /**
     * Days late, after the grace period.
     *
     * Returns 0 rather than a negative number when the book is early, so callers can
     * multiply without guarding.
     */
    public function daysLate(?Carbon $asOf = null): int
    {
        $reference = $this->returned_on ?? $asOf ?? now();
        $graceEnd = $this->due_date->copy()->addDays((int) $this->grace_days);

        if ($reference->startOfDay()->lte($graceEnd)) {
            return 0;
        }

        return (int) $graceEnd->diffInDays($reference->startOfDay());
    }

    /**
     * Fine owed, using the rule snapshotted onto this loan.
     *
     * Capped by max_fine when set. Without a cap, a book forgotten over a two-month
     * summer break generates a fine larger than the book, which no school will
     * actually collect and which then pollutes the receivables report forever.
     */
    public function calculateFine(?Carbon $asOf = null): float
    {
        $days = $this->daysLate($asOf);

        if ($days <= 0) {
            return 0.0;
        }

        $fine = $days * (float) $this->fine_per_day;

        if ($this->max_fine !== null) {
            $fine = min($fine, (float) $this->max_fine);
        }

        return round($fine, 2);
    }

    /** Fine still outstanding after waivers and collections. */
    public function outstandingFine(): float
    {
        return round(
            (float) $this->fine_amount - (float) $this->fine_waived - (float) $this->fine_collected,
            2
        );
    }

    public function borrowerName(): string
    {
        $borrower = $this->borrower;

        if ($borrower === null) {
            return '-';
        }

        // Students and employees both carry name_en; the user account is optional
        // for students, so it cannot be the primary source.
        return $borrower->name_en ?? $borrower->user?->name ?? '-';
    }
}
