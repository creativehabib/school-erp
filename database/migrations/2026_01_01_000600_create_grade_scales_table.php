<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named grading table. Plural because the scale is not universal: the SSC 5.00
 * scale differs from the primary-school scale some schools use for classes 1-5, and
 * a school that changes scale must not retroactively alter last year's marksheets.
 * Exams point at a scale, so old results keep the old scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->decimal('max_gpa', 3, 2)->default(5.00);
            $table->decimal('optional_subject_deduction', 3, 2)->default(2.00)
                ->comment('BD 4th-subject rule: points above this are added to the total');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
