<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\InvoiceStatus;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'billing_month' => 'integer',
            'billing_year' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'fine_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'due_total' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function gatewayTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'payable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('due_total', '>', 0)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', now());
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('billing_year', $year)->where('billing_month', $month);
    }

    /* ------------------------------------------------------------------ */
    /* Behaviour                                                          */
    /* ------------------------------------------------------------------ */

    public function isPayable(): bool
    {
        return ! in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)
            && (float) $this->due_total > 0;
    }

    public function daysOverdue(): int
    {
        return $this->due_date->isPast() ? (int) $this->due_date->diffInDays(now()) : 0;
    }

    /**
     * Recompute totals from line items and allocations, then derive status.
     * Call this inside the same DB transaction as any payment write — never
     * from a queued job, or two cashiers will race each other.
     */
    public function recalculate(): void
    {
        $items = $this->items()
            ->selectRaw('COALESCE(SUM(amount),0) as amount, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(fine),0) as fine, COALESCE(SUM(total),0) as total')
            ->first();

        $paid = (float) $this->allocations()->sum('amount');
        $grand = (float) $items->total;
        $due = round(max(0, $grand - $paid), 2);

        $status = match (true) {
            $this->status === InvoiceStatus::Cancelled => InvoiceStatus::Cancelled,
            $this->status === InvoiceStatus::Draft => InvoiceStatus::Draft,
            $due <= 0.0 => InvoiceStatus::Paid,
            $paid > 0.0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };

        $this->forceFill([
            'subtotal' => $items->amount,
            'discount_total' => $items->discount,
            'fine_total' => $items->fine,
            'grand_total' => $grand,
            'paid_total' => $paid,
            'due_total' => $due,
            'status' => $status,
        ])->save();
    }
}
