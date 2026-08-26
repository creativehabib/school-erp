<?php

declare(strict_types=1);

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One band: A+ = 80-100 = 5.00.
 *
 * `is_failing` is stored rather than inferred from `gpa == 0`, because some primary
 * scales award a non-zero point to the lowest band while still calling it a fail.
 * The "F in any subject fails the whole result" rule then follows the school's own
 * definition instead of a magic number in PHP.
 */
class GradeScaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_scale_id', 'name', 'name_bn', 'min_marks', 'max_marks',
        'gpa', 'is_failing', 'remarks', 'serial',
    ];

    protected function casts(): array
    {
        return [
            'min_marks' => 'decimal:2',
            'max_marks' => 'decimal:2',
            'gpa' => 'decimal:2',
            'is_failing' => 'boolean',
        ];
    }

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function range(): string
    {
        return sprintf('%d - %d', (int) $this->min_marks, (int) $this->max_marks);
    }
}
