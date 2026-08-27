<?php

declare(strict_types=1);

namespace App\Models\Hrm;

use App\Enums\BloodGroup;
use App\Enums\EmployeeStatus;
use App\Enums\EmployeeType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Academic\SectionTeacherAssignment;
use App\Models\Library\BookIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Teachers and non-teaching staff. `user_id` is nullable so HR can onboard a
 * person before credentials are issued.
 */
class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'resignation_date' => 'date',
            'date_of_birth' => 'date',
            'basic_salary' => 'decimal:2',
            'type' => EmployeeType::class,
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'salary_payment_mode' => PaymentMethod::class,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionTeacherAssignment::class);
    }

    public function bookIssues(): MorphMany
    {
        return $this->morphMany(BookIssue::class, 'borrower');
    }

    /** Salary structure rows, including historical (closed) ones. */
    public function salaryComponents(): BelongsToMany
    {
        return $this->belongsToMany(SalaryComponent::class, 'employee_salary_components')
            ->withPivot(['id', 'value', 'calculation', 'effective_from', 'effective_to'])
            ->withTimestamps();
    }

    public function salaryComponentAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    public function scopeTeaching(Builder $query): Builder
    {
        return $query->where('type', EmployeeType::Teaching);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name_en', 'like', "%{$term}%")
            ->orWhere('employee_code', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('mpo_index_no', 'like', "%{$term}%"));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Salary structure in force on a given date. Payroll MUST use this rather
     * than the raw relationship, otherwise a mid-year raise silently rewrites
     * earlier payslips.
     */
    public function salaryStructureOn(\DateTimeInterface $date): \Illuminate\Support\Collection
    {
        return $this->salaryComponentAssignments()
            ->with('salaryComponent')
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $date))
            ->get();
    }

    public function remainingLeave(int $leaveTypeId, ?int $year = null): float
    {
        $balance = $this->leaveBalances()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year ?? now()->year)
            ->first();

        if (! $balance) {
            return 0.0;
        }

        return (float) ($balance->entitled_days + $balance->carried_days - $balance->consumed_days);
    }

    public function qrPayload(): string
    {
        return "EMP:{$this->employee_code}";
    }
}
