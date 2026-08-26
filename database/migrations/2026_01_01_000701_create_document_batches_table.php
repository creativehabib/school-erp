<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bulk generation run: "admit cards for class 9, section A, Annual exam".
 *
 * Batches are first-class rows rather than ephemeral queue state because the operator
 * needs to come back tomorrow and re-download the same merged PDF without paying the
 * rendering cost again, and because a partially failed run has to be resumable for
 * the failed subset only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->comment('DocumentType enum');
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status', 24)->default('queued')->index()->comment('BatchStatus enum');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('merged_path')->nullable()->comment('Single print-ready PDF for the whole batch');
            $table->json('filters')->nullable()->comment('Exactly what was selected, so the run is reproducible');
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_batches');
    }
};
