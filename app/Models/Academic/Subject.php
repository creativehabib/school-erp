<?php

declare(strict_types=1);

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject in the catalogue.
 *
 * Holds nothing class-specific on purpose. The same Mathematics row is offered to
 * class 6 at 100 marks and to class 9 at 100 marks split across two papers, and
 * that variation belongs on ClassSubject.
 */
class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'code', 'board_subject_code', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%");
        });
    }

    /** Bengali name where available, English otherwise. */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'bn' && filled($this->name_bn)
            ? $this->name_bn
            : $this->name;
    }
}
