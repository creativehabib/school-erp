<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which subjects THIS student sits, and which single one is their 4th subject.
 *
 * This table is what makes the Bangladeshi 4th-subject GPA rule expressible. The
 * rule - "grade points above 2.00 in the optional subject are added to the total" -
 * depends on which subject the individual student elected, so it cannot be answered
 * from class_subjects alone. Schools that do not stream still get correct results:
 * every student simply has is_optional = false on every row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_subject_id')->constrained()->restrictOnDelete();
            $table->boolean('is_optional')->default(false)->comment('TRUE on exactly one row = the 4th subject');
            $table->timestamps();
            
            $table->unique(['student_enrollment_id', 'class_subject_id'], 'student_subjects_unique');
            $table->index(['student_enrollment_id', 'is_optional'], 'student_subjects_optional_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subjects');
    }
};
