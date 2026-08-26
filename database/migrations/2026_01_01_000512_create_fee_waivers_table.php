<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-student concessions: freedom fighter quota, sibling discount, merit
 * scholarship, financial hardship. `fee_head_id` NULL = applies to all heads.
 *
 * Waivers are stored as rules rather than baked into fee_structures so the
 * rate card stays clean and the discount is explainable on the invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type', 20)->default('percent'); // percent | fixed
            $table->decimal('value', 12, 2);
            $table->string('reason')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'academic_session_id'], 'fee_waiver_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_waivers');
    }
};
