<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class-teacher assignment is per academic session, not a column on
 * `sections` — the teacher in charge of Class Six / A changes every year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('class_teacher');
            $table->timestamps();

            $table->unique(['section_id', 'academic_session_id', 'role'], 'section_teacher_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_teacher_assignments');
    }
};
