<?php

declare(strict_types=1);

namespace App\Models\Accounts;

use App\Enums\FeeCategory;
use App\Enums\FeeFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeHead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'code', 'category', 'frequency',
        'is_refundable', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => FeeCategory::class,
            'frequency' => FeeFrequency::class,
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('frequency', FeeFrequency::Monthly);
    }
}
