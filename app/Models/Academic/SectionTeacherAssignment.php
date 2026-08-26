<?php

declare(strict_types=1);

namespace App\Models\Academic;

use App\Models\Hrm\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionTeacherAssignment extends Model
{
    protected $fillable = [
        'section_id', 'academic_session_id', 'employee_id', 'role',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
