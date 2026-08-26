<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'annual_quota', 'is_paid',
        'carry_forward', 'max_carry_forward', 'requires_document', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_quota' => 'decimal:1',
            'max_carry_forward' => 'decimal:1',
            'is_paid' => 'boolean',
            'carry_forward' => 'boolean',
            'requires_document' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
