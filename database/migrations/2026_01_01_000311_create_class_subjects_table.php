<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a given class actually studies, and on what terms.
 *
 * `group` is nullable: NULL means every student in the class takes it, a value means
 * it only applies to Science / Business Studies / Humanities students. That single
 * nullable column is what lets classes 9-10 stream without a separate schema branch.
 *
 * `is_optional_candidate` marks a subject that MAY be a student's 4th subject. It is
 * not the same as "this student's 4th subject" - that is per student and lives on
 * student_subjects, because two classmates can have different 4th subjects and the
 * GPA rule applies to whichever one is theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('group', 30)->nullable()->comment('NULL = all students in the class');
            $table->boolean('is_optional_candidate')->default(false);
            $table->boolean('has_practical')->default(false);
            $table->decimal('full_marks', 6, 2)->default(100);
            $table->decimal('pass_marks', 6, 2)->default(33);
            $table->unsignedSmallInteger('serial')->default(0)->comment('Print order on the marksheet');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['school_class_id', 'subject_id', 'group'], 'class_subjects_unique');
            $table->index(['school_class_id', 'group'], 'class_subjects_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
