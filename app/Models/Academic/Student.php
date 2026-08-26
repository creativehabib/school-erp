<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Accounts\FeeWaiver;
use App\Models\Accounts\Invoice;
use App\Models\Accounts\Payment;
use App\Models\Identity\Board;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immutable student identity. Class / section / roll are NOT here — resolve
 * them through currentEnrollment() or enrollmentFor($session).
 */
class Student extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'left_on' => 'date',
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'status' => StudentStatus::class,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function admissionClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'admission_class_id');
    }

    public function admissionSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'admission_session_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->where('is_current', true);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['is_primary', 'receives_sms', 'can_collect_student'])
            ->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feeWaivers(): HasMany
    {
        return $this->hasMany(FeeWaiver::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StudentStatus::Active);
    }

    /** Students sitting in a given class/section during a given session. */
    public function scopeEnrolledIn(
        Builder $query,
        int $academicSessionId,
        ?int $schoolClassId = null,
        ?int $sectionId = null
    ): Builder {
        return $query->whereHas('enrollments', function (Builder $q) use ($academicSessionId, $schoolClassId, $sectionId) {
            $q->where('academic_session_id', $academicSessionId)
                ->when($schoolClassId, fn (Builder $sq) => $sq->where('school_class_id', $schoolClassId))
                ->when($sectionId, fn (Builder $sq) => $sq->where('section_id', $sectionId));
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name_en', 'like', "%{$term}%")
            ->orWhere('name_bn', 'like', "%{$term}%")
            ->orWhere('admission_no', 'like', "%{$term}%")
            ->orWhere('board_roll', 'like', "%{$term}%"));
    }

    /** Students with an outstanding balance — the fee-collection worklist. */
    public function scopeHavingDues(Builder $query): Builder
    {
        return $query->whereHas('invoices', fn (Builder $q) => $q->where('due_total', '>', 0));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    public function enrollmentFor(AcademicSession|int $session): ?StudentEnrollment
    {
        $id = $session instanceof AcademicSession ? $session->id : $session;

        return $this->enrollments()->where('academic_session_id', $id)->first();
    }

    public function primaryGuardian(): ?Guardian
    {
        return $this->guardians()->wherePivot('is_primary', true)->first();
    }

    public function totalDue(): string
    {
        return (string) $this->invoices()->sum('due_total');
    }

    /**
     * Payload encoded into the ID-card QR. Deliberately an identifier plus a
     * signature target, never personal data — ID cards get lost.
     */
    public function qrPayload(): string
    {
        return "STU:{$this->admission_no}";
    }
}
