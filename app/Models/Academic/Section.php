<?php

declare(strict_types=1);

namespace App\Models\Academic;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_class_id', 'shift_id', 'name', 'capacity', 'room_no', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(SectionTeacherAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "Class Six / A (Morning)" — used all over dropdowns and PDFs. */
    public function fullName(): string
    {
        $label = "{$this->schoolClass->name} / {$this->name}";

        return $this->shift ? "{$label} ({$this->shift->name})" : $label;
    }
}
