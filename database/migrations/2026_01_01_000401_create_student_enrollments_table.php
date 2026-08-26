<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHERE a student sits in a GIVEN academic session. One row per student per
 * session; `is_current` is a query convenience, the composite unique key is
 * the real integrity guarantee.
 *
 * Every attendance record, mark, invoice and marksheet should resolve class /
 * section through this table so history stays correct after promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();

            $table->string('class_roll', 20)->nullable();
            $table->string('group', 30)->nullable();
            $table->string('status', 20)->default('running')->index();
            $table->boolean('is_current')->default(true)->index();
            $table->date('enrolled_on');
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'enrollment_student_session_unique');
            $table->unique(['academic_session_id', 'section_id', 'class_roll'], 'enrollment_roll_unique');
            $table->index(['academic_session_id', 'school_class_id', 'section_id'], 'enrollment_class_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
