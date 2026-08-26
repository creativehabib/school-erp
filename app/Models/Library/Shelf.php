<?php

declare(strict_types=1);

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Physical location.
 *
 * Separate from category because where a book lives and what it is about are
 * different questions, and the librarian hunting a misfiled copy needs the first.
 */
class Shelf extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'room', 'rack', 'row', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function locationLabel(): string
    {
        return collect([$this->room, $this->rack, $this->row])
            ->filter()
            ->implode(' / ') ?: $this->name;
    }
}
