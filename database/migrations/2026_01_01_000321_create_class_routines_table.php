<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (section, weekday, period) slot.
 *
 * Two unique indexes carry real weight here. The first stops two subjects being put
 * in the same slot for the same section. The second stops one teacher being booked
 * into two rooms at the same time - the single most common data-entry error when a
 * head teacher builds a routine by hand. MySQL permits repeated NULLs in a unique
 * index, so free slots and unassigned teachers do not collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->comment('0=Sunday .. 6=Saturday, matches Carbon');
            $table->foreignId('class_subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_no', 20)->nullable();
            $table->timestamps();
            
            $table->unique(['academic_session_id', 'section_id', 'day_of_week', 'period_id'], 'routine_slot_unique');
            $table->unique(['academic_session_id', 'employee_id', 'day_of_week', 'period_id'], 'routine_teacher_unique');
            $table->index(['academic_session_id', 'section_id', 'day_of_week'], 'routine_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_routines');
    }
};
