<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMMUTABLE student identity only.
 *
 * Note what is NOT here: current class, section, shift or roll number. Those
 * change every academic year and live in `student_enrollments`. Putting them
 * on this table is the most common design mistake in school ERPs — it makes
 * historical marksheets and invoices silently wrong the moment a student is
 * promoted. `admission_*` columns are the exception: they record the intake
 * event and never change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_no', 30)->unique();

            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('nationality', 30)->default('Bangladeshi');
            $table->string('birth_certificate_no', 40)->nullable();
            $table->string('nid', 30)->nullable();

            // Board identifiers, used on admit cards and testimonials.
            $table->foreignId('board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('board_roll', 30)->nullable();
            $table->string('board_registration_no', 40)->nullable();
            $table->string('board_session', 20)->nullable();

            $table->date('admission_date');
            $table->foreignId('admission_session_id')->nullable()
                  ->constrained('academic_sessions')->nullOnDelete();
            $table->foreignId('admission_class_id')->nullable()
                  ->constrained('school_classes')->nullOnDelete();
            $table->string('previous_school')->nullable();

            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('photo_path')->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->date('left_on')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('board_roll');
            $table->index('board_registration_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
