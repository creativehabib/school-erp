<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\CalculationType;
use App\Enums\SalaryComponentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'code', 'type', 'calculation',
        'default_value', 'is_taxable', 'applies_to_all', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'calculation' => CalculationType::class,
            'default_value' => 'decimal:2',
            'is_taxable' => 'boolean',
            'applies_to_all' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', SalaryComponentType::Earning);
    }

    public function scopeDeductions(Builder $query): Builder
    {
        return $query->where('type', SalaryComponentType::Deduction);
    }

    public function isEarning(): bool
    {
        return $this->type === SalaryComponentType::Earning;
    }
}
