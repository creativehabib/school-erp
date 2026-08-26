<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One student's marks for one paper.
 *
 * grade / gpa / is_failing are stored, not computed on read. They are a snapshot of
 * what the grade scale said at the moment results were processed. Recomputing them
 * on every page load would mean a school editing its grade bands in March silently
 * rewrites every marksheet printed in January.
 *
 * `is_absent` is distinct from zero marks. Absent students are excluded from subject
 * averages and highest-marks statistics; a genuine zero is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('cq_marks', 6, 2)->nullable();
            $table->decimal('mcq_marks', 6, 2)->nullable();
            $table->decimal('practical_marks', 6, 2)->nullable();
            $table->decimal('obtained_marks', 6, 2)->default(0);
            $table->boolean('is_absent')->default(false);
            $table->string('grade', 5)->nullable()->comment('Snapshot at processing time');
            $table->decimal('gpa', 3, 2)->nullable()->comment('Snapshot at processing time');
            $table->boolean('is_failing')->default(false);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->unique(['exam_subject_id', 'student_enrollment_id'], 'marks_unique');
            $table->index(['student_enrollment_id'], 'marks_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
