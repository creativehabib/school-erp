<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bands inside a scale: A+ = 80-100 = 5.00, F = 0-32 = 0.00, and so on.
 *
 * `is_failing` is data, not a hard-coded "gpa == 0" test. Some primary scales award
 * a non-zero point to the lowest band while still calling it a fail, and the
 * "F in any subject fails the whole result" rule must follow the school's definition
 * rather than a magic number in PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_scale_id')->constrained()->cascadeOnDelete();
            $table->string('name', 5)->comment('A+, A, A-, B, C, D, F');
            $table->string('name_bn', 10)->nullable();
            $table->decimal('min_marks', 6, 2);
            $table->decimal('max_marks', 6, 2);
            $table->decimal('gpa', 3, 2);
            $table->boolean('is_failing')->default(false);
            $table->string('remarks', 40)->nullable()->comment('Excellent / Very Good / Fail');
            $table->unsignedSmallInteger('serial')->default(0);
            $table->timestamps();
            
            $table->unique(['grade_scale_id', 'name'], 'grade_scale_items_unique');
            $table->index(['grade_scale_id', 'min_marks'], 'grade_scale_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scale_items');
    }
};
