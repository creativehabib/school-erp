<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily student attendance, keyed to the ENROLLMENT rather than the student.
 *
 * Keying to the enrollment means a 2026 attendance row keeps pointing at the class
 * and section the student was actually in during 2026, even after promotion. Keying
 * to student_id would quietly reassign three years of attendance history every
 * January.
 *
 * Attendance is daily, not period-wise. Period-wise attendance triples the row count
 * and Bangladeshi schools take one roll call at assembly; if a school later needs it,
 * add a nullable period_id and a wider unique index rather than a second table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->comment('AttendanceStatus enum');
            $table->time('in_time')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['student_enrollment_id', 'attendance_date'], 'student_attendance_unique');
            $table->index(['attendance_date', 'status'], 'student_attendance_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
