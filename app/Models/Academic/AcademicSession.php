<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Models\Exam\Exam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'year', 'starts_on', 'ends_on', 'is_current', 'is_locked'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'is_locked' => 'boolean',
            'year' => 'integer',
        ];
    }

    public static function current(): self
    {
        return Cache::rememberForever(
            'academic.session.current',
            fn () => static::query()->where('is_current', true)->firstOrFail()
        );
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('academic.session.current'));
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /** Guard for back-dated edits once the year is closed. */
    public function isEditable(): bool
    {
        return ! $this->is_locked;
    }
}
