<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Academic\Guardian;
use App\Models\Academic\Student;
use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * The single authenticatable identity.
 *
 * A User holds credentials and roles ONLY. Domain data lives in the profile
 * models below, which keeps this table narrow and lets one person legitimately
 * hold two roles — a Teacher whose own child studies here is one User with the
 * Teacher and Guardian roles, an Employee record and a Guardian record.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar_path',
        'status',
        'locale',
        'must_change_password',
        'phone_verified_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Profile relationships                                              */
    /* ------------------------------------------------------------------ */

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%"));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin->value);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Where this user lands after login. Keeping the mapping here (rather than
     * in a controller) means the redirect logic has exactly one home.
     */
    public function dashboardRoute(): string
    {
        return match (true) {
            $this->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Admin->value]) => 'admin.dashboard',
            $this->hasRole(RoleName::Teacher->value) => 'teacher.dashboard',
            $this->hasRole(RoleName::Guardian->value) => 'guardian.dashboard',
            $this->hasRole(RoleName::Student->value) => 'student.dashboard',
            default => 'dashboard.unassigned',
        };
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    /** Used by SMS notification channels (bulk SMS gateways in BD). */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }
}
