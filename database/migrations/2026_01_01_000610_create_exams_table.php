<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An exam event within a session.
 *
 * `weight` supports the common Bangladeshi final-result formula where the annual
 * result is a weighted blend of terms (e.g. 25% half-yearly + 75% annual). Storing
 * the weight per exam means the combined result is derived, not hand-keyed.
 *
 * `is_locked` is the guard that matters operationally: once results are published,
 * mark entry must stop, or a teacher "fixing" one number silently invalidates
 * printed marksheets and merit positions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_scale_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 30);
            $table->string('type', 30)->comment('ExamType enum');
            $table->unsignedTinyInteger('term')->nullable()->comment('1, 2, 3 - for weighted final results');
            $table->decimal('weight', 5, 2)->default(100.00)->comment('Contribution % to the combined result');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('mark_entry_deadline')->nullable();
            $table->boolean('is_locked')->default(false)->index();
            $table->boolean('publish_marksheet')->default(false)->comment('Visible to guardian / student portals');
            $table->timestamp('result_published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['academic_session_id', 'code'], 'exams_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
