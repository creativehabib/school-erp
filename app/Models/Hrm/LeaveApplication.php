<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\LeaveStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'days' => 'decimal:1',
            'is_half_day' => 'boolean',
            'status' => LeaveStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    /** Approved leave that overlaps a date — used when marking attendance. */
    public function scopeCoveringDate(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->approved()
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date);
    }

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }
}
