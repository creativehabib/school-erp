<?php

declare(strict_types=1);

namespace App\Models\Library;

use App\Enums\BorrowerType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Borrowing policy per borrower type, effective-dated.
 *
 * Effective dating for the same reason salary structures are versioned: raising the
 * fine from 2 to 5 taka a day in June must not retroactively inflate a fine that was
 * settled in March. A loan reads the rule in force on its own issue date, and
 * IssueBook snapshots the numbers onto the loan row so even deleting the rule cannot
 * rewrite history.
 */
class LibraryRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'borrower_type', 'max_books', 'loan_days', 'grace_days', 'fine_per_day',
        'max_fine', 'lost_book_multiplier', 'max_renewals',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'borrower_type' => BorrowerType::class,
            'fine_per_day' => 'decimal:2',
            'max_fine' => 'decimal:2',
            'lost_book_multiplier' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeEffectiveOn(Builder $query, DateTimeInterface|string $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * The rule that applied to this borrower type on this date.
     *
     * Ordered newest-first so overlapping rows resolve to the most recent, which is
     * the forgiving reading when a data-entry mistake leaves two rules live.
     */
    public static function forBorrower(BorrowerType $type, DateTimeInterface|string|null $date = null): ?self
    {
        return static::query()
            ->where('borrower_type', $type)
            ->effectiveOn($date ?? now())
            ->orderByDesc('effective_from')
            ->first();
    }
}
