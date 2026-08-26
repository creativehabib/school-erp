<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_bn', 'code', 'rank', 'is_teaching', 'is_active'];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_teaching' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeBySeniority(Builder $query): Builder
    {
        return $query->orderBy('rank');
    }
}
