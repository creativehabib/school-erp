<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Class Six, Morning shift, 2026, Tuition Fee = 800 BDT, due on the 10th."
 *
 * This is the rate card that invoice generation reads. shift_id is nullable:
 * NULL means the rate applies to every shift of that class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('due_day')->default(10);

            $table->decimal('late_fine_amount', 12, 2)->default(0);
            $table->string('late_fine_type', 20)->default('fixed'); // fixed | per_day | percent
            $table->unsignedSmallInteger('fine_grace_days')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'school_class_id', 'fee_head_id', 'shift_id'],
                'fee_structures_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
