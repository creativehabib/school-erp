<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\LateFineType;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rate card. Invoice generation reads this; nothing else should.
 */
class FeeStructure extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_day' => 'integer',
            'late_fine_amount' => 'decimal:2',
            'late_fine_type' => LateFineType::class,
            'fine_grace_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Rates for a class in a session. A shift-specific row wins over the
     * shift-agnostic (NULL) fallback, hence the ordering.
     */
    public function scopeFor(
        Builder $query,
        int $academicSessionId,
        int $schoolClassId,
        ?int $shiftId = null
    ): Builder {
        return $query->active()
            ->where('academic_session_id', $academicSessionId)
            ->where('school_class_id', $schoolClassId)
            ->where(fn (Builder $q) => $q
                ->whereNull('shift_id')
                ->when($shiftId, fn (Builder $sq) => $sq->orWhere('shift_id', $shiftId)))
            ->orderByRaw('shift_id IS NULL');
    }

    /** Fine owed for a payment that is `$daysLate` days overdue. */
    public function calculateFine(int $daysLate): float
    {
        $chargeableDays = max(0, $daysLate - $this->fine_grace_days);

        if ($chargeableDays === 0 || (float) $this->late_fine_amount === 0.0) {
            return 0.0;
        }

        return match ($this->late_fine_type) {
            LateFineType::Fixed => (float) $this->late_fine_amount,
            LateFineType::PerDay => round((float) $this->late_fine_amount * $chargeableDays, 2),
            LateFineType::Percent => round((float) $this->amount * ((float) $this->late_fine_amount / 100), 2),
        };
    }
}
