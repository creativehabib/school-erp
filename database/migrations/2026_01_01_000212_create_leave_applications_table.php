<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();

            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->text('reason');
            $table->string('document_path')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Who covers the classes while this teacher is away.
            $table->foreignId('substitute_employee_id')->nullable()
                  ->constrained('employees')->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'from_date', 'to_date'], 'leave_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
