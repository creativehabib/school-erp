<?php

declare(strict_types=1);

namespace App\Models\Library;

use App\Enums\BookCopyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A title. Physical objects live in BookCopy.
 *
 * Splitting title from copy is the decision that makes this module work. A single
 * `quantity` integer cannot answer "which copy is overdue", cannot record that copy
 * three is water-damaged, and races under concurrent issue.
 */
class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_category_id', 'shelf_id', 'title', 'title_bn', 'author', 'publisher',
        'edition', 'isbn', 'language', 'published_year', 'pages', 'price',
        'cover_path', 'description', 'is_reference_only', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_reference_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'book_category_id');
    }

    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function availableCopies(): HasMany
    {
        return $this->copies()->where('status', BookCopyStatus::Available);
    }

    public function issues(): HasManyThrough
    {
        return $this->hasManyThrough(BookIssue::class, BookCopy::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLendable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_reference_only', false);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('title_bn', 'like', "%{$term}%")
                ->orWhere('author', 'like', "%{$term}%")
                ->orWhere('isbn', 'like', "%{$term}%")
                ->orWhereHas('copies', fn (Builder $c) => $c->where('accession_no', 'like', "%{$term}%"));
        });
    }

    public function availableCount(): int
    {
        return $this->availableCopies()->count();
    }

    public function isAvailable(): bool
    {
        return ! $this->is_reference_only && $this->availableCount() > 0;
    }

    /** Replacement charge when a copy is written off. */
    public function replacementCost(float $multiplier = 1.0): float
    {
        return round((float) ($this->price ?? 0) * $multiplier, 2);
    }
}
