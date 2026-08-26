<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money actually received, i.e. the money receipt. Deliberately NOT tied to a
 * single invoice — see payment_allocations. A guardian handing over 2,000 BDT
 * to clear two months of dues produces one payment and two allocations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 40)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->string('method', 20)->default('cash');
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100)->nullable();

            $table->timestamp('paid_at');
            $table->string('status', 20)->default('completed')->index();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
