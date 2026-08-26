<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One rendered artefact, addressable and re-downloadable.
 *
 * `subject` is polymorphic because the same table serves a student ID card, an
 * employee ID card, a payslip and a fee receipt. `payload` snapshots the data the
 * document was rendered from - the reason is the same as for payslips: a testimonial
 * issued in 2024 must reprint identically in 2027 even though the student has since
 * left and the head teacher has changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index()->comment('DocumentType enum');
            $table->morphs('subject');
            $table->string('serial_no', 60)->nullable()->unique()->comment('Printed reference, e.g. TC-2026-000148');
            $table->string('status', 20)->default('pending')->index()->comment('DocumentStatus enum');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable()->comment('sha256 of the file, to detect tampering');
            $table->json('payload')->nullable()->comment('Snapshot of the rendered data');
            $table->text('error')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedSmallInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'subject_type', 'subject_id'], 'generated_documents_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
