<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\CalculationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One versioned line of an employee's salary structure.
 *
 * `resolve($basic)` is the only place the fixed-vs-percentage rule lives, so
 * payroll generation and the salary-structure preview screen cannot disagree.
 */
class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'employee_id', 'salary_component_id', 'value',
        'calculation', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'calculation' => CalculationType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }

    public function resolve(float $basicSalary): float
    {
        return match ($this->calculation) {
            CalculationType::Fixed => (float) $this->value,
            CalculationType::PercentOfBasic => round($basicSalary * ((float) $this->value / 100), 2),
        };
    }
}
