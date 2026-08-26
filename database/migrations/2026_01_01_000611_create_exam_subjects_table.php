<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One paper: this exam, this class-subject, these marks.
 *
 * Component columns exist because Bangladeshi pass rules are per component, not per
 * paper. A student scoring 60 total but 8/40 in Creative Questions has failed, and a
 * single obtained_marks column cannot express that. Nullable component full marks
 * mean "this paper has no such component", which keeps primary-school papers simple.
 *
 * `is_countable` lets a school hold an exam that appears on the marksheet but does
 * not affect GPA - religious studies in some schools, or a fourth paper being
 * trialled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_subject_id')->constrained()->restrictOnDelete();
            $table->decimal('full_marks', 6, 2);
            $table->decimal('pass_marks', 6, 2);
            $table->decimal('cq_full_marks', 6, 2)->nullable()->comment('Creative / written');
            $table->decimal('cq_pass_marks', 6, 2)->nullable();
            $table->decimal('mcq_full_marks', 6, 2)->nullable();
            $table->decimal('mcq_pass_marks', 6, 2)->nullable();
            $table->decimal('practical_full_marks', 6, 2)->nullable();
            $table->decimal('practical_pass_marks', 6, 2)->nullable();
            $table->boolean('is_countable')->default(true)->comment('Counts toward GPA');
            $table->date('exam_date')->nullable();
            $table->time('starts_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('room_no', 30)->nullable();
            $table->unsignedSmallInteger('serial')->default(0);
            $table->timestamps();
            
            $table->unique(['exam_id', 'class_subject_id'], 'exam_subjects_unique');
            $table->index(['exam_id', 'exam_date'], 'exam_subjects_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
    }
};
