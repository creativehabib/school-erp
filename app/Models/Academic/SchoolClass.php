<?php

declare(strict_types=1);

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named SchoolClass because `Class` is a reserved word in PHP.
 */
class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    protected $fillable = ['name', 'name_bn', 'code', 'level', 'has_groups', 'is_active'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'has_groups' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('level');
    }

    /** Next class up, used by the year-end promotion action. */
    public function nextClass(): ?self
    {
        return static::query()->active()
            ->where('level', '>', $this->level)
            ->orderBy('level')
            ->first();
    }
}
