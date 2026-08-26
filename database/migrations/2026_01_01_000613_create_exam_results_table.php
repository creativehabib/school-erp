<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The processed result: one row per student per exam.
 *
 * This is a materialised summary, rebuilt by ProcessExamResult. It exists because
 * merit position is inherently a whole-cohort calculation - you cannot compute
 * "3rd in class" from one student's rows - and because a marksheet must render from
 * a stable snapshot rather than re-deriving GPA on every PDF request.
 *
 * `subject_snapshot` stores the per-subject breakdown as it was at publication, so a
 * reprinted marksheet from two years ago matches the original even if a subject has
 * since been renamed or removed from the class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_full_marks', 8, 2)->default(0);
            $table->decimal('total_obtained_marks', 8, 2)->default(0);
            $table->decimal('average_marks', 6, 2)->default(0);
            $table->decimal('gpa', 3, 2)->default(0);
            $table->string('grade', 5)->nullable();
            $table->boolean('is_failed')->default(false)->index();
            $table->unsignedSmallInteger('failed_subject_count')->default(0);
            $table->unsignedSmallInteger('appeared_subject_count')->default(0);
            $table->unsignedInteger('class_position')->nullable();
            $table->unsignedInteger('section_position')->nullable();
            $table->unsignedSmallInteger('present_days')->nullable();
            $table->unsignedSmallInteger('working_days')->nullable();
            $table->json('subject_snapshot')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->unique(['exam_id', 'student_enrollment_id'], 'exam_results_unique');
            $table->index(['exam_id', 'gpa'], 'exam_results_merit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
