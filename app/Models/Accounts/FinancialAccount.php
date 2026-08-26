<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\FinancialAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Authoritative balance recomputed from the cash book. Use this to verify
     * `current_balance` rather than trusting the cached column blindly.
     */
    public function computedBalance(): string
    {
        $in = $this->transactions()->where('direction', 'in')->sum('amount');
        $out = $this->transactions()->where('direction', 'out')->sum('amount');

        return bcadd((string) $this->opening_balance, bcsub((string) $in, (string) $out, 2), 2);
    }
}
