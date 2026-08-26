<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\WaiverType;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeWaiver extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => WaiverType::class,
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeEffectiveOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /** Discount this waiver grants against a gross amount. */
    public function discountFor(float $amount): float
    {
        return match ($this->type) {
            WaiverType::Percent => round($amount * ((float) $this->value / 100), 2),
            WaiverType::Fixed => min($amount, (float) $this->value),
        };
    }
}
