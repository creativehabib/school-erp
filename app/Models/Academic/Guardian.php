<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Enums\GuardianRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The "Father" role, generalised. One guardian -> many students, so a father
 * with three children here has one login and one fee-due view covering all of
 * them.
 */
class Guardian extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'monthly_income' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['is_primary', 'receives_sms', 'can_collect_student'])
            ->withTimestamps();
    }

    /** Combined outstanding balance across every child. */
    public function totalDue(): string
    {
        return (string) $this->students()
            ->join('invoices', 'invoices.student_id', '=', 'students.id')
            ->sum('invoices.due_total');
    }
}
