<?php

declare(strict_types=1);

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Library classification, two levels deep via parent_id.
 *
 * A nested-set or materialised-path package would be over-engineering here: a
 * school library has a few dozen categories and a librarian who wants a flat list
 * with indentation, not arbitrary depth.
 */
class BookCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'name', 'name_bn', 'code', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function fullName(): string
    {
        return $this->parent
            ? "{$this->parent->name} / {$this->name}"
            : $this->name;
    }
}
